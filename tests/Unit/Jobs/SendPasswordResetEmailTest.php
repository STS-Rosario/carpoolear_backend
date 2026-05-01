<?php

namespace Tests\Unit\Jobs;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Mockery;
use STS\Jobs\SendPasswordResetEmail;
use STS\Mail\ResetPassword;
use STS\Models\User;
use Tests\TestCase;

class SendPasswordResetEmailTest extends TestCase
{
    public function test_job_exposes_retry_and_timeout_settings(): void
    {
        $user = User::factory()->create();
        $job = new SendPasswordResetEmail($user, 'tok', 'https://app.example/reset', 'Carpoolear', 'example.com');

        $this->assertSame(3, $job->tries);
        $this->assertSame([60, 300, 900], $job->backoff);
        $this->assertSame(30, $job->timeout);
    }

    public function test_handle_sends_reset_password_mailable(): void
    {
        Mail::fake();
        config(['carpoolear.log_emails' => false]);

        $user = User::factory()->create();
        $token = 'plain-reset-token';
        $url = 'https://app.example/reset';
        $nameApp = 'Carpoolear';
        $domain = 'example.com';

        $job = new SendPasswordResetEmail($user, $token, $url, $nameApp, $domain);
        $job->handle();

        Mail::assertSent(ResetPassword::class, function (ResetPassword $mail) use ($user, $token, $url, $nameApp, $domain) {
            return $mail->hasTo($user->email)
                && $mail->token === $token
                && $mail->user->is($user)
                && $mail->url === $url
                && $mail->name_app === $nameApp
                && $mail->domain === $domain;
        });
    }

    public function test_handle_logs_to_email_logs_when_enabled(): void
    {
        Mail::fake();
        config(['carpoolear.log_emails' => true]);

        $user = User::factory()->create();
        $token = 'abcdefghijklmnop';

        $emailChannel = Mockery::mock();
        $emailChannel->shouldReceive('info')
            ->once()
            ->with('PASSWORD_RESET_EMAIL_SENDING', Mockery::on(function (array $context) use ($user) {
                return $context['user_id'] === $user->id
                    && $context['email'] === $user->email
                    && str_starts_with($context['token'], 'abcdefghij')
                    && str_ends_with($context['token'], '...')
                    && $context['url'] === 'https://app.example/r'
                    && $context['name_app'] === 'App';
            }));
        $emailChannel->shouldReceive('info')
            ->once()
            ->with('PASSWORD_RESET_EMAIL_SUCCESS', Mockery::on(function (array $context) use ($user) {
                return $context['user_id'] === $user->id && $context['email'] === $user->email;
            }));

        Log::shouldReceive('info')
            ->once()
            ->ordered()
            ->with('Sending password reset email', Mockery::on(function (array $context) use ($user) {
                return $context['user_id'] === $user->id
                    && $context['email'] === $user->email
                    && isset($context['attempt'], $context['timestamp']);
            }));
        Log::shouldReceive('channel')
            ->once()
            ->ordered()
            ->with('email_logs')
            ->andReturn($emailChannel);
        Log::shouldReceive('info')
            ->once()
            ->ordered()
            ->with('Password reset email sent successfully', Mockery::on(function (array $context) use ($user) {
                return $context['user_id'] === $user->id && $context['email'] === $user->email;
            }));
        Log::shouldReceive('channel')
            ->once()
            ->ordered()
            ->with('email_logs')
            ->andReturn($emailChannel);

        $job = new SendPasswordResetEmail($user, $token, 'https://app.example/r', 'App', 'example.com');
        $job->handle();
    }

    public function test_handle_rethrows_after_logging_when_mail_fails(): void
    {
        config(['carpoolear.log_emails' => false]);

        $user = User::factory()->create();

        Mail::shouldReceive('to')
            ->once()
            ->with($user->email)
            ->andReturnSelf();
        Mail::shouldReceive('send')
            ->once()
            ->with(Mockery::type(ResetPassword::class))
            ->andThrow(new \RuntimeException('mail transport down'));

        Log::shouldReceive('info')->once()->with('Sending password reset email', Mockery::type('array'));
        Log::shouldReceive('error')->once()->with('Failed to send password reset email', Mockery::on(function (array $context) use ($user) {
            return $context['user_id'] === $user->id
                && $context['email'] === $user->email
                && $context['error'] === 'mail transport down';
        }));

        $job = new SendPasswordResetEmail($user, 't', 'https://x', 'App', 'x.com');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('mail transport down');

        $job->handle();
    }

    public function test_failed_logs_permanent_failure_and_optional_email_channel(): void
    {
        config(['carpoolear.log_emails' => true]);

        $user = User::factory()->create();

        $emailChannel = Mockery::mock();
        $emailChannel->shouldReceive('critical')
            ->once()
            ->with('PASSWORD_RESET_EMAIL_PERMANENTLY_FAILED', Mockery::on(function (array $context) use ($user) {
                return $context['user_id'] === $user->id
                    && isset($context['stack_trace'])
                    && strlen((string) $context['stack_trace']) > 0;
            }));

        Log::shouldReceive('error')
            ->once()
            ->with('Password reset email job failed permanently', Mockery::on(function (array $context) use ($user) {
                return $context['user_id'] === $user->id
                    && $context['email'] === $user->email
                    && $context['error'] === 'exhausted';
            }));
        Log::shouldReceive('channel')
            ->once()
            ->with('email_logs')
            ->andReturn($emailChannel);

        $job = new SendPasswordResetEmail($user, 't', 'https://x', 'App', 'x.com');
        $job->failed(new \RuntimeException('exhausted'));
    }
}
