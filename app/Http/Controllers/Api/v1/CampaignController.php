<?php

namespace STS\Http\Controllers\Api\v1;

use STS\Http\Controllers\Controller;
use STS\Models\Campaign;
use Illuminate\Http\JsonResponse;

class CampaignController extends Controller
{
    /**
     * Display the specified campaign by slug.
     */
    public function showBySlug(string $slug): JsonResponse
    {
        $campaign = Campaign::where('slug', $slug)->first();
        
        if (!$campaign || !$campaign->visible) {
            return response()->json(['message' => 'Campaign not found'], 404);
        }

        $campaign->load(['milestones' => function ($query) {
            $query->orderBy('amount_cents');
        }, 'donations' => function ($query) {
            $query->where('status', 'paid')
                  ->orderBy('created_at', 'desc');
        }, 'rewards' => function ($query) {
            $query->where('is_active', true);
        }]);
        
        $campaign->total_donated = $campaign->total_donated ?? 0;
        
        return response()->json($campaign);
    }
} 