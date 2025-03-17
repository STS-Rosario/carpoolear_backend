<?php

namespace STS\Http\Middleware;

use Closure;
use Tymon\JWTAuth\JWTAuth;

class AuthOptional
{
    protected $auth;

    public function __construct(JWTAuth $auth)
    {
        $this->auth = $auth;
    }

    public function handle($request, Closure $next)
    {
        try {
            $user = $this->auth->parseToken()->authenticate();
            if ($user && !$user->banned && $user->active) {
                auth()->setUser($user);
            }
        } catch (\Exception $e) {
            // Token is invalid or not present - that's fine for optional auth
        }

        return $next($request);  // Always continue, regardless of auth status
    }
} 