<?php 
namespace STS\Http\Middleware;

use Closure;

class LoadConfig {
    

	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next)
	{
        $settings = \STS\Entities\AppConfig::all();
        foreach ($settings as $config) {
            if (isset($config->is_laravel) && $config->is_laravel) {
                \Config::set($config->key, $config->value);
            } else {
                \Config::set("carpoolear." . $config->key, $config->value);
            }
        }
		return $next($request);
	}

}
