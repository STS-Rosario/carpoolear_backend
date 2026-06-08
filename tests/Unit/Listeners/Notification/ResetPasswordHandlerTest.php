<?php

namespace Tests\Unit\Listeners\Notification;

use STS\Events\User\Reset as ResetEvent;
use STS\Listeners\Notification\ResetPasswordHandler;
use STS\Models\User;
use STS\Notifications\ResetPasswordNotification;
use STS\Repository\UserRepository;
use STS\Services\Notifications\NotificationServices;
use Tests\TestCase;

class ResetPasswordHandlerTest extends TestCase
{
    public function test_handle_sends_reset_password_notification_when_user_exists(): void
    {
        $user = User::factory()->create();
        $token = 'reset-token-xyz';

        $repo = $this->mock(UserRepository::class);
        $repo->shouldReceive('show')
            ->once()
            ->with($user->id)
            ->andReturn($user);

        $this->mock(NotificationServices::class)
            ->shouldReceive('send')
            ->once()
            ->withArgs(function ($notification, $users, $channel) use ($user, $token) {
                return $notification instanceof ResetPasswordNotification
                    && $notification->getAttribute('token') === $token
                    && $users instanceof User
                    && $users->is($user)
                    && is_string($channel);
            });

        $listener = new ResetPasswordHandler($repo);
        $listener->handle(new ResetEvent($user->id, $token));
    }

    public function test_handle_skips_notification_when_user_is_missing(): void
    {
        $missingId = 999_999_123;

        $repo = $this->mock(UserRepository::class);
        $repo->shouldReceive('show')
            ->once()
            ->with($missingId)
            ->andReturn(null);

        $this->mock(NotificationServices::class)->shouldNotReceive('send');

        $listener = new ResetPasswordHandler($repo);
        $listener->handle(new ResetEvent($missingId, 'token-ignored'));
    }
}
