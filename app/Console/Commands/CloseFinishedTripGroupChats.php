<?php

namespace STS\Console\Commands;

use Illuminate\Console\Command;
use STS\Models\Conversation;
use STS\Models\Trip;

class CloseFinishedTripGroupChats extends Command
{
    protected $signature = 'conversation:close-finished-group-chats';

    protected $description = 'Close trip group chats 48 hours after the trip date';

    /**
     * Trip group chats are closed 48 hours after the trip departure (trip_date).
     * As a safety net (e.g. when the delete event never fired) the chats of
     * canceled or soft-deleted trips are closed as well.
     */
    public function handle(): int
    {
        $threshold = now()->subHours(48);

        $closed = Conversation::query()
            ->where('conversations.type', Conversation::TYPE_TRIP_CONVERSATION)
            ->whereNull('conversations.closed_at')
            ->join('trips', 'trips.id', '=', 'conversations.trip_id')
            ->where(function ($query) use ($threshold) {
                // 48 hours after departure, for regular (non weekly) trips.
                $query->where(function ($finished) use ($threshold) {
                    $finished->whereNull('trips.deleted_at')
                        ->where(function ($schedule) {
                            $schedule->where('trips.weekly_schedule', 0)
                                ->orWhereNull('trips.weekly_schedule');
                        })
                        ->whereNotNull('trips.trip_date')
                        ->where('trips.trip_date', '<=', $threshold);
                })
                    // ...or as soon as the trip is canceled or deleted.
                    ->orWhereNotNull('trips.deleted_at')
                    ->orWhere('trips.state', Trip::STATE_CANCELED);
            })
            ->update(['closed_at' => now()]);

        $this->info('Trip group chats closed: '.$closed);

        return self::SUCCESS;
    }
}
