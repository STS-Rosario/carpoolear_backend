<?php

namespace STS\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use STS\Events\User\Create;

class TestJob implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct() {}

    /**
     * Handle the event.
     *
     *
     * @return void
     */
    public function handle(Create $event) {}
}
