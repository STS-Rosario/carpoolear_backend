<?php

namespace STS\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use STS\Models\Trip;
use STS\Services\ContributionCheck\ContributionCheckFailedException;
use STS\Services\ContributionCheck\ContributionCheckHttpException;
use STS\Services\ContributionCheck\ContributionCheckResult;
use STS\Services\ContributionCheck\OpenRouterContributionChecker;
use STS\Services\ContributionCheck\TripContributionCheckApplier;
use STS\Services\ContributionCheck\TripMaximumSeatPrice;
use Throwable;

/**
 * Reviews a saved trip's description with an LLM (OpenRouter) and flags it for
 * the admin "exceso de contribución" list. Runs on the queue so trip
 * creation/update never waits on (or fails because of) the LLM.
 */
class CheckTripContributionWithLlm implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /** @var list<int> Seconds to wait before each retry */
    public array $backoff = [10, 60, 300];

    /** Must exceed services.openrouter.timeout so the HTTP timeout fires first. */
    public int $timeout = 90;

    public function __construct(public readonly int $tripId) {}

    public function handle(OpenRouterContributionChecker $checker, TripContributionCheckApplier $applier): void
    {
        $trip = Trip::find($this->tripId);

        if (! $checker->isConfigured()) {
            Log::info('Trip contribution LLM check skipped: OPENROUTER_API_KEY is not set.', [
                'trip_id' => $this->tripId,
            ]);

            if ($trip !== null) {
                $applier->apply($trip, new ContributionCheckResult(null, false, false));
            }

            return;
        }

        if ($trip === null) {
            return;
        }

        $description = trim((string) $trip->description);

        if ($description === '') {
            $applier->apply($trip, new ContributionCheckResult(null, false, false));

            return;
        }

        try {
            $result = $checker->check($description, TripMaximumSeatPrice::centsFor($trip));
        } catch (ContributionCheckHttpException $e) {
            if (! $e->isRetryable()) {
                $this->fail($e);

                return;
            }

            $this->logAttemptFailure($e);

            throw $e;
        } catch (ContributionCheckFailedException $e) {
            $this->logAttemptFailure($e);

            throw $e;
        }

        $applier->apply($trip, $result);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Trip contribution LLM check failed permanently', [
            'trip_id' => $this->tripId,
            'error' => $exception->getMessage(),
            'attempts' => $this->job ? $this->attempts() : null,
        ]);
    }

    private function logAttemptFailure(Throwable $exception): void
    {
        Log::warning('Trip contribution LLM check attempt failed, will retry', [
            'trip_id' => $this->tripId,
            'error' => $exception->getMessage(),
            'attempt' => $this->job ? $this->attempts() : null,
        ]);
    }
}
