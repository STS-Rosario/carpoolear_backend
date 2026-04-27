<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use STS\Http\Controllers\Controller;
use STS\Models\SupportTicket;
use STS\Models\SupportTicketAttachment;
use STS\Models\SupportTicketReply;

class SupportTicketController extends Controller
{
    public function __construct()
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
            'type' => 'required|in:bug_report,contact,feedback,report',
            'subject' => 'required|string|min:3|max:160',
            'message_markdown' => 'required|string|min:1',
            'priority' => 'nullable|in:low,normal,high',
            'attachments' => 'nullable|array|max:3',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        $user = auth()->user();
        $ticket = DB::transaction(function () use ($validated, $user) {
            $ticket = SupportTicket::create([
                'user_id' => $user->id,
                'type' => $validated['type'],
                'subject' => $validated['subject'],
                'status' => 'Open',
                'priority' => $validated['priority'] ?? 'normal',
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
                $this->storeAttachment($file, $user->id, null, $reply->id);
            }

            return $ticket->fresh();
        });

        return response()->json(['data' => $ticket]);
    }

    public function reply(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message_markdown' => 'required|string|min:1',
            'attachments' => 'nullable|array|max:3',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        $user = auth()->user();
        $ticket = SupportTicket::where('user_id', $user->id)->find($id);
        if (! $ticket) {
            return response()->json(['error' => 'Ticket not found'], 404);
        }
        if (in_array($ticket->status, ['Resuelto', 'Cerrado'], true)) {
            return response()->json(['error' => 'Ticket is closed for replies'], 422);
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
                $this->storeAttachment($file, $user->id, null, $reply->id);
            }

            $ticket->status = 'Esperando respuesta';
            $ticket->unread_for_admin = $ticket->unread_for_admin + 1;
            $ticket->unread_for_user = 0;
            $ticket->last_reply_at = now();
            $ticket->updated_by = $user->id;
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

    private function storeAttachment($file, int $userId, ?int $ticketId, ?int $replyId): SupportTicketAttachment
    {
        $folder = 'support/'.date('Y').'/'.date('m');
        $filename = Str::ulid().'_'.Str::random(20).'.'.$file->getClientOriginalExtension();
        $path = Storage::disk('public')->putFileAs($folder, $file, $filename);

        return SupportTicketAttachment::create([
            'ticket_id' => $ticketId,
            'reply_id' => $replyId,
            'user_id' => $userId,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => (int) $file->getSize(),
        ]);
    }
}
