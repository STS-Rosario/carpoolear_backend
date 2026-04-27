<?php

namespace STS\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use STS\Models\AppConfig;

class LoadConfig
{
    /**
     * Merge persisted {@see AppConfig} rows into the runtime config repository.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $settings = AppConfig::all();
        foreach ($settings as $config) {
            if (isset($config->is_laravel) && $config->is_laravel) {
                Config::set($config->key, $config->value);
            } else {
                Config::set('carpoolear.'.$config->key, $config->value);
            }
        }

        return $next($request);
    }
}
