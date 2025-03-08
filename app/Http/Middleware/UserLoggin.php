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
     * @var Guard
     */
    protected $auth;

    protected $user;

    /**
     * Create a new filter instance.
     *
     * @param Guard $auth
     *
     * @return void
     */
    public function __construct(JWTAuth $auth)
    { 
        $this->auth = $auth; 
    }

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    { 
        try {
            $this->user = $this->auth->parseToken()->authenticate();
        } catch (\Exception $e) {
            $this->user = null;
        }

        if ($this->user && !$this->user->banned && $this->user->active) {
            return $next($request);
        } else {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }
    }
}
