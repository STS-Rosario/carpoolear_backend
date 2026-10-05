<?php

namespace STS\Services\Admin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use STS\Models\User;

class ClubCarpoolearMembersListService
{
    /** @var list<string> */
    private const SORTABLE = ['joined_at', 'last_paid_at', 'total_donated_cents', 'user_name', 'left_at'];

    /**
     * @param  array{status?: mixed, q?: mixed, tier?: mixed}  $filters
     */
    public function paginate(
        array $filters,
        int $perPage,
        int $page,
        ?string $sort = null,
        ?string $direction = null,
    ): LengthAwarePaginator {
        $status = ($filters['status'] ?? 'current') === 'former' ? 'former' : 'current';

        $query = User::query()->select('users.*');
        $this->addAggregates($query);

        if ($status === 'former') {
            $query->whereNotNull('users.club_carpoolear_left_at');
        } else {
            $query->whereNull('users.club_carpoolear_left_at')
                ->where(function (Builder $builder) {
                    $builder->where('users.monthly_donate', true)
                        ->orWhereExists(function ($subscription) {
                            $subscription->selectRaw('1')
                                ->from('donation_subscriptions')
                                ->whereColumn('donation_subscriptions.user_id', 'users.id')
                                ->where('donation_subscriptions.status', 'authorized');
                        });
                });
        }

        if (! empty($filters['q'])) {
            $query->where('users.name', 'like', '%'.$filters['q'].'%');
        }
        if (! empty($filters['tier'])) {
            $query->having('tier_slug', '=', $filters['tier']);
        }

        $sortKey = in_array($sort, self::SORTABLE, true) ? $sort : 'joined_at';
        $column = match ($sortKey) {
            'user_name' => 'users.name',
            'joined_at' => 'users.club_carpoolear_joined_at',
            'left_at' => 'users.club_carpoolear_left_at',
            default => $sortKey,
        };
        $dir = strtolower((string) $direction) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($column, $dir)->orderBy('users.id', 'desc');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $items = collect($paginator->items())
            ->map(fn (User $user) => $this->serialize($user, $status === 'former'))
            ->values()
            ->all();

        return new LengthAwarePaginator(
            $items,
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            ['path' => request()->url()]
        );
    }

    private function addAggregates(Builder $query): void
    {
        $chargeTotal = '(select coalesce(sum(c.amount_cents), 0) from donation_subscription_charges c inner join donation_subscriptions s on s.id = c.donation_subscription_id where s.user_id = users.id and c.status = \'approved\')';
        $onceTotal = '(select coalesce(sum(p.amount_cents), 0) from donation_payments p where p.user_id = users.id and p.status = \'approved\')';
        $lastPaid = '(select max(c.charged_at) from donation_subscription_charges c inner join donation_subscriptions s on s.id = c.donation_subscription_id where s.user_id = users.id and c.status = \'approved\')';
        $tierSlug = '(select t.slug from donation_subscriptions s left join donation_tiers t on t.id = s.donation_tier_id where s.user_id = users.id order by case when s.status = \'authorized\' then 0 else 1 end, s.id desc limit 1)';

        $query->selectRaw($chargeTotal.' + '.$onceTotal.' as total_donated_cents');
        $query->selectRaw($lastPaid.' as last_paid_at');
        $query->selectRaw($tierSlug.' as tier_slug');
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(User $user, bool $includeLeftAt): array
    {
        $row = [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'joined_at' => $user->club_carpoolear_joined_at?->toDateTimeString(),
            'last_paid_at' => $this->formatTimestamp($user->getAttribute('last_paid_at')),
            'tier_slug' => $user->getAttribute('tier_slug'),
            'total_donated_cents' => (int) $user->getAttribute('total_donated_cents'),
        ];

        if ($includeLeftAt) {
            $row['left_at'] = $user->club_carpoolear_left_at?->toDateTimeString();
        }

        return $row;
    }

    private function formatTimestamp(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return \Carbon\Carbon::parse($value)->toDateTimeString();
    }
}
