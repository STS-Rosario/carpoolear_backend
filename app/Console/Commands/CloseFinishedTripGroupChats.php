<?php

namespace STS\Console\Commands;

use Illuminate\Console\Command;
use STS\Models\Conversation;

class CloseFinishedTripGroupChats extends Command
{
    protected $signature = 'conversation:close-finished-group-chats';

    protected $description = 'Close trip group chats 48 hours after the trip date';

    public function handle(): int
    {
        $threshold = now()->subHours(48);

        $closed = Conversation::query()
            ->where('conversations.type', Conversation::TYPE_TRIP_CONVERSATION)
            ->whereNull('conversations.closed_at')
            ->join('trips', 'trips.id', '=', 'conversations.trip_id')
            ->whereNull('trips.deleted_at')
            ->where(function ($query) {
                $query->where('trips.weekly_schedule', 0)
                    ->orWhereNull('trips.weekly_schedule');
            })
            ->whereNotNull('trips.trip_date')
            ->where('trips.trip_date', '<=', $threshold)
            ->update(['closed_at' => now()]);

        $this->info('Trip group chats closed: '.$closed);

        return self::SUCCESS;
    }
}