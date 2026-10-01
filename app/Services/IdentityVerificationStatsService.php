<?php

namespace STS\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use STS\Models\IdentityVerificationEvent;

class IdentityVerificationStatsService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summarize(array $filters): array
    {
        $from = Carbon::parse($filters['from'])->startOfDay();
        $to = Carbon::parse($filters['to'])->endOfDay();
        $method = $filters['method'];
        $attemptName = $method === IdentityVerificationOutcome::METHOD_MANUAL
            ? IdentityVerificationOutcome::NAME_DOCS_SUBMITTED
            : IdentityVerificationOutcome::NAME_ATTEMPT_STARTED;

        $query = IdentityVerificationEvent::query()
            ->where('method', $method)
            ->whereBetween('created_at', [$from, $to]);

        foreach (['surface', 'platform', 'app_version'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        $events = $query->get(['user_id', 'name', 'reason', 'attempt_id', 'created_at']);
        $attempts = $events->where('name', $attemptName);
        $successes = $events->where('name', IdentityVerificationOutcome::NAME_SUCCEEDED);
        $failures = $events->where('name', IdentityVerificationOutcome::NAME_FAILED);

        $attemptCount = $attempts->count();
        $successCount = $successes->count();
        $failureCount = $failures->count();
        $abandoned = $this->abandonedCount($attempts, $events, $method);
        $uniqueStarted = $attempts->pluck('user_id')->filter()->unique()->count();
        $uniqueSucceeded = $successes->pluck('user_id')->filter()->unique()->count();

        $payload = [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'method' => $method,
            'attempts' => $attemptCount,
            'successes' => $successCount,
            'failures' => $failureCount,
            'abandoned' => $abandoned,
            'unique_users_started' => $uniqueStarted,
            'unique_users_succeeded' => $uniqueSucceeded,
            'retry_rate' => $uniqueStarted === 0 ? 0.0 : round($attemptCount / $uniqueStarted, 2),
            'success_rate' => $this->percent($successCount, $attemptCount),
            'failure_rate' => $this->percent($failureCount, $attemptCount),
            'abandonment_rate' => $this->percent($abandoned, $attemptCount),
            'failures_by_reason' => $this->failuresByReason($failures, $attemptCount, $failureCount),
        ];

        if (! empty($filters['group_by'])) {
            $payload['series'] = $this->series($attempts, $successes, $failures, $filters['group_by']);
        }

        return $payload;
    }

    /**
     * @param  Collection<int, IdentityVerificationEvent>  $attempts
     * @param  Collection<int, IdentityVerificationEvent>  $events
     */
    private function abandonedCount(Collection $attempts, Collection $events, string $method): int
    {
        if ($method !== IdentityVerificationOutcome::METHOD_MERCADO_PAGO) {
            return 0;
        }

        $outcomeIds = $events
            ->whereIn('name', [
                IdentityVerificationOutcome::NAME_SUCCEEDED,
                IdentityVerificationOutcome::NAME_FAILED,
            ])
            ->pluck('attempt_id')
            ->filter()
            ->unique();

        return $attempts
            ->filter(function (IdentityVerificationEvent $event) use ($outcomeIds): bool {
                $attemptId = $event->attempt_id;
                if ($attemptId === null || $attemptId === '') {
                    return true;
                }

                return ! $outcomeIds->contains($attemptId);
            })
            ->count();
    }

    /**
     * @param  Collection<int, IdentityVerificationEvent>  $failures
     * @return list<array{reason: string, count: int, percent_of_attempts: float, percent_of_failures: float}>
     */
    private function failuresByReason(Collection $failures, int $attemptCount, int $failureCount): array
    {
        return $failures
            ->groupBy(fn (IdentityVerificationEvent $event) => $event->reason ?: 'unknown')
            ->map(function (Collection $group, string $reason) use ($attemptCount, $failureCount): array {
                $count = $group->count();

                return [
                    'reason' => $reason,
                    'count' => $count,
                    'percent_of_attempts' => $this->percent($count, $attemptCount),
                    'percent_of_failures' => $this->percent($count, $failureCount),
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, IdentityVerificationEvent>  $attempts
     * @param  Collection<int, IdentityVerificationEvent>  $successes
     * @param  Collection<int, IdentityVerificationEvent>  $failures
     * @return list<array{period: string, attempts: int, successes: int, failures: int}>
     */
    private function series(Collection $attempts, Collection $successes, Collection $failures, string $groupBy): array
    {
        $bucket = fn (IdentityVerificationEvent $event): string => $groupBy === 'week'
            ? Carbon::parse($event->created_at)->startOfWeek()->toDateString()
            : Carbon::parse($event->created_at)->toDateString();

        $periods = $attempts->map($bucket)
            ->merge($successes->map($bucket))
            ->merge($failures->map($bucket))
            ->unique()
            ->sort()
            ->values();

        return $periods->map(function (string $period) use ($attempts, $successes, $failures, $bucket): array {
            return [
                'period' => $period,
                'attempts' => $attempts->filter(fn ($event) => $bucket($event) === $period)->count(),
                'successes' => $successes->filter(fn ($event) => $bucket($event) === $period)->count(),
                'failures' => $failures->filter(fn ($event) => $bucket($event) === $period)->count(),
            ];
        })->all();
    }

    private function percent(int $part, int $whole): float
    {
        if ($whole === 0) {
            return 0.0;
        }

        return round($part / $whole * 100, 2);
    }
}
