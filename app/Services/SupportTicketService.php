<?php

namespace STS\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use STS\Models\SupportTicket;
use STS\Models\SupportTicketAttachment;

class SupportTicketService
{
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

    public function applyUserReplyTransition(SupportTicket $ticket, int $actorUserId): void
    {
        $ticket->status = 'Esperando respuesta';
        $ticket->unread_for_admin++;
        $ticket->unread_for_user = 0;
        $ticket->last_reply_at = now();
        $ticket->updated_by = $actorUserId;
    }

    public function applyAdminReplyTransition(SupportTicket $ticket, int $actorUserId): void
    {
        if (in_array($ticket->status, ['Open', 'Esperando respuesta'], true)) {
            $ticket->status = 'En revision';
        }
        $ticket->unread_for_user++;
        $ticket->unread_for_admin = 0;
        $ticket->last_reply_at = now();
        $ticket->updated_by = $actorUserId;
    }
}
