<?php

namespace STS\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use STS\Mail\DeleteAccountRequestNotification;

class SendDeleteAccountRequestEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3; // Maximum number of attempts
    public $backoff = [60, 300, 900]; // Wait times between retries: 1 min, 5 min, 15 min
    public $timeout = 30; // Job timeout in seconds

    protected $adminEmail;
    protected $adminUrl;

    /**
     * Create a new job instance.
     */
    public function __construct(string $adminEmail, string $adminUrl)
    {
        $this->adminEmail = $adminEmail;
        $this->adminUrl = $adminUrl;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $enableEmailLogging = config('carpoolear.log_emails', false);

        try {
            $logData = [
                'admin_email' => $this->adminEmail,
                'attempt' => $this->attempts(),
                'timestamp' => now()->toIso8601String()
            ];

            // Log to regular log
            Log::info('Sending delete account request email', $logData);

            // Log to email_logs channel if enabled
            if ($enableEmailLogging) {
                Log::channel('email_logs')->info('DELETE_ACCOUNT_REQUEST_EMAIL_SENDING', array_merge($logData, [
                    'admin_url' => $this->adminUrl
                ]));
            }

            Mail::to($this->adminEmail)->send(new DeleteAccountRequestNotification(
                $this->adminUrl
            ));

            $successData = [
                'admin_email' => $this->adminEmail,
                'timestamp' => now()->toIso8601String()
            ];

            // Log to regular log
            Log::info('Delete account request email sent successfully', $successData);

            // Log to email_logs channel if enabled
            if ($enableEmailLogging) {
                Log::channel('email_logs')->info('DELETE_ACCOUNT_REQUEST_EMAIL_SUCCESS', $successData);
            }

        } catch (\Exception $e) {
            $errorData = [
                'admin_email' => $this->adminEmail,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'attempt' => $this->attempts(),
                'timestamp' => now()->toIso8601String()
            ];

            // Log to regular log
            Log::error('Failed to send delete account request email', $errorData);

            // Log to email_logs channel if enabled
            if ($enableEmailLogging) {
                Log::channel('email_logs')->error('DELETE_ACCOUNT_REQUEST_EMAIL_FAILED', array_merge($errorData, [
                    'stack_trace' => $e->getTraceAsString()
                ]));
            }

            // Re-throw the exception to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $enableEmailLogging = config('carpoolear.log_emails', false);

        $failureData = [
            'admin_email' => $this->adminEmail,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
            'timestamp' => now()->toIso8601String()
        ];

        // Log to regular log
        Log::error('Delete account request email job failed permanently', $failureData);

        // Log to email_logs channel if enabled
        if ($enableEmailLogging) {
            Log::channel('email_logs')->critical('DELETE_ACCOUNT_REQUEST_EMAIL_PERMANENTLY_FAILED', array_merge($failureData, [
                'stack_trace' => $exception->getTraceAsString()
            ]));
        }
    }
}
