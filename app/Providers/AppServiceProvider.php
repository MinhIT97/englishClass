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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // View Share AI Status
        $aiService = app(\App\Services\AI\GeminiService::class);
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
