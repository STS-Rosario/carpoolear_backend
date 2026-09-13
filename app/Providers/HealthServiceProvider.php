<?php

namespace STS\Providers;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class HealthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(DiagnosingHealth::class, function () {
            DB::select('select 1');
        });
    }
}
