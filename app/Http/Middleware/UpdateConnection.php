<?php

namespace STS\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Auth\Guard;
use Tymon\JWTAuth\JWTAuth;

class UpdateConnection
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
     * @param  Guard  $auth
     * @return void
     */
    public function __construct(JWTAuth $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        try {
            // Only try to parse token if auth is available and token exists
            if ($this->auth && $this->auth->parser()->hasToken()) {
                $this->user = $this->auth->parseToken()->authenticate();
                if ($this->user) {
                    $this->user->last_connection = Carbon::now();
                    $this->user->save();
                }
            }
        } catch (\Exception $e) {
            \Log::warning('UpdateConnection middleware error: '.$e->getMessage());
        }

        return $next($request);
    }
}
