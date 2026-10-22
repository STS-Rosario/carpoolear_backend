<?php

namespace STS\Console\Commands;

use Illuminate\Console\Command;
use STS\Models\SupportTicket;
use STS\Notifications\SupportTicketAutoClosedNotification;
use STS\Services\SupportTicketService;

class SupportTicketsAutoClose extends Command
{
    protected $signature = 'support-tickets:autoclose';

    protected $description = 'Auto close resolved support tickets and tickets left waiting on the user';

    public function handle(SupportTicketService $supportTicketService): int
    {
        $updated = $this->closeResolvedTickets();
        $closedForInactivity = $this->closeTicketsWaitingOnUser($supportTicketService);

        $this->info('Support tickets auto-closed: '.$updated);
        $this->info('Tickets closed for user inactivity: '.$closedForInactivity);

        return self::SUCCESS;
    }

    /**
     * Bulk-closes tickets already marked as Resuelto after the inactivity threshold.
     * Kept as-is: no message is appended for this case.
     */
    private function closeResolvedTickets(): int
    {
        $days = (int) config('carpoolear.support_ticket_autoclose_days', 10);
        $threshold = now()->subDays($days);

        return SupportTicket::where('status', 'Resuelto')
            ->where(function ($query) use ($threshold) {
                $query->where('last_reply_at', '<=', $threshold)
                    ->orWhere(function ($inner) use ($threshold) {
                        $inner->whereNull('last_reply_at')->where('updated_at', '<=', $threshold);
                    });
            })
            ->update([
                'status' => 'Cerrado',
                'closed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Closes tickets left waiting on the user (status "Esperando respuesta")
     * when the owner has not replied within the configured threshold. Each one
     * gets an automated closing message plus a notification to the owner.
     */
    private function closeTicketsWaitingOnUser(SupportTicketService $supportTicketService): int
    {
        $days = (int) config('carpoolear.support_ticket_user_inactivity_days', 30);
        $threshold = now()->subDays($days);

        $candidates = SupportTicket::query()
            ->where('status', 'Esperando respuesta')
            ->where('last_reply_at', '<=', $threshold)
            ->with('user')
            ->get();

        $closed = 0;

        foreach ($candidates as $ticket) {
            $lastUserReplyAt = $supportTicketService->latestUserReplyAt($ticket);
            $inactiveSince = $lastUserReplyAt ?? $ticket->created_at;

            if ($inactiveSince !== null && $inactiveSince->greaterThan($threshold)) {
                continue;
            }

            if (! $supportTicketService->closeTicketForUserInactivity($ticket)) {
                continue;
            }

            $closed++;
            $this->notifyOwner($ticket);
        }

        return $closed;
    }

    private function notifyOwner(SupportTicket $ticket): void
    {
        if ($ticket->user === null) {
            return;
        }

        $notification = new SupportTicketAutoClosedNotification;
        $notification->setAttribute('ticket', $ticket);

        try {
            $notification->notify($ticket->user);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
