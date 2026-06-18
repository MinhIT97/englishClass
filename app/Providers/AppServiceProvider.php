<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use App\Enums\UserRole;
use App\Http\View\Composers\FeedbackComposer;


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

        // View Composers
        View::composer('layouts.app', FeedbackComposer::class);

        //
        // View Share AI Status
        $aiService = app(\Modules\Speaking\Services\AiSpeakingService::class);
        view()->share('ai_live', $aiService->isLive());

        $this->registerRateLimiters();

        Gate::define('admin-access', function ($user) {
            return $user->role === UserRole::Admin->value;
        });

        Gate::define('active-user', function ($user) {
            return $user->status === 'active';
        });

        // Register components for ease of use
        Blade::component('layouts.app', 'app-layout');
        Blade::component('layouts.guest', 'guest-layout');
    }

    /**
     * SECURITY: Centralised rate limiters used via ->middleware('throttle:name')
     * in route files. Keeping them here makes the limits easy to audit
     * and adjust without grepping every route file.
     */
    protected function registerRateLimiters(): void
    {
        // AI endpoints — these call paid third-party APIs.
        // 20/min/user to bound cost and abuse.
        RateLimiter::for('ai', function (Request $request) {
            return Limit::perMinute(20)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        // Speaking AI is even more expensive (TTS, audio streaming).
        // 10/min/user.
        RateLimiter::for('ai-speaking', function (Request $request) {
            return Limit::perMinute(10)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        // Lesson-quota request submissions — admins review these so
        // a flood of submissions slows them down. 3/hour/user.
        RateLimiter::for('lesson-requests', function (Request $request) {
            return Limit::perHour(3)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        // Webhook callbacks — generous but bounded so a stuck Telegram
        // dispatcher can't fill the queue.
        RateLimiter::for('webhook', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });
    }
}
