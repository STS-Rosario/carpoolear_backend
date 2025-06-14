<?php

namespace STS\Http\Controllers\Api\Admin;

use STS\Http\Controllers\Controller;
use STS\Models\Campaign;
use STS\Models\CampaignDonation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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
    public function show(CampaignDonation $donation): JsonResponse
    {
        return response()->json($donation->load(['user', 'campaign']));
    }

    /**
     * Update the specified donation in storage.
     */
    public function update(Request $request, CampaignDonation $donation): JsonResponse
    {
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
    public function destroy(CampaignDonation $donation): JsonResponse
    {
        $donation->delete();
        return response()->json(null, 204);
    }
}
