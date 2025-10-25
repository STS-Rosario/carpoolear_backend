<?php

namespace STS\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use STS\Mail\ResetPassword;
use STS\Models\User;

class SendPasswordResetEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3; // Maximum number of attempts
    public $backoff = [60, 300, 900]; // Wait times between retries: 1 min, 5 min, 15 min
    public $timeout = 30; // Job timeout in seconds

    protected $user;
    protected $token;
    protected $url;
    protected $nameApp;
    protected $domain;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, string $token, string $url, string $nameApp, string $domain)
    {
        $this->user = $user;
        $this->token = $token;
        $this->url = $url;
        $this->nameApp = $nameApp;
        $this->domain = $domain;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $enableEmailLogging = config('mail.log_emails', env('LOG_EMAILS', false));

        try {
            $logData = [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'attempt' => $this->attempts(),
                'timestamp' => now()->toIso8601String()
            ];

            // Log to regular log
            Log::info('Sending password reset email', $logData);

            // Log to email_logs channel if enabled
            if ($enableEmailLogging) {
                Log::channel('email_logs')->info('PASSWORD_RESET_EMAIL_SENDING', array_merge($logData, [
                    'token' => substr($this->token, 0, 10) . '...', // Partial token for debugging
                    'url' => $this->url,
                    'name_app' => $this->nameApp
                ]));
            }

            Mail::to($this->user->email)->send(new ResetPassword(
                $this->token,
                $this->user,
                $this->url,
                $this->nameApp,
                $this->domain
            ));

            $successData = [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'timestamp' => now()->toIso8601String()
            ];

            // Log to regular log
            Log::info('Password reset email sent successfully', $successData);

            // Log to email_logs channel if enabled
            if ($enableEmailLogging) {
                Log::channel('email_logs')->info('PASSWORD_RESET_EMAIL_SUCCESS', $successData);
            }

        } catch (\Exception $e) {
            $errorData = [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'attempt' => $this->attempts(),
                'timestamp' => now()->toIso8601String()
            ];

            // Log to regular log
            Log::error('Failed to send password reset email', $errorData);

            // Log to email_logs channel if enabled
            if ($enableEmailLogging) {
                Log::channel('email_logs')->error('PASSWORD_RESET_EMAIL_FAILED', array_merge($errorData, [
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
        $enableEmailLogging = config('mail.log_emails', env('LOG_EMAILS', false));

        $failureData = [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
            'timestamp' => now()->toIso8601String()
        ];

        // Log to regular log
        Log::error('Password reset email job failed permanently', $failureData);

        // Log to email_logs channel if enabled
        if ($enableEmailLogging) {
            Log::channel('email_logs')->critical('PASSWORD_RESET_EMAIL_PERMANENTLY_FAILED', array_merge($failureData, [
                'stack_trace' => $exception->getTraceAsString()
            ]));
        }
    }
}
