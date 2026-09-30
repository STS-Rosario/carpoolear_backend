<?php

namespace STS\Services;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use STS\Services\IdentityVerificationAttemptClassifier as Classifier;

class IdentityVerificationReportService
{
    private const TABLE = 'identity_verification_events';

    private const TOTAL = 'total';

    public function __construct(private Classifier $classifier) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function build(array $input): array
    {
        $filters = $this->normalizeFilters($input);
        $mercadoPago = $this->mercadoPagoAttempts($filters);
        $manual = $this->manualAttempts($filters);
        $periodSql = $this->periodSql($filters['group_by']);

        $manualByPeriod = $this->aggregate($manual, Classifier::MANUAL_CLASSES, $periodSql);
        $automaticByPeriod = $this->aggregate($mercadoPago, Classifier::AUTOMATIC_CLASSES, $periodSql);

        $series = [];
        foreach ($this->periods($filters) as $period) {
            $series[] = ['period' => $period] + $this->combine(
                $manualByPeriod[$period] ?? $this->zeroCounts(Classifier::MANUAL_CLASSES),
                $automaticByPeriod[$period] ?? $this->zeroCounts(Classifier::AUTOMATIC_CLASSES),
            );
        }

        // Without a period expression the aggregate is a single ungrouped row, always present.
        return [
            'filters' => $filters,
            'totals' => $this->combine(
                $this->aggregate($manual, Classifier::MANUAL_CLASSES)[self::TOTAL],
                $this->aggregate($mercadoPago, Classifier::AUTOMATIC_CLASSES)[self::TOTAL],
            ),
            'series' => $series,
            'funnel' => $this->funnel($mercadoPago, $filters),
        ];
    }

