<?php

namespace STS\Console\Commands;

use Illuminate\Console\Command;
use STS\Services\SupportTicketService;

class SupportTicketsReleaseExpiredAssignments extends Command
{
    protected $signature = 'support-tickets:release-expired-assignments';

    protected $description = 'Release expired support ticket assignments after inactivity threshold';

    public function __construct(private readonly SupportTicketService $supportTicketService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $released = $this->supportTicketService->releaseExpiredAssignments();

        $this->info('Support ticket assignments released: '.$released);

        return self::SUCCESS;
    }
}
