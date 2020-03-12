<?php

namespace STS\Listeners\User;

use STS\Events\User\Create;
use STS\Notifications\NewUserNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use STS\Contracts\Repository\User as UserRepository;

class CreateHandler implements ShouldQueue
{
    protected $userRepo;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(UserRepository $userRepo)
    {
        $this->userRepo = $userRepo;
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
        $user = $this->userRepo->show($event->id);
        if ($user && $user->email) {
            $notification = new NewUserNotification();
            $notification->notify($user);
        }
    }
}
