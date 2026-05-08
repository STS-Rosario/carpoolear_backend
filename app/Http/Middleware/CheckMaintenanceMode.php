<?php

namespace STS\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use STS\Models\User;
use STS\Services\Maintenance\MaintenanceStateService;
use Tymon\JWTAuth\Facades\JWTAuth;

class CheckMaintenanceMode
{
    public function __construct(
        protected MaintenanceStateService $maintenanceStateService
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        if ($this->shouldBypass($request)) {
            return $next($request);
        }

        $state = $this->maintenanceStateService->state();

        if (! $state->is_active) {
            return $next($request);
        }

        $user = $this->resolvePassengerApiUser();

        if ($state->mode === 'flexible' && $user && $user->is_admin) {
            return $next($request);
        }

        return response()->json([
            'maintenance' => true,
            'enabled' => true,
            'mode' => $state->mode,
            'message' => $state->message,
            'ends_at' => $state->ends_at?->toIso8601String(),
        ], 503);
    }

    private function shouldBypass(Request $request): bool
    {
        if ($request->is('api/admin') || $request->is('api/admin/*')) {
            return true;
        }

        $literal = [
            'api/config',
            'api/login',
            'api/retoken',
            'api/log',
            'api/reset-password',
            'api/mercadopago/oauth/callback',
            'api/mercadopago/manual-validation-success',
        ];

        foreach ($literal as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        if ($request->is('api/activate') || $request->is('api/activate/*')) {
            return true;
        }

        if ($request->is('api/change-password') || $request->is('api/change-password/*')) {
            return true;
        }

        return false;
    }

    private function resolvePassengerApiUser(): ?User
    {
        $user = null;

        try {
            $authUser = JWTAuth::parseToken()->authenticate();
            if ($authUser instanceof User) {
                $user = $authUser;
            }
        } catch (\Throwable) {
            // Missing or invalid token — acceptable before logged middleware runs.
        }

        if (! $user && auth()->user() instanceof User) {
            $user = auth()->user();
        }

        if (! $user || $user->banned || ! $user->active) {
            return null;
        }

        return $user;
    }
}
