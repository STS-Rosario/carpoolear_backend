<?php namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Guard;
use Tymon\JWTAuth\JWTAuth;

class UserAdmin {

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
        $this->user = $this->auth->parseToken()->authenticate();
	}

	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next)
	{
		if ($this->user && $this->user->is_admin)
		{
            return $next($request);
        } else {
            return response()->json('Unauthorized.', 401);
	    }
			
	}

}
