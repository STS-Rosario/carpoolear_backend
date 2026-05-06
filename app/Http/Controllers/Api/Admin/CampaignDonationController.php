<?php

namespace STS\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Models\Campaign;
use STS\Models\CampaignDonation;

class CampaignDonationController extends Controller
{
    /**
     * Display a listing of the campaign donations.
     */
    public function index(Campaign $campaign): JsonResponse
    {
        $donations = $campaign->donations()
            ->with('user')
            ->latest()
            ->get();

        return response()->json($donations);
    }

    /**
     * Store a newly created donation in storage.
     */
    public function store(Request $request, Campaign $campaign): JsonResponse
    {
        $validated = $request->validate([
            'payment_id' => 'nullable|string|max:255',
            'amount_cents' => 'required|integer|min:1',
            'name' => 'nullable|string|max:255',
            'comment' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id',
            'status' => 'required|in:pending,paid,failed',
        ]);

        $donation = $campaign->donations()->create($validated);

        return response()->json($donation, 201);
    }

    /**
     * Display the specified donation.
     */
    public function show(Campaign $campaign, CampaignDonation $donation): JsonResponse
    {
        $this->assertDonationBelongsToCampaign($campaign, $donation);

        return response()->json($donation->load(['user', 'campaign']));
    }

    /**
     * Update the specified donation in storage.
     */
    public function update(Request $request, Campaign $campaign, CampaignDonation $donation): JsonResponse
    {
        $this->assertDonationBelongsToCampaign($campaign, $donation);

        $validated = $request->validate([
            'payment_id' => 'nullable|string|max:255',
            'amount_cents' => 'sometimes|required|integer|min:1',
            'name' => 'nullable|string|max:255',
            'comment' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id',
            'status' => 'sometimes|required|in:pending,paid,failed',
        ]);

        $donation->update($validated);

        return response()->json($donation->load('user'));
    }

    /**
     * Remove the specified donation from storage.
     */
    public function destroy(Campaign $campaign, CampaignDonation $donation): JsonResponse
    {
        $this->assertDonationBelongsToCampaign($campaign, $donation);

        $donation->delete();

        return response()->json(null, 204);
    }

    private function assertDonationBelongsToCampaign(Campaign $campaign, CampaignDonation $donation): void
    {
        if ((int) $donation->campaign_id !== (int) $campaign->id) {
            abort(404);
        }
    }
}
