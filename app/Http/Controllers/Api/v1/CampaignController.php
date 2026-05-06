<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\JsonResponse;
use STS\Http\Controllers\Controller;
use STS\Models\Campaign;

class CampaignController extends Controller
{
    /**
     * Display the specified campaign by slug.
     */
    public function showBySlug(string $slug): JsonResponse
    {
        $campaign = Campaign::where('slug', $slug)->first();

        if (! $campaign || ! $campaign->visible) {
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

        // Accessor `total_donated` would win in `toArray()` over a set attribute; merge so the
        // response uses the paid total from the eager-loaded `donations` relation.
        $paidTotalCents = (int) ($campaign->donations->sum('amount_cents') ?? 0);

        return response()->json(array_merge($campaign->toArray(), [
            'total_donated' => $paidTotalCents,
        ]));
    }
}
