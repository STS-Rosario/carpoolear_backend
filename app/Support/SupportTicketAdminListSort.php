<?php

namespace STS\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use STS\Models\DonationSubscription;
use STS\Models\SupportTicket;
use STS\Models\User;

class SupportTicketAdminListSort
{
    /** @var list<string> */
    public const ALLOWED_SORTS = [
        'subject',
        'priority',
        'created_at',
        'updated_at',
        'status',
        'assigned_to',
        'type',
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
     * @param  Builder<SupportTicket>  $query
     * @return Builder<SupportTicket>
     */
    public static function apply(Builder $query, ?string $sort, ?string $direction): Builder
    {
        $resolvedSort = self::resolveSort($sort);
        $resolvedDirection = self::resolveDirection($direction);

        $query->orderByRaw(self::clubFirstOrderExpression());

        if ($resolvedSort === null) {
            $query->orderBy('support_tickets.id', 'desc');
        } else {
            self::applyExplicitColumnOrder($query, $resolvedSort, $resolvedDirection);
        }

        return $query->orderBy('support_tickets.id', 'desc');
    }

    /**
     * @param  iterable<int, SupportTicket>  $tickets
     */
    public static function decorateClubActive(iterable $tickets): void
    {
        $ticketList = $tickets instanceof Collection ? $tickets->all() : iterator_to_array($tickets);
        if ($ticketList === []) {
            return;
        }

        $userIds = collect($ticketList)
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($userIds === []) {
            foreach ($ticketList as $ticket) {
                $ticket->club_carpoolear_active = 0;
            }

            return;
        }

        $monthlyDonateUserIds = User::query()
            ->whereIn('id', $userIds)
            ->where('monthly_donate', true)
            ->pluck('id')
            ->all();

        $subscriptionUserIds = DonationSubscription::query()
            ->whereIn('user_id', $userIds)
            ->where('status', 'authorized')
            ->pluck('user_id')
            ->all();

        $activeUserIds = array_fill_keys(
            array_map('intval', array_unique(array_merge($monthlyDonateUserIds, $subscriptionUserIds))),
            true
        );

        foreach ($ticketList as $ticket) {
            $userId = (int) $ticket->user_id;
            $ticket->club_carpoolear_active = isset($activeUserIds[$userId]) ? 1 : 0;
        }
    }

    private static function clubFirstOrderExpression(): string
    {
        return "CASE WHEN support_tickets.status NOT IN ('Resuelto', 'Cerrado') AND (EXISTS (SELECT 1 FROM users WHERE users.id = support_tickets.user_id AND users.monthly_donate = 1) OR EXISTS (SELECT 1 FROM donation_subscriptions WHERE donation_subscriptions.user_id = support_tickets.user_id AND donation_subscriptions.status = 'authorized')) THEN 0 ELSE 1 END";
    }

    /**
     * @param  Builder<SupportTicket>  $query
     */
    private static function applyExplicitColumnOrder(Builder $query, string $sort, string $direction): void
    {
        $table = 'support_tickets';

        switch ($sort) {
            case 'subject':
                $query->orderBy("{$table}.subject", $direction);
                break;
            case 'priority':
                $query->orderByRaw(self::priorityOrderExpression($direction));
                break;
            case 'created_at':
                $query->orderBy("{$table}.created_at", $direction);
                break;
            case 'updated_at':
                $query->orderBy("{$table}.updated_at", $direction);
                break;
            case 'status':
                $query->orderBy("{$table}.status", $direction);
                break;
            case 'assigned_to':
                self::joinAssignees($query);
                $query
                    ->orderByRaw('support_ticket_assignees.name IS NULL')
                    ->orderBy('support_ticket_assignees.name', $direction);
                break;
            case 'type':
                $query->orderBy("{$table}.type", $direction);
                break;
        }
    }

    private static function priorityOrderExpression(string $direction): string
    {
        return "CASE WHEN `support_tickets`.`priority` = 'high' THEN 3 WHEN `support_tickets`.`priority` = 'normal' THEN 2 WHEN `support_tickets`.`priority` = 'low' THEN 1 ELSE 0 END {$direction}";
    }

    /**
     * @param  Builder<SupportTicket>  $query
     */
    private static function joinAssignees(Builder $query): void
    {
        if (self::queryHasAssigneesJoin($query)) {
            return;
        }

        $query
            ->leftJoin(
                'users as support_ticket_assignees',
                'support_ticket_assignees.id',
                '=',
                'support_tickets.assigned_to_user_id'
            )
            ->select('support_tickets.*');
    }

    /**
     * @param  Builder<SupportTicket>  $query
     */
    private static function queryHasAssigneesJoin(Builder $query): bool
    {
        $joins = $query->getQuery()->joins ?? [];

        foreach ($joins as $join) {
            if ($join->table === 'users as support_ticket_assignees') {
                return true;
            }
        }

        return false;
    }
}
