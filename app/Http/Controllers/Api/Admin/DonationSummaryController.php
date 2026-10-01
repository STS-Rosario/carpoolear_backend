<?php

namespace STS\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use STS\Http\Controllers\Controller;
use STS\Services\PlatformDonationService;

class DonationSummaryController extends Controller
{
    public function __construct(private PlatformDonationService $platformDonationService) {}

    public function show(): JsonResponse
    {
        return response()->json($this->platformDonationService->summary());
    }
}
