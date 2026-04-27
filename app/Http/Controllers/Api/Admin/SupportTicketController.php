<?php

namespace STS\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use STS\Http\Controllers\Controller;
use STS\Models\SupportTicket;
use STS\Models\SupportTicketReply;
use STS\Notifications\SupportTicketReplyNotification;
use STS\Services\SupportTicketService;

class SupportTicketController extends Controller
{
    public function __construct(private readonly SupportTicketService $supportTicketService) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => SupportTicket::orderByDesc('id')->get(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $ticket = SupportTicket::with(['replies.attachments', 'attachments', 'user'])->findOrFail($id);

        return response()->json(['data' => $ticket]);
    }

    public function reply(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message_markdown' => 'required|string|min:1',
            'attachments' => 'nullable|array|max:3',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        $admin = auth()->user();
        $ticket = SupportTicket::findOrFail($id);

        DB::transaction(function () use ($validated, $admin, $ticket) {
            $reply = SupportTicketReply::create([
                'ticket_id' => $ticket->id,
                'user_id' => $admin->id,
                'is_admin' => true,
                'message_markdown' => $validated['message_markdown'],
                'created_by' => $admin->id,
            ]);

            foreach (($validated['attachments'] ?? []) as $file) {
                $this->supportTicketService->storeReplyAttachments([$file], $admin->id, $reply->id);
            }

            $this->supportTicketService->applyAdminReplyTransition($ticket, $admin->id);
            $ticket->save();
        });

        $notification = new SupportTicketReplyNotification([
            'ticket' => $ticket->fresh(),
            'from' => $admin,
        ]);
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

    public function updateStatus(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:Open,Esperando respuesta,En revision,Resuelto,Cerrado',
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
