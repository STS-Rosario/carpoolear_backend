<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Services\Admin\ImpersonationService;

class ImpersonationConsumeController extends Controller
{
    public function __construct(
        protected ImpersonationService $impersonationService,
        protected AuthController $authController
    ) {}

    public function consume(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'min:64'],
        ]);

        $result = $this->impersonationService->consume($validated['token']);
        $config = json_decode(
            $this->authController->getConfig($request)->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        return response()->json([
            'token' => $result['token'],
            'config' => $config,
            'impersonation' => [
                'session_id' => $result['session']->id,
                'actor_id' => $result['session']->admin_user_id,
                'target_user_id' => $result['session']->target_user_id,
                'expires_at' => $result['session']->expires_at->toIso8601String(),
            ],
        ]);
    }
}
