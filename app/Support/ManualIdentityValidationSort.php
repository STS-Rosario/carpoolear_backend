<?php

namespace STS\Support;

use Illuminate\Database\Eloquent\Builder;
use STS\Models\ManualIdentityValidation;
use STS\Models\SupportTicket;

class ManualIdentityValidationSort
{
    /** @var list<string> */
    public const ALLOWED_SORTS = [
        'id',
        'user_name',
        'paid_at',
        'submitted_at',
        'waiting_time',
        'paid',
        'review_status',
        'open_account_verification_tickets_count',
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
     * @param  Builder<ManualIdentityValidation>  $query
     * @return Builder<ManualIdentityValidation>
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
     * @param  Builder<ManualIdentityValidation>  $query
     * @return Builder<ManualIdentityValidation>
     */
    private static function applyDefaultOrder(Builder $query): Builder
    {
        return $query
            ->orderByRaw('CASE WHEN paid = 1 THEN 0 ELSE 1 END')
            ->orderByRaw('CASE WHEN submitted_at IS NOT NULL THEN 0 ELSE 1 END')
            ->orderByRaw("CASE WHEN COALESCE(review_status, '') = 'approved' THEN 1 WHEN COALESCE(review_status, '') = 'rejected' THEN 2 ELSE 0 END")
            ->orderByRaw('COALESCE(submitted_at, paid_at, created_at) ASC')
            ->orderBy('manual_identity_validations.created_at', 'asc');
    }

    /**
     * @param  Builder<ManualIdentityValidation>  $query
     * @return Builder<ManualIdentityValidation>
     */
    private static function applyExplicitOrder(Builder $query, string $sort, string $direction): Builder
    {
        $table = 'manual_identity_validations';

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
            case 'paid_at':
                $query
                    ->orderByRaw("{$table}.paid_at IS NULL")
                    ->orderBy("{$table}.paid_at", $direction);
                break;
            case 'submitted_at':
                $query
                    ->orderByRaw("{$table}.submitted_at IS NULL")
                    ->orderBy("{$table}.submitted_at", $direction);
                break;
            case 'waiting_time':
                $query
                    ->orderByRaw("{$table}.submitted_at IS NULL")
                    ->orderByRaw(
                        "TIMESTAMPDIFF(SECOND, {$table}.submitted_at, COALESCE({$table}.manual_validation_started_at, NOW())) {$direction}"
                    );
                break;
            case 'paid':
                $query->orderBy("{$table}.paid", $direction);
                break;
            case 'review_status':
                $query->orderByRaw(
                    self::reviewStatusOrderExpression($table, $direction)
                );
                break;
            case 'open_account_verification_tickets_count':
                self::joinOpenAccountVerificationTicketCounts($query);
                $query
                    ->orderByRaw(
                        'COALESCE(open_account_verification_tickets_count, 0) '.$direction
                    )
                    ->orderByRaw(
                        self::workflowStateOrderExpression($table, $direction)
                    );
                break;
        }

        return $query->orderBy("{$table}.id", 'asc');
    }

    private static function reviewStatusOrderExpression(string $table, string $direction): string
    {
        return "CASE
            WHEN {$table}.paid = 0 THEN 0
            WHEN {$table}.review_status IS NULL OR {$table}.review_status = '' THEN 1
            WHEN {$table}.review_status IN ('approved', 'approve') THEN 2
            WHEN {$table}.review_status IN ('rejected', 'reject') THEN 3
            ELSE 1
        END {$direction}";
    }

    private static function workflowStateOrderExpression(string $table, string $direction): string
    {
        return "CASE
            WHEN {$table}.paid = 0 THEN 0
            WHEN {$table}.submitted_at IS NULL THEN 1
            WHEN {$table}.review_status IN ('approved', 'approve') THEN 3
            WHEN {$table}.review_status IN ('rejected', 'reject') THEN 4
            ELSE 2
        END {$direction}";
    }

    /**
     * @param  Builder<ManualIdentityValidation>  $query
     */
    private static function joinOpenAccountVerificationTicketCounts(Builder $query): void
    {
        if (self::queryHasOpenAccountVerificationTicketCountsJoin($query)) {
            return;
        }

        $subquery = SupportTicket::query()
            ->selectRaw('user_id, COUNT(*) as open_account_verification_tickets_count')
            ->where('type', 'account_verification')
            ->open()
            ->createdByAdmin()
            ->groupBy('user_id');

        $query
            ->leftJoinSub($subquery, 'open_account_verification_tickets', function ($join) {
                $join->on(
                    'open_account_verification_tickets.user_id',
                    '=',
                    'manual_identity_validations.user_id'
                );
            })
            ->select('manual_identity_validations.*');
    }

    /**
     * @param  Builder<ManualIdentityValidation>  $query
     */
    private static function queryHasOpenAccountVerificationTicketCountsJoin(Builder $query): bool
    {
        $joins = $query->getQuery()->joins ?? [];

        foreach ($joins as $join) {
            if ($join->table === 'open_account_verification_tickets') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Builder<ManualIdentityValidation>  $query
     */
    private static function joinUsers(Builder $query): void
    {
        if (self::queryHasUsersJoin($query)) {
            return;
        }

        $query
            ->leftJoin('users', 'users.id', '=', 'manual_identity_validations.user_id')
            ->select('manual_identity_validations.*');
    }

    /**
     * @param  Builder<ManualIdentityValidation>  $query
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
