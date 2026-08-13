<?php

namespace STS\Services\Admin;

use Illuminate\Pagination\LengthAwarePaginator;
use STS\Models\Trip;

class TripExcessContributionListService
{
    public function paginate(int $perPage, int $page): LengthAwarePaginator
    {
        $paginator = Trip::query()
            ->with(['user:id,private_note'])
            ->where('has_potential_excess_contribution', true)
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        $items = collect($paginator->items())
            ->map(fn (Trip $trip) => $this->serializeRow($trip))
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

    /**
     * @return array<string, mixed>
     */
    private function serializeRow(Trip $trip): array
    {
        $seatPriceCents = (int) $trip->seat_price_cents;

        return [
            'id' => $trip->id,
            'from_town' => $trip->from_town,
            'to_town' => $trip->to_town,
            'seat_price_cents' => $seatPriceCents,
            'potential_seat_price_cents' => $trip->description_potential_seat_price_cents,
            'has_private_note' => trim((string) ($trip->user?->private_note ?? '')) !== '',
            'user_id' => $trip->user_id,
        ];
    }
}
