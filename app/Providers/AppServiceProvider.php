<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Blade;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(\App\Providers\TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS when behind a proxy like Cloudflare Tunnel
        if (request()->header('x-forwarded-proto') === 'https' || str_contains(config('app.url'), 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        \Illuminate\Support\Facades\Event::listen(
            \Laravel\Reverb\Events\MessageReceived::class,
            \App\Listeners\ReverbMessageListener::class,
        );

        \Illuminate\Support\Facades\Event::listen(
            \App\Events\StudentRegistered::class,
            \App\Listeners\SendTelegramNotification::class,
        );

        // View Share AI Status
        $aiService = app(\Modules\Speaking\Services\AiSpeakingService::class);
        view()->share('ai_live', $aiService->isLive());

        Gate::define('admin-access', function ($user) {
            return $user->role === 'admin';
        });

        Gate::define('active-user', function ($user) {
            return $user->status === 'active';
        });

        // Register components for ease of use
        Blade::component('layouts.app', 'app-layout');
        Blade::component('layouts.guest', 'guest-layout');
    }
}
