<?php

namespace STS\Support;

use Illuminate\Database\Eloquent\Builder;
use STS\Models\SupportTicket;
use STS\Models\Trip;

class TripExcessContributionSort
{
    /** @var list<string> */
    public const ALLOWED_SORTS = [
        'id',
        'user_name',
        'from_town',
        'to_town',
        'seat_price_cents',
        'potential_seat_price_cents',
        'has_private_note',
        'excess_contribution_support_tickets_count',
        'exceso_contribucion_status',
    ];

    public static function resolveSort(?string $sort): ?string
    {
        return in_array($sort, self::ALLOWED_SORTS, true) ? $sort : null;
    }

    public static function resolveDirection(?string $direction): string
    {
        return strtolower((string) $direction) === 'asc' ? 'asc' : 'desc';
    }

    /**
     * @param  Builder<Trip>  $query
     * @return Builder<Trip>
     */
    public static function applyRequiresActionOnlyFilter(Builder $query): Builder
    {
        return $query->where(function (Builder $statusQuery) {
            $statusQuery
                ->whereNull('exceso_contribucion_status')
                ->orWhereIn(
                    'exceso_contribucion_status',
                    TripExcessContributionStatus::requiresAdminActionStatuses()
                );
        });
    }

    /**
     * @param  Builder<Trip>  $query
     * @return Builder<Trip>
     */
    public static function apply(Builder $query, ?string $sort, ?string $direction): Builder
    {
        $resolvedSort = self::resolveSort($sort);
        $resolvedDirection = self::resolveDirection($direction);

        if ($resolvedSort === null) {
            return self::applyDefaultOrder($query);
        }

        return self::applyExplicitOrder($query, $resolvedSort, $resolvedDirection);
    }

    /**
     * @param  Builder<Trip>  $query
     * @return Builder<Trip>
     */
    private static function applyDefaultOrder(Builder $query): Builder
    {
        $table = 'trips';

        return $query
            ->orderByRaw(self::statusOrderExpression($table, 'asc'))
            ->orderBy("{$table}.id", 'desc');
    }

    /**
     * @param  Builder<Trip>  $query
     * @return Builder<Trip>
     */
    private static function applyExplicitOrder(Builder $query, string $sort, string $direction): Builder
    {
        $table = 'trips';

        switch ($sort) {
            case 'id':
                $query->orderBy("{$table}.id", $direction);
                break;
            case 'user_name':
                self::joinUsers($query);
                $query
                    ->orderByRaw('users.name IS NULL')
                    ->orderBy('users.name', $direction);
                break;
            case 'from_town':
                $query->orderBy("{$table}.from_town", $direction);
                break;
            case 'to_town':
                $query->orderBy("{$table}.to_town", $direction);
                break;
            case 'seat_price_cents':
                $query->orderBy("{$table}.seat_price_cents", $direction);
                break;
            case 'potential_seat_price_cents':
                $query
                    ->orderByRaw("{$table}.description_potential_seat_price_cents IS NULL")
                    ->orderBy("{$table}.description_potential_seat_price_cents", $direction);
                break;
            case 'has_private_note':
                self::joinUsers($query);
                $query->orderByRaw(
                    "(TRIM(COALESCE(users.private_note, '')) <> '') {$direction}"
                );
                break;
            case 'excess_contribution_support_tickets_count':
                self::joinExcessContributionSupportTicketCounts($query);
                $query->orderByRaw(
                    'COALESCE(excess_contribution_support_tickets_count, 0) '.$direction
                );
                break;
            case 'exceso_contribucion_status':
                $query->orderByRaw(self::statusOrderExpression($table, $direction));
                break;
        }

        return $query->orderBy("{$table}.id", 'asc');
    }

    private static function statusOrderExpression(string $table, string $direction): string
    {
        $pendiente = TripExcessContributionStatus::PENDIENTE;
        $enProceso = TripExcessContributionStatus::EN_PROCESO;
        $resuelto = TripExcessContributionStatus::RESUELTO;
        $descartado = TripExcessContributionStatus::DESCARTADO;

        return "CASE
            WHEN {$table}.exceso_contribucion_status IS NULL OR {$table}.exceso_contribucion_status = '' OR {$table}.exceso_contribucion_status = '{$pendiente}' THEN 0
            WHEN {$table}.exceso_contribucion_status = '{$enProceso}' THEN 1
            WHEN {$table}.exceso_contribucion_status = '{$resuelto}' THEN 2
            WHEN {$table}.exceso_contribucion_status = '{$descartado}' THEN 3
            ELSE 0
        END {$direction}";
    }

    /**
     * @param  Builder<Trip>  $query
     */
    private static function joinExcessContributionSupportTicketCounts(Builder $query): void
    {
        if (self::queryHasExcessContributionSupportTicketCountsJoin($query)) {
            return;
        }

        $subquery = SupportTicket::query()
            ->selectRaw('user_id, COUNT(*) as excess_contribution_support_tickets_count')
            ->where('type', 'excess_contribution')
            ->open()
            ->groupBy('user_id');

        $query
            ->leftJoinSub($subquery, 'excess_contribution_support_tickets', function ($join) {
                $join->on(
                    'excess_contribution_support_tickets.user_id',
                    '=',
                    'trips.user_id'
                );
            })
            ->select('trips.*');
    }

    /**
     * @param  Builder<Trip>  $query
     */
    private static function queryHasExcessContributionSupportTicketCountsJoin(Builder $query): bool
    {
        $joins = $query->getQuery()->joins ?? [];

        foreach ($joins as $join) {
            if ($join->table === 'excess_contribution_support_tickets') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Builder<Trip>  $query
     */
    private static function joinUsers(Builder $query): void
    {
        if (self::queryHasUsersJoin($query)) {
            return;
        }

        $query
            ->leftJoin('users', 'users.id', '=', 'trips.user_id')
            ->select('trips.*');
    }

    /**
     * @param  Builder<Trip>  $query
     */
    private static function queryHasUsersJoin(Builder $query): bool
    {
        $joins = $query->getQuery()->joins ?? [];

        foreach ($joins as $join) {
            if ($join->table === 'users') {
                return true;
            }
        }

        return false;
    }
}
