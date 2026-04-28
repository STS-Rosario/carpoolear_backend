<?php

namespace Tests\Unit\Listeners\Notification;

use Mockery;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use STS\Events\User\Reset as ResetEvent;
use STS\Listeners\Notification\ResetPasswordHandler;
use STS\Models\User;
use STS\Repository\UserRepository;
use Tests\TestCase;

class ResetPasswordHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[RunInSeparateProcess]

    #[PreserveGlobalState(false)]
    public function test_handle_sends_reset_password_notification_when_user_exists(): void
    {
        $user = User::factory()->create();
        $token = 'reset-token-xyz';

        $repo = Mockery::mock(UserRepository::class);
        $repo->shouldReceive('show')
            ->once()
            ->with($user->id)
            ->andReturn($user);

        $notificationMock = Mockery::mock('overload:STS\\Notifications\\ResetPasswordNotification');
        $notificationMock->shouldReceive('setAttribute')
            ->once()
            ->with('token', $token);
        $notificationMock->shouldReceive('notify')
            ->once()
            ->with($user);

        $listener = new ResetPasswordHandler($repo);
        $listener->handle(new ResetEvent($user->id, $token));

        $this->assertTrue(true);
    }

    #[RunInSeparateProcess]

    #[PreserveGlobalState(false)]
    public function test_handle_skips_notification_when_user_is_missing(): void
    {
        $missingId = 999_999_123;

        $repo = Mockery::mock(UserRepository::class);
        $repo->shouldReceive('show')
            ->once()
            ->with($missingId)
            ->andReturn(null);

        $notificationMock = Mockery::mock('overload:STS\\Notifications\\ResetPasswordNotification');
        $notificationMock->shouldNotReceive('setAttribute');
        $notificationMock->shouldNotReceive('notify');

        $listener = new ResetPasswordHandler($repo);
        $listener->handle(new ResetEvent($missingId, 'token-ignored'));

        $this->assertTrue(true);
    }
}
