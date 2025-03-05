<?php

namespace STS\Listeners\User;

use STS\Events\User\Create;
use STS\Notifications\NewUserNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use STS\Repository\UserRepository; 

class TestJob implements ShouldQueue
{ 

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(U)
    {
        
    }

    /**
     * Handle the event.
     *
     * @param Create $event
     *
     * @return void
     */
    public function handle(Create $event)
    {
        \Log::info('create handler');
    }
}
