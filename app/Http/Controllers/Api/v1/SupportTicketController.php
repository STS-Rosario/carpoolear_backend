<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use STS\Http\Controllers\Concerns\StreamsSupportTicketAttachments;
use STS\Http\Controllers\Controller;
use STS\Models\SupportTicket;
use STS\Models\SupportTicketReply;
use STS\Models\User;
use STS\Notifications\SupportTicketReplyNotification;
use STS\Services\SupportTicketService;
use STS\Support\ImageAttachmentRules;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportTicketController extends Controller
{
    use StreamsSupportTicketAttachments;

    public function __construct(private readonly SupportTicketService $supportTicketService)
    {
        $this->middleware('logged');
    }

    public function index(): JsonResponse
    {
        $user = auth()->user();
        $tickets = SupportTicket::where('user_id', $user->id)->orderByDesc('id')->get();

        return response()->json(['data' => $tickets]);
    }

    public function show(int $id): JsonResponse
    {
        $user = auth()->user();
        $ticket = SupportTicket::with(['replies.attachments', 'attachments'])
            ->where('user_id', $user->id)
            ->find($id);
        if (! $ticket) {
            return response()->json(['error' => 'Ticket not found'], 404);
        }

        return response()->json(['data' => $ticket]);
    }

    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => SupportTicket::typeValidationRule(),
            'subject' => 'required|string|min:3|max:160',
            'message_markdown' => 'required|string|min:1',
            'attachments' => 'nullable|array|max:3',
            'attachments.*' => ImageAttachmentRules::FILE,
        ]);

        $user = auth()->user();

        $existingDuplicate = $this->findExistingTicketWithSameOpening(
            (int) $user->id,
            $validated['subject'],
            $validated['message_markdown'],
        );
        if ($existingDuplicate) {
            return response()->json(['data' => $existingDuplicate->fresh()]);
        }

        [$ticket, $addedOpeningAutoReply] = DB::transaction(function () use ($validated, $user) {
            $ticket = SupportTicket::create([
                'user_id' => $user->id,
                'type' => $validated['type'],
                'subject' => $validated['subject'],
                'status' => 'Open',
                'priority' => SupportTicket::TYPE_DEFAULT_PRIORITIES[$validated['type']] ?? 'normal',
                'unread_for_user' => 0,
                'unread_for_admin' => 1,
                'created_by' => $user->id,
                'last_reply_at' => now(),
            ]);

            $reply = SupportTicketReply::create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'is_admin' => false,
                'message_markdown' => $validated['message_markdown'],
                'created_by' => $user->id,
            ]);

            foreach (($validated['attachments'] ?? []) as $file) {
                $this->supportTicketService->storeReplyAttachments([$file], $ticket->id, $user->id, $reply->id);
            }

            $addedOpeningAutoReply = $this->supportTicketService->appendOpeningAutoReply($ticket);

            return [$ticket->fresh(), $addedOpeningAutoReply];
        });

        if ($addedOpeningAutoReply) {
            $this->notifyTicketOwnerOfAdminReply($ticket, $user);
        }

        return response()->json(['data' => $ticket]);
    }

    private function findExistingTicketWithSameOpening(int $userId, string $subject, string $messageMarkdown): ?SupportTicket
    {
        return SupportTicket::query()
            ->where('user_id', $userId)
            ->where('subject', $subject)
            ->whereExists(function ($query) use ($messageMarkdown) {
                $query->selectRaw('1')
                    ->from('support_ticket_replies as str')
                    ->whereColumn('str.ticket_id', 'support_tickets.id')
                    ->where('str.message_markdown', $messageMarkdown)
                    ->whereRaw('str.id = (select min(s.id) from support_ticket_replies as s where s.ticket_id = support_tickets.id)');
            })
            ->orderBy('id')
            ->first();
    }

    public function reply(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message_markdown' => 'required|string|min:1',
            'attachments' => 'nullable|array|max:3',
            'attachments.*' => ImageAttachmentRules::FILE,
        ]);

        $user = auth()->user();
        $ticket = SupportTicket::where('user_id', $user->id)->find($id);
        if (! $ticket) {
            return response()->json(['error' => 'Ticket not found'], 404);
        }
        if (in_array($ticket->status, ['Resuelto', 'Cerrado'], true)) {
            return response()->json(['error' => 'Ticket is closed for replies'], 422);
        }

        if ($this->supportTicketService->ticketAlreadyHasReplyWithMessageMarkdown(
            $ticket->id,
            $validated['message_markdown'],
        )) {
            return response()->json(['error' => 'Duplicate reply'], 422);
        }

        DB::transaction(function () use ($validated, $user, $ticket) {
            $reply = SupportTicketReply::create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'is_admin' => false,
                'message_markdown' => $validated['message_markdown'],
                'created_by' => $user->id,
            ]);
            foreach (($validated['attachments'] ?? []) as $file) {
                $this->supportTicketService->storeReplyAttachments([$file], $ticket->id, $user->id, $reply->id);
            }

            $this->supportTicketService->applyUserReplyTransition($ticket, $user->id);
            $ticket->save();
        });

        return response()->json(['data' => $ticket->fresh()]);
    }

    public function close(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message_markdown' => 'nullable|string',
        ]);
        $user = auth()->user();
        $ticket = SupportTicket::where('user_id', $user->id)->find($id);
        if (! $ticket) {
            return response()->json(['error' => 'Ticket not found'], 404);
        }

        DB::transaction(function () use ($ticket, $user, $validated) {
            if (! empty($validated['message_markdown'])) {
                SupportTicketReply::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $user->id,
                    'is_admin' => false,
                    'message_markdown' => $validated['message_markdown'],
                    'created_by' => $user->id,
                ]);
            }

            $ticket->status = 'Cerrado';
            $ticket->closed_by = $user->id;
            $ticket->closed_at = now();
            $ticket->updated_by = $user->id;
            $ticket->save();
        });

        return response()->json(['data' => $ticket->fresh()]);
    }

    public function attachmentImage(int $ticketId, int $attachmentId): StreamedResponse|JsonResponse
    {
        $user = auth()->user();
        $ticket = SupportTicket::where('user_id', $user->id)->find($ticketId);
        if (! $ticket) {
            return response()->json(['error' => 'Ticket not found'], 404);
        }

        return $this->streamSupportTicketAttachment($this->supportTicketService, $ticketId, $attachmentId);
    }

    private function notifyTicketOwnerOfAdminReply(SupportTicket $ticket, User $owner): void
    {
        $actorUserId = $this->supportTicketService->resolveAutoReplyActorUserId();
        if ($actorUserId === null) {
            return;
        }

        $actor = User::query()->find($actorUserId);
        if ($actor === null) {
            return;
        }

        $notification = new SupportTicketReplyNotification;
        $notification->setAttribute('ticket', $ticket->fresh());
        $notification->setAttribute('from', $actor);
        try {
            $notification->notify($owner);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
