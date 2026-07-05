<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use STS\Http\Controllers\Controller;
use STS\Services\Admin\ImpersonationService;
use Tymon\JWTAuth\Facades\JWTAuth;

class ImpersonationStopController extends Controller
{
    public function __construct(
        protected ImpersonationService $impersonationService
    ) {}

    public function stop(): JsonResponse
    {
        $payload = JWTAuth::parseToken()->getPayload();

        if (! $payload->get('imp')) {
            abort(403, 'not_impersonating');
        }

        $session = $this->impersonationService->findSessionOrFail((int) $payload->get('session_id'));
        $this->impersonationService->stopSession($session, auth()->user());

        try {
            $token = JWTAuth::getToken();
            if ($token) {
                JWTAuth::invalidate($token);
            }
        } catch (\Exception $e) {
            Log::error('Failed to invalidate impersonation JWT: '.$e->getMessage());
        }

        return response()->json(['message' => 'impersonation_stopped']);
    }
}
