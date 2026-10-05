<?php

namespace STS\Services\Admin;

use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DonationLedgerListService
{
    /** @var list<string> */
    private const SORTABLE = ['paid_at', 'amount_cents', 'user_name', 'kind', 'status'];

    /**
     * @param  array{kind?: mixed, status?: mixed, q?: mixed, user_id?: mixed}  $filters
     */
    public function paginate(
        array $filters,
        int $perPage,
        int $page,
        ?string $sort = null,
        ?string $direction = null,
    ): LengthAwarePaginator {
        $query = DB::query()->fromSub($this->unionQuery(), 'donation_ledger');

        if (! empty($filters['kind'])) {
            $query->where('kind', $filters['kind']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }
        if (! empty($filters['q'])) {
            $query->where('user_name', 'like', '%'.$filters['q'].'%');
        }

        $sortKey = in_array($sort, self::SORTABLE, true) ? $sort : 'paid_at';
        $dir = strtolower((string) $direction) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortKey, $dir)->orderBy('id', 'desc');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $items = collect($paginator->items())
            ->map(fn ($row) => [
                'kind' => $row->kind,
                'id' => (int) $row->id,
                'user_id' => $row->user_id !== null ? (int) $row->user_id : null,
                'user_name' => $row->user_name,
                'paid_at' => $row->paid_at,
                'amount_cents' => (int) $row->amount_cents,
                'status' => $row->status,
                'tier_slug' => $row->tier_slug,
                'source' => $row->source,
                'mp_payment_id' => $row->mp_payment_id,
            ])
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

    private function unionQuery(): Builder
    {
        $once = DB::table('donation_payments')
            ->leftJoin('users', 'users.id', '=', 'donation_payments.user_id')
            ->leftJoin('donation_tiers', 'donation_tiers.id', '=', 'donation_payments.donation_tier_id')
            ->select([
                DB::raw("'unica_vez' as kind"),
                'donation_payments.id as id',
                'donation_payments.user_id as user_id',
                'users.name as user_name',
                'donation_payments.paid_at as paid_at',
                'donation_payments.amount_cents as amount_cents',
                'donation_payments.status as status',
                'donation_tiers.slug as tier_slug',
                'donation_payments.source as source',
                'donation_payments.mp_payment_id as mp_payment_id',
            ]);

        $club = DB::table('donation_subscription_charges')
            ->join('donation_subscriptions', 'donation_subscriptions.id', '=', 'donation_subscription_charges.donation_subscription_id')
            ->leftJoin('users', 'users.id', '=', 'donation_subscriptions.user_id')
            ->leftJoin('donation_tiers', 'donation_tiers.id', '=', 'donation_subscriptions.donation_tier_id')
            ->select([
                DB::raw("'club' as kind"),
                'donation_subscription_charges.id as id',
                'donation_subscriptions.user_id as user_id',
                'users.name as user_name',
                'donation_subscription_charges.charged_at as paid_at',
                'donation_subscription_charges.amount_cents as amount_cents',
                'donation_subscription_charges.status as status',
                'donation_tiers.slug as tier_slug',
                'donation_subscriptions.source as source',
                'donation_subscription_charges.mp_payment_id as mp_payment_id',
            ]);

        return $once->unionAll($club);
    }
}
