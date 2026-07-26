<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Value;
use STS\Contracts\Logic\Social;
use STS\Models\User;
use STS\Services\Logic\SocialManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Social::class, SocialManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('viewPulse', fn (?User $user) => (bool) ($user?->is_admin));

        Pulse::user(fn (User $user) => [
            'name' => $user->name,
            'extra' => $user->email,
            'avatar' => $user->image ? url('/image/profile/'.$user->image) : null,
        ]);

        Pulse::filter(function (Entry|Value $entry) {
            if (! $entry instanceof Entry) {
                return true;
            }

            return ! preg_match('#/pulse(?:/|$)#', $entry->key);
        });
    }
}
