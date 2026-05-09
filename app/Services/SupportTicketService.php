<?php

namespace STS\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use STS\Models\SupportTicket;
use STS\Models\SupportTicketAttachment;
use STS\Models\SupportTicketReply;

class SupportTicketService
{
    /** Statuses where neither party may add replies via normal flows. */
    private const TERMINAL_USER_REPLY_STATUSES = ['Resuelto', 'Cerrado'];

    public function ticketAlreadyHasReplyWithMessageMarkdown(int $ticketId, string $messageMarkdown): bool
    {
        return SupportTicketReply::query()
            ->where('ticket_id', $ticketId)
            ->where('message_markdown', $messageMarkdown)
            ->exists();
    }

    /**
     * Open conversation states: after an admin reply we wait on the ticket owner.
     * {@see applyAdminReplyTransition}
     */
    private const ADMIN_REPLY_SETS_WAITING_FOR_USER = ['Open', 'Esperando respuesta', 'En revision'];

    public function storeReplyAttachments(array $files, int $userId, int $replyId): void
    {
        foreach ($files as $file) {
            if (! ($file instanceof UploadedFile)) {
                continue;
            }

            $folder = 'support/'.date('Y').'/'.date('m');
            $filename = Str::ulid().'_'.Str::random(20).'.'.$file->getClientOriginalExtension();
            $path = Storage::disk('public')->putFileAs($folder, $file, $filename);

            SupportTicketAttachment::create([
                'reply_id' => $replyId,
                'ticket_id' => null,
                'user_id' => $userId,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType() ?? 'application/octet-stream',
                'size_bytes' => (int) $file->getSize(),
            ]);
        }
    }

    /** Marks unread for admins and moves active tickets to pending team review. */
    public function applyUserReplyTransition(SupportTicket $ticket, int $actorUserId): void
    {
        if (! in_array($ticket->status, self::TERMINAL_USER_REPLY_STATUSES, true)) {
            $ticket->status = 'En revision';
        }
        $ticket->unread_for_admin++;
        $ticket->unread_for_user = 0;
        $ticket->last_reply_at = now();
        $ticket->updated_by = $actorUserId;
    }

    /** Bumps unread for the ticket owner and sets waiting-for-user when applicable. */
    public function applyAdminReplyTransition(SupportTicket $ticket, int $actorUserId): void
    {
        if (in_array($ticket->status, self::ADMIN_REPLY_SETS_WAITING_FOR_USER, true)) {
            $ticket->status = 'Esperando respuesta';
        }
        $ticket->unread_for_user++;
        $ticket->unread_for_admin = 0;
        $ticket->last_reply_at = now();
        $ticket->updated_by = $actorUserId;
    }
}
