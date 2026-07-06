<?php

namespace STS\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Models\AdminActionLog;
use STS\Models\AdminImpersonationSession;
use STS\Models\User;
use STS\Services\Admin\ImpersonationService;
use STS\Services\AdminActionLogger;

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

        AdminActionLogger::log($admin, AdminActionLog::ACTION_USER_IMPERSONATE_START, $user->id, [
            'session_id' => $result['session']->id,
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

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

        AdminActionLogger::log($admin, AdminActionLog::ACTION_USER_IMPERSONATE_STOP, $session->target_user_id, [
            'session_id' => $session->id,
            'ip' => request()->ip(),
            'user_agent' => (string) request()->userAgent(),
        ]);

        return response()->json(['message' => 'impersonation_stopped']);
    }
}
