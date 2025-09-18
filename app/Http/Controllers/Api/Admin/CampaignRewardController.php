<?php

namespace STS\Http\Controllers\Api\Admin;

use STS\Http\Controllers\Controller;
use STS\Models\Campaign;
use STS\Models\CampaignReward;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CampaignRewardController extends Controller
{
    public function index(Campaign $campaign)
    {
        $rewards = $campaign->rewards()->withCount(['donations' => function ($query) {
            $query->where('status', 'paid');
        }])->get();

        return response()->json($rewards);
    }

    public function store(Request $request, Campaign $campaign)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'donation_amount_cents' => 'required|integer|min:1',
            'quantity_available' => 'nullable|integer|min:1',
            'is_active' => 'boolean'
        ]);

        $reward = $campaign->rewards()->create($validated);

        return response()->json($reward, 201);
    }

    public function show(Campaign $campaign, CampaignReward $reward)
    {
        if ($reward->campaign_id !== $campaign->id) {
            return response()->json(['error' => 'Reward does not belong to this campaign'], 404);
        }

        return response()->json($reward->loadCount(['donations' => function ($query) {
            $query->where('status', 'paid');
        }]));
    }

    public function update(Request $request, Campaign $campaign, CampaignReward $reward)
    {
        if ($reward->campaign_id !== $campaign->id) {
            return response()->json(['error' => 'Reward does not belong to this campaign'], 404);
        }

        $validated = $request->validate([
            'title' => 'string|max:255',
            'description' => 'string',
            'donation_amount_cents' => 'integer|min:1',
            'quantity_available' => 'nullable|integer|min:1',
            'is_active' => 'boolean'
        ]);

        $reward->update($validated);

        return response()->json($reward);
    }

    public function destroy(Campaign $campaign, CampaignReward $reward)
    {
        if ($reward->campaign_id !== $campaign->id) {
            return response()->json(['error' => 'Reward does not belong to this campaign'], 404);
        }

        $reward->delete();

        return response()->json(null, 204);
    }
} 