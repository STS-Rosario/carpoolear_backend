<?php

namespace Tests\Unit\Jobs;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Mockery;
use STS\Jobs\SendClubCarpoolearWelcomeEmail;
use STS\Mail\ClubCarpoolearWelcome;
use STS\Models\User;
use Tests\TestCase;

class SendClubCarpoolearWelcomeEmailTest extends TestCase
{
    public function test_job_exposes_retry_and_timeout_settings(): void
    {
        $user = User::factory()->create();
        $job = new SendClubCarpoolearWelcomeEmail($user);

        $this->assertSame(3, $job->tries);
        $this->assertSame([60, 300, 900], $job->backoff);
        $this->assertSame(30, $job->timeout);
    }

    public function test_handle_sends_welcome_mailable_to_user(): void
    {
        Mail::fake();
        config([
            'carpoolear.log_emails' => false,
            'carpoolear.frontend_url' => 'https://app.example',
            'app.url' => 'https://api.example',
        ]);

        $user = User::factory()->create();

        $job = new SendClubCarpoolearWelcomeEmail($user);
        $job->handle();

        Mail::assertSent(ClubCarpoolearWelcome::class, function (ClubCarpoolearWelcome $mail) use ($user) {
            return $mail->hasTo($user->email)
                && $mail->tripsUrl === 'https://app.example/app/trips'
                && $mail->heroImageUrl === 'https://api.example/img/carpoolear-grupal-1.jpg';
        });
    }

    public function test_handle_sends_welcome_mailable_to_recipient_override(): void
    {
        Mail::fake();
        config([
            'carpoolear.log_emails' => false,
            'carpoolear.club_welcome_mailer' => null,
        ]);

        $user = User::factory()->create();

        (new SendClubCarpoolearWelcomeEmail($user, 'override@example.com'))->handle();

        Mail::assertSent(ClubCarpoolearWelcome::class, function (ClubCarpoolearWelcome $mail) {
            return $mail->hasTo('override@example.com');
        });
    }

    public function test_handle_uses_configured_resend_mailer_when_key_present(): void
    {
        config([
            'carpoolear.log_emails' => false,
            'carpoolear.club_welcome_mailer' => 'resend',
            'services.resend.key' => 're_test_key',
        ]);

        $user = User::factory()->create();

        Mail::shouldReceive('mailer')->once()->with('resend')->andReturnSelf();
        Mail::shouldReceive('to')->once()->with($user->email)->andReturnSelf();
        Mail::shouldReceive('send')->once()->with(Mockery::type(ClubCarpoolearWelcome::class));

        (new SendClubCarpoolearWelcomeEmail($user))->handle();
    }

    public function test_handle_falls_back_to_default_mailer_when_resend_key_missing(): void
    {
        config([
            'carpoolear.log_emails' => false,
            'carpoolear.club_welcome_mailer' => 'resend',
            'services.resend.key' => null,
        ]);

        $user = User::factory()->create();

        Mail::shouldReceive('mailer')->once()->with(null)->andReturnSelf();
        Mail::shouldReceive('to')->once()->with($user->email)->andReturnSelf();
        Mail::shouldReceive('send')->once()->with(Mockery::type(ClubCarpoolearWelcome::class));

        (new SendClubCarpoolearWelcomeEmail($user))->handle();
    }

    public function test_handle_uses_default_mailer_when_not_configured(): void
    {
        config([
            'carpoolear.log_emails' => false,
            'carpoolear.club_welcome_mailer' => null,
        ]);

        $user = User::factory()->create();

        Mail::shouldReceive('mailer')->once()->with(null)->andReturnSelf();
        Mail::shouldReceive('to')->once()->with($user->email)->andReturnSelf();
        Mail::shouldReceive('send')->once()->with(Mockery::type(ClubCarpoolearWelcome::class));

        (new SendClubCarpoolearWelcomeEmail($user))->handle();
    }

    public function test_handle_rethrows_after_logging_when_mail_fails(): void
    {
        config([
            'carpoolear.log_emails' => false,
            'carpoolear.club_welcome_mailer' => null,
        ]);

        $user = User::factory()->create();

        Mail::shouldReceive('mailer')->once()->with(null)->andReturnSelf();
        Mail::shouldReceive('to')->once()->with($user->email)->andReturnSelf();
        Mail::shouldReceive('send')->once()->with(Mockery::type(ClubCarpoolearWelcome::class))
            ->andThrow(new \RuntimeException('resend transport down'));

        Log::shouldReceive('error')->once()->with('Failed to send Club Carpoolear welcome email', Mockery::on(function (array $context) use ($user) {
            return $context['user_id'] === $user->id
                && $context['email'] === $user->email
                && $context['error'] === 'resend transport down'
                && array_key_exists('error_code', $context)
                && array_key_exists('attempt', $context)
                && array_key_exists('timestamp', $context);
        }));

        $job = new SendClubCarpoolearWelcomeEmail($user);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('resend transport down');

        $job->handle();
    }

    public function test_handle_logs_to_email_logs_channel_when_enabled(): void
    {
        Mail::fake();
        config([
            'carpoolear.log_emails' => true,
            'carpoolear.club_welcome_mailer' => null,
        ]);

        $user = User::factory()->create();

        $emailChannel = Mockery::mock();
        $emailChannel->shouldReceive('info')
            ->once()
            ->with('CLUB_CARPOOLEAR_WELCOME_EMAIL_SENDING', Mockery::on(function (array $context) use ($user) {
                return $context['user_id'] === $user->id
                    && $context['email'] === $user->email
                    && array_key_exists('locale', $context)
                    && $context['mailer'] === 'default'
                    && array_key_exists('attempt', $context);
            }));
        $emailChannel->shouldReceive('info')
            ->once()
            ->with('CLUB_CARPOOLEAR_WELCOME_EMAIL_SUCCESS', Mockery::on(function (array $context) use ($user) {
                return $context['user_id'] === $user->id
                    && $context['email'] === $user->email
                    && array_key_exists('timestamp', $context);
            }));

        Log::shouldReceive('channel')->twice()->with('email_logs')->andReturn($emailChannel);

        (new SendClubCarpoolearWelcomeEmail($user))->handle();
    }

    public function test_failed_logs_permanent_failure_and_optional_email_channel(): void
    {
        config(['carpoolear.log_emails' => true]);

        $user = User::factory()->create();

        $emailChannel = Mockery::mock();
        $emailChannel->shouldReceive('critical')
            ->once()
            ->with('CLUB_CARPOOLEAR_WELCOME_EMAIL_PERMANENTLY_FAILED', Mockery::on(function (array $context) use ($user) {
                return $context['user_id'] === $user->id
                    && $context['email'] === $user->email
                    && $context['error'] === 'exhausted'
                    && array_key_exists('stack_trace', $context);
            }));

        Log::shouldReceive('error')->once()->with('Club Carpoolear welcome email job failed permanently', Mockery::on(function (array $context) use ($user) {
            return $context['user_id'] === $user->id
                && $context['email'] === $user->email
                && $context['error'] === 'exhausted'
                && array_key_exists('attempts', $context);
        }));
        Log::shouldReceive('channel')->once()->with('email_logs')->andReturn($emailChannel);

        (new SendClubCarpoolearWelcomeEmail($user))->failed(new \RuntimeException('exhausted'));
    }
}
