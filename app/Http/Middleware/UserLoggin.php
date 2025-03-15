<?php

namespace STS\Http\Middleware;

use Closure;
use Tymon\JWTAuth\JWTAuth;
use Illuminate\Contracts\Auth\Guard;

class UserLoggin
{
    /**
     * The Guard implementation.
     *
     * @var JWTAuth
     */
    protected $auth;

    protected $user;

    /**
     * Create a new filter instance.
     *
     * @param JWTAuth $auth
     */
    public function __construct(JWTAuth $auth)
    { 
        $this->auth = $auth; 
    }

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @param string|null $mode
     *
     * @return mixed
     */
    public function handle($request, Closure $next, $mode = null)
    { 
        try {
            $this->user = $this->auth->parseToken()->authenticate();
        } catch (\Exception $e) { 
            \Log::info('JWT Exception: ' . get_class($e) . ' - ' . 'Request URL: ' . $request->url()); 
            $this->user = null;
        }

        if ($mode === 'optional') {
            // Allow unauthenticated access, but keep the user available in auth()
            auth()->setUser($this->user);
            return $next($request);
        }

        // Require authentication
        if ($this->user && !$this->user->banned && $this->user->active) {
            auth()->setUser($this->user);
            return $next($request);
        } else {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }
    }
}
