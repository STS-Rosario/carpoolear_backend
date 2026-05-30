<?php

namespace STS\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use STS\Http\Controllers\Concerns\StreamsSupportTicketAttachments;
use STS\Http\Controllers\Controller;
use STS\Models\SupportTicket;
use STS\Models\SupportTicketReply;
use STS\Notifications\SupportTicketReplyNotification;
use STS\Services\SupportTicketService;
use STS\Support\ImageAttachmentRules;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportTicketController extends Controller
{
    use StreamsSupportTicketAttachments;

    private const ADMIN_CREATED_TICKET_STATUS = 'Esperando respuesta';

    /**
     * @return list<string>
     */
    private static function ticketDetailRelationships(): array
    {
        return [
            'replies.attachments',
            'replies.user:id,name',
            'attachments',
            'user',
        ];
    }

    /**
     * @return list<string>
     */
    private static function ticketIndexRelationships(): array
    {
        return ['user:id,name'];
    }

    public function __construct(private readonly SupportTicketService $supportTicketService) {}

    public function index(Request $request): JsonResponse
    {
        $query = SupportTicket::query()
            ->with(self::ticketIndexRelationships());

        $type = $request->query('type');
        if (is_string($type) && $type !== '' && in_array($type, SupportTicket::TYPES, true)) {
            $query->where('type', $type);
        }

        $priority = $request->query('priority');
        if (is_string($priority) && in_array($priority, ['low', 'normal', 'high'], true)) {
            $query->where('priority', $priority);
        }

        return response()->json([
            'data' => $query->orderByDesc('id')->get(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $ticket = SupportTicket::with(self::ticketDetailRelationships())->findOrFail($id);

        return response()->json(['data' => $ticket]);
    }

    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'type' => SupportTicket::typeValidationRule(),
            'subject' => 'required|string|min:3|max:160',
            'message_markdown' => 'required|string|min:1',
        ]);

        $admin = auth()->user();
        $ticket = DB::transaction(function () use ($validated, $admin) {
            $ticket = SupportTicket::create([
                'user_id' => (int) $validated['user_id'],
                'type' => $validated['type'],
                'subject' => $validated['subject'],
                'status' => self::ADMIN_CREATED_TICKET_STATUS,
                'priority' => SupportTicket::TYPE_DEFAULT_PRIORITIES[$validated['type']] ?? 'normal',
                'unread_for_user' => 1,
                'unread_for_admin' => 0,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
                'last_reply_at' => now(),
            ]);

            SupportTicketReply::create([
                'ticket_id' => $ticket->id,
                'user_id' => $admin->id,
                'is_admin' => true,
                'message_markdown' => $validated['message_markdown'],
                'created_by' => $admin->id,
            ]);

            return $ticket->fresh();
        });

        $notification = new SupportTicketReplyNotification;
        $notification->setAttribute('ticket', $ticket);
        $notification->setAttribute('from', $admin);
        try {
            $notification->notify($ticket->user);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['data' => $ticket]);
    }

    public function reply(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message_markdown' => 'required|string|min:1',
            'attachments' => 'nullable|array|max:3',
            'attachments.*' => ImageAttachmentRules::FILE,
        ]);

        $admin = auth()->user();
        $ticket = SupportTicket::findOrFail($id);

        if (! $this->supportTicketService->ticketAcceptsReplies($ticket)) {
            return response()->json(['error' => 'Ticket is closed for replies'], 422);
        }

        if ($this->supportTicketService->ticketAlreadyHasReplyWithMessageMarkdown(
            $ticket->id,
            $validated['message_markdown'],
        )) {
            return response()->json(['error' => 'Duplicate reply'], 422);
        }

        DB::transaction(function () use ($validated, $admin, $ticket) {
            $reply = SupportTicketReply::create([
                'ticket_id' => $ticket->id,
                'user_id' => $admin->id,
                'is_admin' => true,
                'message_markdown' => $validated['message_markdown'],
                'created_by' => $admin->id,
            ]);

            foreach (($validated['attachments'] ?? []) as $file) {
                $this->supportTicketService->storeReplyAttachments([$file], $ticket->id, $admin->id, $reply->id);
            }

            $this->supportTicketService->applyAdminReplyTransition($ticket, $admin->id);
            $ticket->save();
        });

        $notification = new SupportTicketReplyNotification;
        $notification->setAttribute('ticket', $ticket->fresh());
        $notification->setAttribute('from', $admin);
        try {
            $notification->notify($ticket->user);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['data' => $ticket->fresh()]);
    }

    public function resolve(int $id, Request $request): JsonResponse
    {
        return $this->applyActionStatus($id, $request, 'Resuelto');
    }

    public function close(int $id, Request $request): JsonResponse
    {
        return $this->applyActionStatus($id, $request, 'Cerrado');
    }

    public function reopen(int $id): JsonResponse
    {
        $admin = auth()->user();
        $ticket = SupportTicket::findOrFail($id);
        $ticket->status = 'En revision';
        $ticket->closed_at = null;
        $ticket->closed_by = null;
        $ticket->updated_by = $admin->id;
        $ticket->save();

        return response()->json(['data' => $ticket]);
    }

    public function unresolve(int $id): JsonResponse
    {
        $admin = auth()->user();
        $ticket = SupportTicket::findOrFail($id);

        if ($ticket->status !== 'Resuelto') {
            return response()->json(['error' => 'Ticket is not resolved'], 422);
        }

        $this->supportTicketService->unresolveTicket($ticket, $admin->id);

        return response()->json(['data' => $ticket->fresh()]);
    }

    public function markNeedsReview(int $id, Request $request): JsonResponse
    {
        return $this->applyActionStatus($id, $request, SupportTicket::STATUS_NEEDS_REVIEW);
    }

    public function updateStatus(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => SupportTicket::statusValidationRule(),
        ]);
        $admin = auth()->user();
        $ticket = SupportTicket::findOrFail($id);
        $ticket->status = $validated['status'];
        $ticket->updated_by = $admin->id;
        if ($validated['status'] === 'Cerrado') {
            $ticket->closed_at = now();
            $ticket->closed_by = $admin->id;
        }
        $ticket->save();

        return response()->json(['data' => $ticket]);
    }

    public function updatePriority(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'priority' => 'required|in:low,normal,high',
        ]);
        $ticket = SupportTicket::findOrFail($id);
        $ticket->priority = $validated['priority'];
        $ticket->updated_by = auth()->id();
        $ticket->save();

        return response()->json(['data' => $ticket]);
    }

    public function updateType(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => SupportTicket::typeValidationRule(),
        ]);
        $ticket = SupportTicket::findOrFail($id);
        $ticket->type = $validated['type'];
        $ticket->priority = SupportTicket::TYPE_DEFAULT_PRIORITIES[$validated['type']] ?? 'normal';
        $ticket->updated_by = auth()->id();
        $ticket->save();

        return response()->json(['data' => $ticket]);
    }

    public function updateInternalNote(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'internal_note_markdown' => 'nullable|string',
        ]);
        $ticket = SupportTicket::findOrFail($id);
        $ticket->internal_note_markdown = $validated['internal_note_markdown'] ?? null;
        $ticket->updated_by = auth()->id();
        $ticket->save();

        return response()->json(['data' => $ticket]);
    }

    public function attachmentImage(int $ticketId, int $attachmentId): StreamedResponse|JsonResponse
    {
        SupportTicket::query()->findOrFail($ticketId);

        return $this->streamSupportTicketAttachment($this->supportTicketService, $ticketId, $attachmentId);
    }

    public function purgeAttachments(int $id): JsonResponse
    {
        SupportTicket::query()->findOrFail($id);
        $this->supportTicketService->purgeTicketAttachments($id);

        return response()->json(['message' => 'Attachments purged']);
    }

    private function applyActionStatus(int $id, Request $request, string $status): JsonResponse
    {
        $validated = $request->validate([
            'message_markdown' => 'nullable|string',
        ]);
        $admin = auth()->user();
        $ticket = SupportTicket::findOrFail($id);

        DB::transaction(function () use ($ticket, $admin, $status, $validated) {
            if (! empty($validated['message_markdown'])) {
                SupportTicketReply::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $admin->id,
                    'is_admin' => true,
                    'message_markdown' => $validated['message_markdown'],
                    'created_by' => $admin->id,
                ]);
            }

            $ticket->status = $status;
            $ticket->updated_by = $admin->id;
            if ($status === 'Cerrado') {
                $ticket->closed_by = $admin->id;
                $ticket->closed_at = now();
            }
            $ticket->save();
        });

        return response()->json(['data' => $ticket->fresh()]);
    }
}
