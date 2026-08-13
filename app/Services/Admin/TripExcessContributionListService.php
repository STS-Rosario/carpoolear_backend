<?php

namespace STS\Services\Admin;

use Illuminate\Pagination\LengthAwarePaginator;
use STS\Models\SupportTicket;
use STS\Models\Trip;

class TripExcessContributionListService
{
    public function paginate(int $perPage, int $page): LengthAwarePaginator
    {
        $paginator = Trip::query()
            ->with(['user:id,name,private_note'])
            ->where('has_potential_excess_contribution', true)
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        $pageItems = collect($paginator->items());
        $ticketCounts = SupportTicket::countsOpenExcessContributionByUserIds(
            $pageItems->pluck('user_id')->all()
        );

        $items = $pageItems
            ->map(fn (Trip $trip) => $this->serializeRow(
                $trip,
                $ticketCounts[(int) $trip->user_id] ?? 0
            ))
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

    public function findForAdmin(int $tripId): ?Trip
    {
        return Trip::query()
            ->with(['user:id,name,email,private_note'])
            ->where('has_potential_excess_contribution', true)
            ->whereKey($tripId)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeDetail(Trip $trip): array
    {
        $ticketCount = SupportTicket::countsOpenExcessContributionByUserIds([(int) $trip->user_id])[(int) $trip->user_id] ?? 0;

        return array_merge($this->serializeRow($trip, $ticketCount), [
            'description' => $trip->description,
            'trip_date' => $trip->trip_date?->toDateTimeString(),
            'user_email' => $trip->user?->email,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRow(Trip $trip, int $excessContributionSupportTicketsCount = 0): array
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
            'user_name' => $trip->user?->name,
            'exceso_contribucion_status' => $trip->exceso_contribucion_status,
            'excess_contribution_support_tickets_count' => $excessContributionSupportTicketsCount,
        ];
    }
}
