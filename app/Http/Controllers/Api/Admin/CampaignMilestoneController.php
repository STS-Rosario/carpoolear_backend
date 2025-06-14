<?php

namespace STS\Http\Controllers\Api\Admin;

use STS\Http\Controllers\Controller;
use STS\Models\Campaign;
use STS\Models\CampaignMilestone;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CampaignMilestoneController extends Controller
{
    /**
     * Display a listing of the campaign milestones.
     */
    public function index(Campaign $campaign): JsonResponse
    {
        $milestones = $campaign->milestones()->orderBy('amount_cents')->get();
        return response()->json($milestones);
    }

    /**
     * Store a newly created milestone in storage.
     */
    public function store(Request $request, Campaign $campaign): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'image_path' => 'nullable|string|max:255',
            'amount_cents' => 'required|integer|min:1',
        ]);

        $milestone = $campaign->milestones()->create($validated);

        return response()->json($milestone, 201);
    }

    /**
     * Display the specified milestone.
     */
    public function show(CampaignMilestone $milestone): JsonResponse
    {
        return response()->json($milestone);
    }

    /**
     * Update the specified milestone in storage.
     */
    public function update(Request $request, CampaignMilestone $milestone): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'image_path' => 'nullable|string|max:255',
            'amount_cents' => 'sometimes|required|integer|min:1',
        ]);

        $milestone->update($validated);

        return response()->json($milestone);
    }

    /**
     * Remove the specified milestone from storage.
     */
    public function destroy(CampaignMilestone $milestone): JsonResponse
    {
        $milestone->delete();
        return response()->json(null, 204);
    }
}
