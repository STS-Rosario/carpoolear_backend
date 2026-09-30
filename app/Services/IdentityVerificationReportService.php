<?php

namespace STS\Services;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use STS\Services\IdentityVerificationAttemptClassifier as Classifier;

class IdentityVerificationReportService
{
    private const TABLE = 'identity_verification_events';

    public function __construct(private Classifier $classifier) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function build(array $input): array
    {
        $filters = [
            'from' => Carbon::parse($input['from'])->toDateString(),
            'to' => Carbon::parse($input['to'])->toDateString(),
            'group_by' => $input['group_by'] ?? 'month',
            'method' => $input['method'] ?? 'all',
            'surface' => $input['surface'] ?? null,
            'platform' => $input['platform'] ?? null,
            'app_version' => $input['app_version'] ?? null,
        ];

        $automatic = $this->aggregate($this->mercadoPagoAttempts($filters), Classifier::AUTOMATIC_CLASSES);
        $manual = $this->aggregate($this->manualAttempts($filters), Classifier::MANUAL_CLASSES);

        return [
            'filters' => $filters,
            'totals' => [
                'attempts' => $manual['attempts'] + $automatic['attempts'],
                'manual' => $manual,
                'automatic' => $automatic,
            ],
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
            ->selectRaw('s.user_id, s.created_at AS started_at, o.created_at AS outcome_at, '
                .$this->classifier->mercadoPagoOutcomeSql('o.name', 'o.reason').' AS outcome');
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
     * @param  list<string>  $classes
     * @return array<string, mixed>
     */
    private function aggregate(Builder $attempts, array $classes): array
    {
        $select = ['COUNT(*) AS attempts'];
        foreach ($classes as $class) {
            $select[] = "COALESCE(SUM(CASE WHEN outcome = '{$class}' THEN 1 ELSE 0 END), 0) AS {$class}";
        }

        $row = (array) DB::query()->fromSub($attempts, 'a')->selectRaw(implode(', ', $select))->first();
        $counts = [];
        foreach ($classes as $class) {
            $counts[$class] = (int) $row[$class];
        }

        return $this->section((int) $row['attempts'], $counts);
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, mixed>
     */
    private function section(int $attempts, array $counts): array
    {
        $section = ['attempts' => $attempts];
        foreach ($counts as $class => $count) {
            $section[$class] = ['count' => $count, 'pct' => $this->percent($count, $attempts)];
        }

        return $section;
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
