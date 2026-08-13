<?php

namespace STS\Services\Admin;

use Illuminate\Pagination\LengthAwarePaginator;
use STS\Helpers\TripDescriptionContributionHelper;
use STS\Models\Trip;

class TripExcessContributionListService
{
    public function paginate(int $perPage, int $page): LengthAwarePaginator
    {
        $candidates = Trip::query()
            ->with(['user:id,private_note'])
            ->where('seat_price_cents', '>', 0)
            ->whereNotNull('description')
            ->where('description', '!=', '')
            ->where(function ($query) {
                $query->where('description', 'like', '%$%')
                    ->orWhere('description', 'like', '%luca%');
            })
            ->orderByDesc('id')
            ->get();

        $rows = $candidates
            ->filter(function (Trip $trip) {
                return TripDescriptionContributionHelper::hasPotentialExcessContribution(
                    $trip->description ?? '',
                    (int) $trip->seat_price_cents
                );
            })
            ->map(fn (Trip $trip) => $this->serializeRow($trip))
            ->values();

        $total = $rows->count();
        $items = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url()]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRow(Trip $trip): array
    {
        $seatPriceCents = (int) $trip->seat_price_cents;
        $potentialSeatPriceCents = TripDescriptionContributionHelper::potentialExcessContributionCents(
            $trip->description ?? '',
            $seatPriceCents
        );

        return [
            'id' => $trip->id,
            'from_town' => $trip->from_town,
            'to_town' => $trip->to_town,
            'seat_price_cents' => $seatPriceCents,
            'potential_seat_price_cents' => $potentialSeatPriceCents,
            'has_private_note' => trim((string) ($trip->user?->private_note ?? '')) !== '',
            'user_id' => $trip->user_id,
        ];
    }
}
