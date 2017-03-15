<?php

namespace STS\Listeners\User;

use STS\Events\User\Update;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateHandler
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  Update  $event
     * @return void
     */
    public function handle(Update $event)
    {
        //
    }
}
