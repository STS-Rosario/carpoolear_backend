<?php

namespace STS\Http\Controllers\Api\Admin;

use STS\Http\Controllers\Controller;
use STS\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class CampaignController extends Controller
{
    /**
     * Display a listing of the campaigns.
     */
    public function index(): JsonResponse
    {
        $campaigns = Campaign::with(['milestones', 'donations'])
            ->latest()
            ->get();
        return response()->json($campaigns);
    }

    /**
     * Store a newly created campaign in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'image_path' => 'nullable|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'mp_slug' => 'nullable|string|max:255',
        ]);

        // Generate slug from title if not provided
        $validated['slug'] = Str::slug($request->title);

        $campaign = Campaign::create($validated);

        return response()->json($campaign, 201);
    }

    /**
     * Display the specified campaign.
     */
    public function show(Campaign $campaign): JsonResponse
    {
        return response()->json($campaign->load(['milestones', 'donations']));
    }

    /**
     * Update the specified campaign in storage.
     */
    public function update(Request $request, Campaign $campaign): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'image_path' => 'nullable|string|max:255',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'nullable|date|after:start_date',
            'mp_slug' => 'nullable|string|max:255',
        ]);

        // Update slug if title is being updated
        if ($request->has('title')) {
            $validated['slug'] = Str::slug($request->title);
        }

        $campaign->update($validated);

        return response()->json($campaign);
    }

    /**
     * Remove the specified campaign from storage.
     */
    public function destroy(Campaign $campaign): JsonResponse
    {
        $campaign->delete();
        return response()->json(null, 204);
    }
}
