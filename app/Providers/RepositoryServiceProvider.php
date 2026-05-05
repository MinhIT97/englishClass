<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Feedback\FeedbackRepositoryInterface;
use App\Repositories\Feedback\FeedbackRepositoryEloquent;
use App\Repositories\Feedback\FeedbackLogRepositoryInterface;
use App\Repositories\Feedback\FeedbackLogRepositoryEloquent;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(FeedbackRepositoryInterface::class, FeedbackRepositoryEloquent::class);
        $this->app->bind(FeedbackLogRepositoryInterface::class, FeedbackLogRepositoryEloquent::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
