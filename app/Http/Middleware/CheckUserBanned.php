<?php

namespace STS\Http\Middleware;

use Closure;
use Carbon\Carbon;
use Tymon\JWTAuth\JWTAuth;
use Illuminate\Contracts\Auth\Guard;

class CheckUserBanned
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
        if (! \App::environment('testing')) {
            $this->auth = $auth;
        }
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
            // Only try to parse token if it exists
            if ($this->auth->parser()->hasToken()) {
                $this->user = $this->auth->parseToken()->authenticate();
                if ($this->user && $this->user->banned) {
                    abort(403, 'Access denied');
                }
            }
        } catch (\Exception $e) {
            \Log::warning('CheckUserBanned middleware error: ' . $e->getMessage());
        }

        return $next($request);
    }
}
