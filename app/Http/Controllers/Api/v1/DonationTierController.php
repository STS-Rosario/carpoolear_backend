<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\JsonResponse;
use STS\Http\Controllers\Controller;
use STS\Services\PlatformDonationService;

class DonationTierController extends Controller
{
    public function __construct(private PlatformDonationService $platformDonationService) {}

    public function index(): JsonResponse
    {
        return response()->json($this->platformDonationService->listActiveTiers());
    }
}
