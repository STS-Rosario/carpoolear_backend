<?php

namespace STS\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Models\DonationTier;
use STS\Services\PlatformDonationService;

class DonationTierController extends Controller
{
    public function __construct(private PlatformDonationService $platformDonationService) {}

    public function index(): JsonResponse
    {
        $tiers = DonationTier::query()->orderBy('sort_order')->get()
            ->map(fn (DonationTier $tier) => $this->platformDonationService->formatTier($tier));

        return response()->json($tiers);
    }

    public function update(Request $request, DonationTier $donationTier): JsonResponse
    {
        $validated = $request->validate([
            'amount_cents' => 'sometimes|integer|min:100',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
            'icon' => 'sometimes|string|max:64',
            'label_key' => 'sometimes|string|max:128',
        ]);

        $donationTier->update($validated);

        return response()->json($this->platformDonationService->formatTier($donationTier->fresh()));
    }

    public function applyInflation(Request $request, DonationTier $donationTier): JsonResponse
    {
        $validated = $request->validate([
            'new_amount_cents' => 'required|integer|min:100',
        ]);

        $adjustment = $this->platformDonationService->applyInflation(
            $donationTier,
            $validated['new_amount_cents'],
            $request->user()->id
        );

        return response()->json($adjustment->load('tier'), 202);
    }
}
