<?php

namespace STS\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\JWTAuth;

class BlockImpersonationDestructiveActions
{
    /**
     * @var list<string>
     */
    private const BLOCKED_ROUTE_SIGNATURES = [
        'POST api/users/delete-account',
        'POST api/users/delete-account-request',
        'POST api/change-password',
        'GET api/users/mercadopago-oauth-url',
        'POST api/users/manual-identity-validation/preference',
        'POST api/users/manual-identity-validation/qr-order',
    ];

    public function __construct(protected JWTAuth $auth) {}

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            $payload = $this->auth->parseToken()->getPayload();
            if (! $payload->get('imp')) {
                return $next($request);
            }
        } catch (\Exception $e) {
            return $next($request);
        }

        if ($this->isBlockedRoute($request)) {
            return response()->json(['message' => 'impersonation_action_forbidden'], 403);
        }

        return $next($request);
    }

    private function isBlockedRoute(Request $request): bool
    {
        $signature = strtoupper($request->method()).' '.ltrim($request->path(), '/');

        if (in_array($signature, self::BLOCKED_ROUTE_SIGNATURES, true)) {
            return true;
        }

        if (preg_match('#^POST API/TRIPS/\D+/REQUESTS/\D+/PAY$#', strtoupper($signature)) === 1) {
            return true;
        }

        if (str_starts_with($signature, 'POST API/CHANGE-PASSWORD')) {
            return true;
        }

        return false;
    }
}
