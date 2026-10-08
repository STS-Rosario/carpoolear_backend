<?php

namespace STS\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Guard;
use STS\Models\User;
use STS\Support\BannedUserAccess;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tymon\JWTAuth\JWTAuth;

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
     * @param  Guard  $auth
     * @return void
     */
    public function __construct(JWTAuth $auth)
    {
        $this->auth = \App::environment('testing') ? null : $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $this->user = $this->resolveUser();

        if ($this->user && $this->user->banned && ! BannedUserAccess::allows($request)) {
            abort(403, 'Access denied');
        }

        return $next($request);
    }

    private function resolveUser(): ?User
    {
        try {
            if ($this->auth && $this->auth->parser()->hasToken()) {
                $user = $this->auth->parseToken()->authenticate();
                if ($user instanceof User) {
                    return $user;
                }
            }
        } catch (\Throwable $e) {
            if ($e instanceof HttpException) {
                throw $e;
            }
            \Log::warning('CheckUserBanned middleware error: '.$e->getMessage());
        }

        $sessionUser = auth()->user();

        return $sessionUser instanceof User ? $sessionUser : null;
    }
}
