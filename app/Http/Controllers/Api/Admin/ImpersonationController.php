<?php

namespace STS\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Models\AdminImpersonationSession;
use STS\Models\User;
use STS\Services\Admin\ImpersonationService;

class ImpersonationController extends Controller
{
    public function __construct(
        protected ImpersonationService $impersonationService
    ) {}

    public function start(User $user, Request $request): JsonResponse
    {
        /** @var User $admin */
        $admin = auth()->user();
        $result = $this->impersonationService->start($admin, $user);

        return response()->json([
            'handoff_token' => $result['handoff_token'],
            'session_id' => $result['session']->id,
            'target_user_id' => $user->id,
            'expires_at' => $result['session']->expires_at->toIso8601String(),
        ], 201);
    }

    public function stop(AdminImpersonationSession $session): JsonResponse
    {
        /** @var User $admin */
        $admin = auth()->user();
        $this->impersonationService->stopSession($session, $admin);

        return response()->json(['message' => 'impersonation_stopped']);
    }
}
