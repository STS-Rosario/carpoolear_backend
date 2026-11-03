<?php

namespace STS\Console\Commands;

use Illuminate\Console\Command;
use STS\Models\SupportTicket;
use STS\Models\User;
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
     * when there has been no activity within the configured threshold. Each one
     * gets an automated closing message plus a notification to the owner.
     *
     * The inactivity clock is last_reply_at (any reply, user or admin), falling
     * back to updated_at when a ticket never had a reply. A recent admin
     * follow-up therefore restarts the window.
     */
    private function closeTicketsWaitingOnUser(SupportTicketService $supportTicketService): int
    {
        $days = (int) config('carpoolear.support_ticket_user_inactivity_days', 30);
        $threshold = now()->subDays($days);

        $actorId = $supportTicketService->resolveAutoReplyActorUserId();
        $actor = $actorId !== null ? User::query()->find($actorId) : null;
        if ($actor === null) {
            $this->warn('Support tickets auto-close skipped: no auto-reply actor user configured or found.');

            return 0;
        }

        $closed = 0;

        SupportTicket::query()
            ->where('status', 'Esperando respuesta')
            ->where(function ($query) use ($threshold) {
                $query->where('last_reply_at', '<=', $threshold)
                    ->orWhere(function ($inner) use ($threshold) {
                        $inner->whereNull('last_reply_at')->where('updated_at', '<=', $threshold);
                    });
            })
            ->with('user')
            ->chunkById(100, function ($tickets) use ($supportTicketService, $actor, &$closed) {
                foreach ($tickets as $ticket) {
                    try {
                        \Illuminate\Support\Facades\DB::transaction(function () use ($ticket, $supportTicketService, $actor, &$closed) {
                            $locked = SupportTicket::query()
                                ->with('user')
                                ->whereKey($ticket->id)
                                ->lockForUpdate()
                                ->first();

                            if (! $locked || $locked->status !== 'Esperando respuesta') {
                                return;
                            }

                            if (! $supportTicketService->closeTicketForUserInactivity($locked)) {
                                return;
                            }

                            $closed++;
                            $this->notifyOwner($locked, $actor);
                        });
                    } catch (\Throwable $e) {
                        report($e);
                        $this->warn('Could not auto-close support ticket #'.$ticket->id.': '.$e->getMessage());
                    }
                }
            });

        return $closed;
    }

    private function notifyOwner(SupportTicket $ticket, User $actor): void
    {
        if ($ticket->user === null) {
            return;
        }

        $notification = new SupportTicketAutoClosedNotification;
        $notification->setAttribute('ticket', $ticket);
        $notification->setAttribute('from', $actor);

        try {
            $notification->notify($ticket->user);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
