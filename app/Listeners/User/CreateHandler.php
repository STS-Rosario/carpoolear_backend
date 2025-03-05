<?php

namespace STS\Listeners\User;

use STS\Events\User\Create;
use STS\Notifications\NewUserNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use STS\Repository\UserRepository; 
use Illuminate\Support\Facades\Mail;
use STS\Mail\NewAccount;

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
        if ($user && $user->email && !$user->active) {


            $domain = config('app.url');
            $name_app = config('carpoolear.name_app');
            $url = config('app.url').'/app/activate/'.$user->activation_token;
            $html = view('email.create_account', compact('token', 'user', 'url', 'name_app', 'domain'))->render();

            Mail::to($user->email)->send(new NewAccount($token, $user, $url, $name_app, $domain));

            \Log::info('resetPassword post event event');


            $notification = new NewUserNotification();
            $notification->notify($user);
        }
    }
}
