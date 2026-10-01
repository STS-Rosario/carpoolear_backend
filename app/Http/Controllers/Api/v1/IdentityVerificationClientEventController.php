<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Services\IdentityVerificationOutcome;

class IdentityVerificationClientEventController extends Controller
{
    public function __construct()
    {
        $this->middleware('logged');
    }

    public function store(Request $request, IdentityVerificationOutcome $outcome): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|in:'.implode(',', IdentityVerificationOutcome::CLIENT_EVENT_NAMES),
            'surface' => 'nullable|string|max:64',
            'platform' => 'nullable|string|max:32',
            'app_version' => 'nullable|string|max:64',
        ]);

        $outcome->emit([
            'user_id' => $request->user()->id,
            'method' => IdentityVerificationOutcome::METHOD_MERCADO_PAGO,
            'name' => $validated['name'],
            'surface' => $validated['surface'] ?? null,
            'platform' => $validated['platform'] ?? null,
            'app_version' => $validated['app_version'] ?? null,
        ]);

        return response()->json(['ok' => true], 201);
    }
}
