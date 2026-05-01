<?php

namespace Tests\Unit\Jobs;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Mockery;
use STS\Jobs\SendDeleteAccountRequestEmail;
use STS\Mail\DeleteAccountRequestNotification;
use Tests\TestCase;

class SendDeleteAccountRequestEmailTest extends TestCase
{
    public function test_job_exposes_retry_and_timeout_settings(): void
    {
        $job = new SendDeleteAccountRequestEmail('ops@example.com', 'https://admin.example/requests');

        $this->assertSame(3, $job->tries);
        $this->assertSame([60, 300, 900], $job->backoff);
        $this->assertSame(30, $job->timeout);
    }

    public function test_handle_sends_delete_account_request_notification(): void
    {
        Mail::fake();
        config(['carpoolear.log_emails' => false]);

        $adminEmail = 'security@example.org';
        $adminUrl = 'https://admin.example/account-deletes/queue';

        $job = new SendDeleteAccountRequestEmail($adminEmail, $adminUrl);
        $job->handle();

        Mail::assertSent(DeleteAccountRequestNotification::class, function (DeleteAccountRequestNotification $mail) use ($adminEmail, $adminUrl) {
            return $mail->hasTo($adminEmail)
                && $mail->adminUrl === $adminUrl;
        });
    }

    public function test_handle_logs_to_email_logs_when_enabled(): void
    {
        Mail::fake();
        config(['carpoolear.log_emails' => true]);

        $adminEmail = 'ops@example.com';
        $adminUrl = 'https://admin.example/pending';

        $emailChannel = Mockery::mock();
        $emailChannel->shouldReceive('info')
            ->once()
            ->with('DELETE_ACCOUNT_REQUEST_EMAIL_SENDING', Mockery::on(function (array $context) use ($adminEmail, $adminUrl) {
                return $context['admin_email'] === $adminEmail
                    && $context['admin_url'] === $adminUrl
                    && isset($context['attempt'], $context['timestamp']);
            }));
        $emailChannel->shouldReceive('info')
            ->once()
            ->with('DELETE_ACCOUNT_REQUEST_EMAIL_SUCCESS', Mockery::on(function (array $context) use ($adminEmail) {
                return $context['admin_email'] === $adminEmail && isset($context['timestamp']);
            }));

        Log::shouldReceive('info')
            ->once()
            ->ordered()
            ->with('Sending delete account request email', Mockery::on(function (array $context) use ($adminEmail) {
                return $context['admin_email'] === $adminEmail && isset($context['attempt'], $context['timestamp']);
            }));
        Log::shouldReceive('channel')
            ->once()
            ->ordered()
            ->with('email_logs')
            ->andReturn($emailChannel);
        Log::shouldReceive('info')
            ->once()
            ->ordered()
            ->with('Delete account request email sent successfully', Mockery::on(function (array $context) use ($adminEmail) {
                return $context['admin_email'] === $adminEmail && isset($context['timestamp']);
            }));
        Log::shouldReceive('channel')
            ->once()
            ->ordered()
            ->with('email_logs')
            ->andReturn($emailChannel);

        $job = new SendDeleteAccountRequestEmail($adminEmail, $adminUrl);
        $job->handle();
    }

    public function test_handle_rethrows_after_logging_when_mail_fails(): void
    {
        config(['carpoolear.log_emails' => false]);

        $adminEmail = 'ops@example.com';

        Mail::shouldReceive('to')
            ->once()
            ->with($adminEmail)
            ->andReturnSelf();
        Mail::shouldReceive('send')
            ->once()
            ->with(Mockery::type(DeleteAccountRequestNotification::class))
            ->andThrow(new \RuntimeException('smtp unavailable'));

        Log::shouldReceive('info')->once()->with('Sending delete account request email', Mockery::type('array'));
        Log::shouldReceive('error')->once()->with('Failed to send delete account request email', Mockery::on(function (array $context) use ($adminEmail) {
            return $context['admin_email'] === $adminEmail
                && $context['error'] === 'smtp unavailable'
                && array_key_exists('error_code', $context)
                && array_key_exists('attempt', $context)
                && array_key_exists('timestamp', $context)
                && $context['timestamp'] !== '';
        }));

        $job = new SendDeleteAccountRequestEmail($adminEmail, 'https://x');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('smtp unavailable');

        $job->handle();
    }

