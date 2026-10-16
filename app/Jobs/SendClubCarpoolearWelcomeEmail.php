<?php

namespace STS\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use STS\Mail\ClubCarpoolearWelcome;
use STS\Models\User;
use STS\Support\UserLocale;

class SendClubCarpoolearWelcomeEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int Maximum number of attempts */
    public $tries;

    /** @var array<int, int> Wait seconds between retries: 1 min, 5 min, 15 min */
    public $backoff;

    /** @var int Job timeout in seconds */
    public $timeout;

    protected $user;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user)
    {
        $this->tries = 3;
        $this->backoff = [60, 300, 900];
        $this->timeout = 30;

        $this->user = $user;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $enableEmailLogging = config('carpoolear.log_emails', false);
        $locale = UserLocale::resolve($this->user, 'arg');
        $mailer = $this->resolveMailer();

        try {
            if ($enableEmailLogging) {
                Log::channel('email_logs')->info('CLUB_CARPOOLEAR_WELCOME_EMAIL_SENDING', [
                    'user_id' => $this->user->id,
                    'email' => $this->user->email,
                    'locale' => $locale,
                    'mailer' => $mailer ?? 'default',
                    'attempt' => $this->attempts(),
                    'timestamp' => now()->toIso8601String(),
                ]);
            }

            $user = $this->user;
            $tripsUrl = $this->tripsUrl();
            $heroImageUrl = $this->heroImageUrl();

            UserLocale::withLocale($locale, function () use ($user, $mailer, $tripsUrl, $heroImageUrl) {
                Mail::mailer($mailer)->to($user->email)->send(new ClubCarpoolearWelcome(
                    tripsUrl: $tripsUrl,
                    heroImageUrl: $heroImageUrl,
                ));
            });

            if ($enableEmailLogging) {
                Log::channel('email_logs')->info('CLUB_CARPOOLEAR_WELCOME_EMAIL_SUCCESS', [
                    'user_id' => $this->user->id,
                    'email' => $this->user->email,
                    'timestamp' => now()->toIso8601String(),
                ]);
            }
        } catch (\Exception $e) {
            $errorData = [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'attempt' => $this->attempts(),
                'timestamp' => now()->toIso8601String(),
            ];

            Log::error('Failed to send Club Carpoolear welcome email', $errorData);

            if ($enableEmailLogging) {
                Log::channel('email_logs')->error('CLUB_CARPOOLEAR_WELCOME_EMAIL_FAILED', array_merge($errorData, [
                    'stack_trace' => $e->getTraceAsString(),
                ]));
            }

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
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
            'timestamp' => now()->toIso8601String(),
        ];

        Log::error('Club Carpoolear welcome email job failed permanently', $failureData);

        if ($enableEmailLogging) {
            Log::channel('email_logs')->critical('CLUB_CARPOOLEAR_WELCOME_EMAIL_PERMANENTLY_FAILED', array_merge($failureData, [
                'stack_trace' => $exception->getTraceAsString(),
            ]));
        }
    }

    /**
     * Resolve the mailer used for the welcome email.
     *
     * Returns null to use the default mailer when none is configured, or when
     * the Resend mailer is selected but no Resend API key is available.
     */
    private function resolveMailer(): ?string
    {
        $mailer = config('carpoolear.club_welcome_mailer');

        if (! is_string($mailer) || $mailer === '') {
            return null;
        }

        if ($mailer === 'resend' && empty(config('services.resend.key'))) {
            return null;
        }

        return $mailer;
    }

    private function tripsUrl(): string
    {
        return rtrim((string) config('carpoolear.frontend_url'), '/').'/app/trips';
    }

    private function heroImageUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/img/carpoolear-grupal-1.jpg';
    }
}
