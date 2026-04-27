<?php

namespace STS\Console\Commands;

use Illuminate\Console\Command;
use STS\Models\SupportTicket;

class SupportTicketsAutoClose extends Command
{
    protected $signature = 'support-tickets:autoclose';

    protected $description = 'Auto close resolved support tickets after inactivity threshold';

    public function handle(): int
    {
        $days = (int) config('carpoolear.support_ticket_autoclose_days', 10);
        $threshold = now()->subDays($days);

        $updated = SupportTicket::where('status', 'Resuelto')
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

        $this->info('Support tickets auto-closed: '.$updated);

        return self::SUCCESS;
    }
}