    public function test_handle_when_log_emails_key_is_absent_does_not_touch_email_logs_channel(): void
    {
        Mail::fake();
        $carpoolear = config('carpoolear');
        unset($carpoolear['log_emails']);
        config(['carpoolear' => $carpoolear]);

        Log::shouldReceive('info')->twice();
        Log::shouldReceive('channel')->never();

        $job = new SendDeleteAccountRequestEmail('ops@example.com', 'https://admin.example/pending');
        $job->handle();

        Mail::assertSent(DeleteAccountRequestNotification::class);
    }

    public function test_handle_when_mail_fails_with_email_logging_merges_stack_trace_on_channel_error(): void
    {
        config(['carpoolear.log_emails' => true]);

        $adminEmail = 'ops@example.com';

        Mail::shouldReceive('to')
            ->once()
            ->with($adminEmail)
            ->andReturnSelf();
        Mail::shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('smtp down'));

        $emailChannel = Mockery::mock();
        $emailChannel->shouldReceive('info')
            ->once()
            ->with('DELETE_ACCOUNT_REQUEST_EMAIL_SENDING', Mockery::type('array'));
        $emailChannel->shouldReceive('error')
            ->once()
            ->with('DELETE_ACCOUNT_REQUEST_EMAIL_FAILED', Mockery::on(function (array $context) use ($adminEmail) {
                return $context['admin_email'] === $adminEmail
                    && $context['error'] === 'smtp down'
                    && array_key_exists('stack_trace', $context)
                    && strlen((string) $context['stack_trace']) > 0;
            }));

        Log::shouldReceive('info')
            ->once()
            ->ordered()
            ->with('Sending delete account request email', Mockery::type('array'));
        Log::shouldReceive('channel')
            ->once()
            ->ordered()
            ->with('email_logs')
            ->andReturn($emailChannel);
        Log::shouldReceive('error')
            ->once()
            ->ordered()
            ->with('Failed to send delete account request email', Mockery::type('array'));
        Log::shouldReceive('channel')
            ->once()
            ->ordered()
            ->with('email_logs')
            ->andReturn($emailChannel);

        $job = new SendDeleteAccountRequestEmail($adminEmail, 'https://x');

        $this->expectException(\RuntimeException::class);
        $job->handle();
    }

    public function test_failed_logs_permanent_failure_and_optional_email_channel(): void
    {
        config(['carpoolear.log_emails' => true]);

        $adminEmail = 'ops@example.com';

        $emailChannel = Mockery::mock();
        $emailChannel->shouldReceive('critical')
            ->once()
            ->with('DELETE_ACCOUNT_REQUEST_EMAIL_PERMANENTLY_FAILED', Mockery::on(function (array $context) use ($adminEmail) {
                return $context['admin_email'] === $adminEmail
                    && isset($context['stack_trace'])
                    && strlen((string) $context['stack_trace']) > 0;
            }));

        Log::shouldReceive('error')
            ->once()
            ->with('Delete account request email job failed permanently', Mockery::on(function (array $context) use ($adminEmail) {
                return $context['admin_email'] === $adminEmail
                    && $context['error'] === 'queue exhausted'
                    && array_key_exists('attempts', $context)
                    && array_key_exists('timestamp', $context)
                    && $context['timestamp'] !== '';
            }));
        Log::shouldReceive('channel')
            ->once()
            ->with('email_logs')
            ->andReturn($emailChannel);

        $job = new SendDeleteAccountRequestEmail($adminEmail, 'https://x');
        $job->failed(new \RuntimeException('queue exhausted'));
    }
}