    /**
     * Users whose MP attempt in range ended rejected/error, and whether an approval (any method) followed.
     * Each resolved user is attributed to their earliest approval at or after their first failure in range.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function funnel(Builder $mercadoPago, array $filters): array
    {
        [$from] = $this->range($filters);
        $failures = $this->classifier->funnelFailureClassesSql();

        $failedUsers = DB::query()
            ->fromSub($mercadoPago, 'a')
            ->whereRaw('outcome IN '.$failures)
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->selectRaw('user_id, MIN(outcome_at) AS failed_at');

        $approvalSql = $this->classifier->approvalMethodSql('method', 'name', 'reason', 'related_type');
        $approvals = DB::table(self::TABLE)
            ->selectRaw('id, user_id, created_at, '.$approvalSql.' AS resolved_by')
            ->whereNotNull('user_id')
            ->where('created_at', '>=', $from)
            ->whereRaw($approvalSql.' IS NOT NULL');

        $firstApproval = DB::query()
            ->fromSub($failedUsers, 'f')
            ->joinSub($approvals, 'ap', function ($join) {
                $join->on('ap.user_id', '=', 'f.user_id')->on('ap.created_at', '>=', 'f.failed_at');
            })
            ->selectRaw('f.user_id, ap.resolved_by, ROW_NUMBER() OVER (PARTITION BY f.user_id ORDER BY ap.created_at, ap.id) AS rn');

        $select = ['COUNT(*) AS failed_users', 'COALESCE(SUM(CASE WHEN r.resolved_by IS NOT NULL THEN 1 ELSE 0 END), 0) AS resolved'];
        foreach (Classifier::RESOLUTION_METHODS as $method) {
            $select[] = "COALESCE(SUM(CASE WHEN r.resolved_by = '{$method}' THEN 1 ELSE 0 END), 0) AS by_{$method}";
        }
        $row = (array) DB::query()
            ->fromSub($failedUsers, 'fu')
            ->leftJoinSub($firstApproval, 'r', function ($join) {
                $join->on('r.user_id', '=', 'fu.user_id')->where('r.rn', '=', 1);
            })
            ->selectRaw(implode(', ', $select))
            ->first();

        $failedCount = (int) $row['failed_users'];
        $resolvedCount = (int) $row['resolved'];
        $byMethod = [];
        foreach (Classifier::RESOLUTION_METHODS as $method) {
            $byMethod[$method] = (int) $row['by_'.$method];
        }

        return [
            'failed_users' => $failedCount,
            'resolved' => [
                'count' => $resolvedCount,
                'pct' => $this->percent($resolvedCount, $failedCount),
                'by_method' => $byMethod,
            ],
            'unresolved' => [
                'count' => $failedCount - $resolvedCount,
                'pct' => $this->percent($failedCount - $resolvedCount, $failedCount),
            ],
            'unlinked_failures' => $this->unlinkedFailures($filters),
        ];
    }

    /**
     * MP rejected/error failures in range with no user_id (e.g. missing/expired OAuth state, deleted user).
     *
     * @param  array<string, mixed>  $filters
     */
    private function unlinkedFailures(array $filters): int
    {
        [$from, $to] = $this->range($filters);
        $failures = $this->classifier->funnelFailureClassesSql();

        return DB::table(self::TABLE)
            ->where('method', IdentityVerificationOutcome::METHOD_MERCADO_PAGO)
            ->where('name', IdentityVerificationOutcome::NAME_FAILED)
            ->whereNull('user_id')
            ->whereBetween('created_at', [$from, $to])
            ->whereRaw($this->classifier->mercadoPagoOutcomeSql('name', 'reason').' IN '.$failures)
            ->tap(fn (Builder $q) => $this->restrictToMethod($q, $filters, IdentityVerificationOutcome::METHOD_MERCADO_PAGO))
            ->tap(fn (Builder $q) => $this->applyClientFilters($q, $filters))
            ->count();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalizeFilters(array $input): array
    {
        return [
            'from' => Carbon::parse($input['from'])->toDateString(),
            'to' => Carbon::parse($input['to'])->toDateString(),
            'group_by' => $input['group_by'] ?? 'month',
            'method' => $input['method'] ?? 'all',
            'surface' => $input['surface'] ?? null,
            'platform' => $input['platform'] ?? null,
            'app_version' => $input['app_version'] ?? null,
        ];
    }

    /**
     * @param  array<string, int>  $manual
     * @param  array<string, int>  $automatic
     * @return array<string, mixed>
     */
    private function combine(array $manual, array $automatic): array
    {
        return [
            'attempts' => $manual['attempts'] + $automatic['attempts'],
            'manual' => $this->section($manual),
            'automatic' => $this->section($automatic),
        ];
    }

    /**
     * One row per MP attempt_started in range: user_id, started_at, outcome (automatic class).
     *
     * @param  array<string, mixed>  $filters
     */
    private function mercadoPagoAttempts(array $filters): Builder
    {
        [$from, $to] = $this->range($filters);

        $outcomes = DB::table(self::TABLE)
            ->selectRaw('attempt_id, name, reason, created_at, ROW_NUMBER() OVER (PARTITION BY attempt_id ORDER BY created_at DESC, id DESC) AS rn')
            ->where('method', IdentityVerificationOutcome::METHOD_MERCADO_PAGO)
            ->whereIn('name', [IdentityVerificationOutcome::NAME_SUCCEEDED, IdentityVerificationOutcome::NAME_FAILED])
            ->whereNotNull('attempt_id')
            ->where('created_at', '>=', $from);

        return DB::table(self::TABLE.' as s')
            ->leftJoinSub($outcomes, 'o', function ($join) {
                $join->on('o.attempt_id', '=', 's.attempt_id')->where('o.rn', '=', 1);
            })
            ->where('s.method', IdentityVerificationOutcome::METHOD_MERCADO_PAGO)
            ->where('s.name', IdentityVerificationOutcome::NAME_ATTEMPT_STARTED)
            ->whereBetween('s.created_at', [$from, $to])
            ->tap(fn (Builder $q) => $this->restrictToMethod($q, $filters, IdentityVerificationOutcome::METHOD_MERCADO_PAGO))
            ->tap(fn (Builder $q) => $this->applyClientFilters($q, $filters, 's.'))
            ->selectRaw('s.user_id, s.created_at AS started_at, o.created_at AS outcome_at, '
                .$this->classifier->mercadoPagoOutcomeSql('o.name', 'o.reason').' AS outcome');
    }

    /**
     * Makes $query empty when the method filter excludes $method (keeps the response shape stable with zeros).
     *
     * @param  array<string, mixed>  $filters
     */
    private function restrictToMethod(Builder $query, array $filters, string $method): void
    {
        if ($filters['method'] !== 'all' && $filters['method'] !== $method) {
            $query->whereRaw('1 = 0');
        }
    }

    /**
     * surface / platform / app_version match the event that starts the attempt.
     *
     * @param  array<string, mixed>  $filters
     */
    private function applyClientFilters(Builder $query, array $filters, string $prefix = ''): void
    {
        foreach (['surface', 'platform', 'app_version'] as $field) {
            if ($filters[$field] !== null && $filters[$field] !== '') {
                $query->where($prefix.$field, $filters[$field]);
            }
        }
    }

    /**
     * One row per paid manual request whose first paid-evidence event is in range: user_id, started_at,
     * outcome (manual class of the request's latest state event, as of now).
     *
     * @param  array<string, mixed>  $filters
     */
    private function manualAttempts(array $filters): Builder
    {
        [$from, $to] = $this->range($filters);

        $requests = $this->manualRequestEvents()
            ->selectRaw('related_id, MIN(user_id) AS user_id, MIN(created_at) AS started_at')
            ->whereRaw($this->classifier->manualAttemptEvidenceSql('name', 'reason'))
            ->tap(fn (Builder $q) => $this->restrictToMethod($q, $filters, IdentityVerificationOutcome::METHOD_MANUAL))
            ->tap(fn (Builder $q) => $this->applyClientFilters($q, $filters))
            ->groupBy('related_id')
            ->havingRaw('MIN(created_at) BETWEEN ? AND ?', [$from, $to]);

        $stateSql = $this->classifier->manualStateSql('name', 'reason');
        $states = $this->manualRequestEvents()
            ->selectRaw('related_id, '.$stateSql.' AS state, ROW_NUMBER() OVER (PARTITION BY related_id ORDER BY created_at DESC, id DESC) AS rn')
            ->whereRaw($stateSql.' IS NOT NULL')
            ->where('created_at', '>=', $from);

        return DB::query()
            ->fromSub($requests, 'r')
            ->joinSub($states, 'st', function ($join) {
                $join->on('st.related_id', '=', 'r.related_id')->where('st.rn', '=', 1);
            })
            ->selectRaw('r.user_id, r.started_at, st.state AS outcome');
    }

    private function manualRequestEvents(): Builder
    {
        return DB::table(self::TABLE)
            ->where('method', IdentityVerificationOutcome::METHOD_MANUAL)
            ->where('related_type', Classifier::MANUAL_RELATED_TYPE)
            ->whereNotNull('related_id');
    }

    /**
     * Counts per outcome class, in SQL. Keyed by period when $periodSql is given, else by self::TOTAL.
     *
     * @param  list<string>  $classes
     * @return array<string, array<string, int>>
     */
    private function aggregate(Builder $attempts, array $classes, ?string $periodSql = null): array
    {
        $select = [($periodSql ?? "'".self::TOTAL."'").' AS period', 'COUNT(*) AS attempts'];
        foreach ($classes as $class) {
            $select[] = "COALESCE(SUM(CASE WHEN outcome = '{$class}' THEN 1 ELSE 0 END), 0) AS count_{$class}";
        }

        $query = DB::query()->fromSub($attempts, 'a')->selectRaw(implode(', ', $select));
        if ($periodSql !== null) {
            $query->groupByRaw($periodSql);
        }

        $result = [];
        foreach ($query->get() as $row) {
            $row = (array) $row;
            $counts = ['attempts' => (int) $row['attempts']];
            foreach ($classes as $class) {
                $counts[$class] = (int) $row['count_'.$class];
            }
            $result[(string) $row['period']] = $counts;
        }

        return $result;
    }

    /**
     * @param  list<string>  $classes
     * @return array<string, int>
     */
    private function zeroCounts(array $classes): array
    {
        return ['attempts' => 0] + array_fill_keys($classes, 0);
    }

    /**
     * @param  array<string, int>  $counts  attempts + one count per class
     * @return array<string, mixed>
     */
    private function section(array $counts): array
    {
        $attempts = $counts['attempts'];
        $section = ['attempts' => $attempts];
        foreach ($counts as $class => $count) {
            if ($class === 'attempts') {
                continue;
            }
            $section[$class] = ['count' => $count, 'pct' => $this->percent($count, $attempts)];
        }

        return $section;
    }

    /**
     * MySQL expression bucketing a.started_at: YYYY-MM (month), Monday YYYY-MM-DD (week), YYYY-MM-DD (day).
     */
    private function periodSql(string $groupBy): string
    {
        return match ($groupBy) {
            'week' => "DATE_FORMAT(DATE_SUB(DATE(started_at), INTERVAL WEEKDAY(started_at) DAY), '%Y-%m-%d')",
            'day' => "DATE_FORMAT(started_at, '%Y-%m-%d')",
            default => "DATE_FORMAT(started_at, '%Y-%m')",
        };
    }

    /**
     * Every period between from and to (inclusive), so empty periods are reported with zeros.
     *
     * @param  array<string, mixed>  $filters
     * @return list<string>
     */
    private function periods(array $filters): array
    {
        [$from, $to] = $this->range($filters);
        [$cursor, $step, $format] = match ($filters['group_by']) {
            'week' => [$from->copy()->startOfWeek(Carbon::MONDAY), 'addWeek', 'Y-m-d'],
            'day' => [$from->copy(), 'addDay', 'Y-m-d'],
            default => [$from->copy()->startOfMonth(), 'addMonthNoOverflow', 'Y-m'],
        };

        $periods = [];
        while ($cursor->lessThanOrEqualTo($to)) {
            $periods[] = $cursor->format($format);
            $cursor->{$step}();
        }

        return $periods;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(array $filters): array
    {
        return [Carbon::parse($filters['from'])->startOfDay(), Carbon::parse($filters['to'])->endOfDay()];
    }

    private function percent(int $part, int $whole): float
    {
        return $whole === 0 ? 0.0 : round($part / $whole * 100, 2);
    }
}
