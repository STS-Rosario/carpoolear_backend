<?php

namespace STS\Listeners\User;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;
use STS\Events\User\Create;
use STS\Mail\NewAccount;
use STS\Notifications\NewUserNotification;
use STS\Repository\UserRepository;

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
     *
     * @return void
     */
    public function handle(Create $event)
    {
        $user = $this->userRepo->show($event->id);
        if ($user && $user->email && ! $user->active) {

            $domain = config('app.url');
            $name_app = config('carpoolear.name_app');
            $url = config('app.url').'/app/activate/'.$user->activation_token;
            // $html = view('email.create_account', compact('token', 'user', 'url', 'name_app', 'domain'))->render();
            $token = $user->activation_token;
            Mail::to($user->email)->send(new NewAccount($token, $user, $url, $name_app, $domain));

            $notification = new NewUserNotification;
            $notification->notify($user);
        }
    }
}
