<?php

namespace STS\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [
        \Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode::class,
        \Barryvdh\Cors\HandleCors::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            \STS\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \STS\Http\Middleware\VerifyCsrfToken::class,
        ],

        'api' => [
            'throttle:60,1',
        ],

        'logged' => [
            'api.auth',
            'update.connection'
        ]
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'auth'        => \STS\Http\Middleware\Authenticate::class,
        'auth.basic'  => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'can'         => \Illuminate\Foundation\Http\Middleware\Authorize::class,
        'guest'       => \STS\Http\Middleware\RedirectIfAuthenticated::class,
        'throttle'    => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'jwt.auth'    => '\Tymon\JWTAuth\Middleware\GetUserFromToken',
        'jwt.refresh' => '\Tymon\JWTAuth\Middleware\RefreshToken',
        'user.admin'  => 'STS\Http\Middleware\UserAdmin',
        'update.connection' => \STS\Http\Middleware\UpdateConnection::class
    ];
}
