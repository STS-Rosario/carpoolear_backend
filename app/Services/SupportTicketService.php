<?php

namespace STS\Services;

use Illuminate\Http\UploadedFile;
use STS\Models\SupportTicket;
use STS\Models\SupportTicketAttachment;
use STS\Models\SupportTicketReply;
use STS\Models\User;
use STS\Support\SupportTicketOpeningAutoReply;

class SupportTicketService
{
    public function __construct(
        private readonly SupportTicketAttachmentStorage $attachmentStorage,
    ) {}

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

    /**
     * Appends the canned welcome reply on user-created tickets.
     * Does not use {@see applyAdminReplyTransition}; status stays admin-actionable.
     */
    public function appendOpeningAutoReply(SupportTicket $ticket): bool
    {
        $actorUserId = $this->resolveAutoReplyActorUserId();
        if ($actorUserId === null) {
            return false;
        }

        if ($this->ticketAlreadyHasReplyWithMessageMarkdown($ticket->id, SupportTicketOpeningAutoReply::MARKDOWN)) {
            return false;
        }

        SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $actorUserId,
            'is_admin' => true,
            'message_markdown' => SupportTicketOpeningAutoReply::MARKDOWN,
            'created_by' => null,
        ]);

        $this->applyOpeningAutoReplyTransition($ticket, $actorUserId);
        $ticket->save();

        return true;
    }

    public function resolveAutoReplyActorUserId(): ?int
    {
        $configured = config('carpoolear.support_ticket_auto_reply_user_id');
        if ($configured) {
            $id = User::query()->whereKey((int) $configured)->value('id');

            return $id ? (int) $id : null;
        }

        $adminId = User::query()->where('is_admin', true)->orderBy('id')->value('id');

        return $adminId ? (int) $adminId : null;
    }

    public function storeReplyAttachments(array $files, int $ticketId, int $userId, int $replyId): void
    {
        foreach ($files as $file) {
            if (! ($file instanceof UploadedFile)) {
                continue;
            }

            $this->attachmentStorage->storeForReply($file, $ticketId, $replyId, $userId);
        }
    }

    public function purgeTicketAttachments(int $ticketId): int
    {
        return $this->attachmentStorage->purgeForTicket($ticketId);
    }

    public function findTicketAttachment(int $ticketId, int $attachmentId): ?SupportTicketAttachment
    {
        return $this->attachmentStorage->findForTicket($ticketId, $attachmentId);
    }

    public function diskForAttachmentPath(string $path): ?\Illuminate\Contracts\Filesystem\Filesystem
    {
        return $this->attachmentStorage->diskForPath($path);
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

    /**
     * Canned opening reply only: notify the ticket owner without changing status
     * or clearing admin unread (team still owes a human response).
     */
    private function applyOpeningAutoReplyTransition(SupportTicket $ticket, int $actorUserId): void
    {
        $ticket->unread_for_user++;
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

    /**
     * Status to restore when undoing admin-set statuses (Resuelto, Necesita revisión),
     * based on who sent the latest reply.
     */
    public function statusAfterUndoResolve(SupportTicket $ticket): string
    {
        $lastReply = $ticket->replies()
            ->orderByDesc('id')
            ->first();

        if ($lastReply === null) {
            return 'En revision';
        }

        return $lastReply->is_admin ? 'Esperando respuesta' : 'En revision';
    }

    public function unresolveTicket(SupportTicket $ticket, int $adminUserId): void
    {
        $this->restoreStatusFromLastReply($ticket, $adminUserId);
    }

    public function undoNeedsReviewTicket(SupportTicket $ticket, int $adminUserId): void
    {
        $this->restoreStatusFromLastReply($ticket, $adminUserId);
    }

    private function restoreStatusFromLastReply(SupportTicket $ticket, int $adminUserId): void
    {
        $ticket->status = $this->statusAfterUndoResolve($ticket);
        $ticket->updated_by = $adminUserId;
        $ticket->save();
    }

    public function ticketAcceptsReplies(SupportTicket $ticket): bool
    {
        return ! in_array($ticket->status, self::TERMINAL_USER_REPLY_STATUSES, true);
    }

    public function ticketIsAssignableByAdmin(SupportTicket $ticket): bool
    {
        if (in_array($ticket->status, self::TERMINAL_USER_REPLY_STATUSES, true)) {
            return false;
        }

        if ((int) $ticket->unread_for_admin > 0) {
            return true;
        }

        return in_array($ticket->status, ['Open', 'En revision', SupportTicket::STATUS_NEEDS_REVIEW], true);
    }
}
