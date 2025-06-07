<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use STS\Http\Middleware\UserLoggin;
use STS\Http\Middleware\AuthOptional;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'update.connection' => \STS\Http\Middleware\UpdateConnection::class,
            'check.userbanned' => \STS\Http\Middleware\CheckUserBanned::class,
            'throttle'    => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            'user.admin'  => \STS\Http\Middleware\UserAdmin::class,
        ]);

        $middleware->group('logged', [
            UserLoggin::class,
            \STS\Http\Middleware\UpdateConnection::class,
            \STS\Http\Middleware\CheckUserBanned::class,
        ]);

        $middleware->group('logged.optional', [
            AuthOptional::class,
            \STS\Http\Middleware\UpdateConnection::class,
            \STS\Http\Middleware\CheckUserBanned::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
