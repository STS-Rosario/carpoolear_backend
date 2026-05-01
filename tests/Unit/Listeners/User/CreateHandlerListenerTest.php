<?php

namespace Tests\Unit\Listeners\User;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Mockery;
use STS\Events\User\Create as UserCreated;
use STS\Listeners\User\CreateHandler;
use STS\Mail\NewAccount;
use STS\Models\User;
use STS\Notifications\NewUserNotification;
use STS\Repository\UserRepository;
use STS\Services\Notifications\NotificationServices;
use Tests\TestCase;

class CreateHandlerListenerTest extends TestCase
{
    public function test_handle_logs_and_stops_when_user_is_missing(): void
    {
        Mail::fake();
        Log::spy();

        $repo = Mockery::mock(UserRepository::class);
        $repo->shouldReceive('show')->once()->with(404)->andReturn(null);

        (new CreateHandler($repo))->handle(new UserCreated(404));

        Mail::assertNothingSent();
        Log::shouldHaveReceived('info')->with('create handler')->once();
    }

    public function test_handle_does_not_mail_when_user_is_already_active(): void
    {
        $user = User::factory()->create([
            'active' => true,
            'email' => 'active@example.com',
            'activation_token' => 'tok-active',
        ]);

        Mail::fake();
        Log::spy();
        $this->mock(NotificationServices::class)->shouldNotReceive('send');

        $repo = Mockery::mock(UserRepository::class);
        $repo->shouldReceive('show')->once()->with($user->id)->andReturn($user->fresh());

        (new CreateHandler($repo))->handle(new UserCreated($user->id));

        Mail::assertNothingSent();
        Log::shouldHaveReceived('info')->with('create handler')->once();
    }

    public function test_handle_does_not_mail_when_user_has_no_email(): void
    {
        $user = User::factory()->create([
            'active' => false,
            'email' => '',
            'activation_token' => 'tok-no-mail',
        ]);

        Mail::fake();
        Log::spy();
        $this->mock(NotificationServices::class)->shouldNotReceive('send');

        $repo = Mockery::mock(UserRepository::class);
        $repo->shouldReceive('show')->once()->with($user->id)->andReturn($user->fresh());

        (new CreateHandler($repo))->handle(new UserCreated($user->id));

        Mail::assertNothingSent();
        Log::shouldHaveReceived('info')->with('create handler')->once();
    }

    public function test_handle_sends_activation_mail_and_new_user_notification_when_user_is_inactive(): void
    {
        $baseUrl = 'https://activation.test';
        $nameApp = 'TestAppSignup';

        config([
            'app.url' => $baseUrl,
            'carpoolear.name_app' => $nameApp,
        ]);

        $user = User::factory()->create([
            'active' => false,
            'email' => 'inactive@example.com',
            'activation_token' => 'activation-secret-token',
        ]);

        $expectedUrl = $baseUrl.'/app/activate/'.$user->activation_token;

        Mail::fake();
        Log::spy();

        $this->mock(NotificationServices::class)
            ->shouldReceive('send')
            ->once()
            ->withArgs(function ($notification, $users, $channel) use ($user) {
                return $notification instanceof NewUserNotification
                    && $users instanceof User
                    && $users->is($user)
                    && is_string($channel);
            });

        $repo = Mockery::mock(UserRepository::class);
        $repo->shouldReceive('show')->once()->with($user->id)->andReturn($user->fresh());

        (new CreateHandler($repo))->handle(new UserCreated($user->id));

        Mail::assertSent(NewAccount::class, function (NewAccount $mail) use ($user, $expectedUrl, $baseUrl, $nameApp) {
            return $mail->hasTo($user->email)
                && $mail->token === $user->activation_token
                && $mail->url === $expectedUrl
                && $mail->domain === $baseUrl
                && $mail->name_app === $nameApp;
        });

        Log::shouldHaveReceived('info')->with('create handler')->once();
        Log::shouldHaveReceived('info')->with('resetPassword post event event')->once();
    }
}
