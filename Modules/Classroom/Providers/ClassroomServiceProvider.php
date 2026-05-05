<?php

namespace Modules\Classroom\Providers;

use Illuminate\Support\Facades\Gate;
use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

class ClassroomServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Classroom';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'classroom';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Define module schedules.
     * 
     * @param $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        parent::register();

        $this->app->bind(
            \Modules\Classroom\Repositories\Contracts\ClassroomRepositoryInterface::class,
            \Modules\Classroom\Repositories\Eloquent\ClassroomRepository::class
        );

        $this->app->bind(
            \Modules\Classroom\Services\Contracts\ClassroomServiceInterface::class,
            \Modules\Classroom\Services\ClassroomService::class
        );
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(
            \Modules\Classroom\Models\Classroom::class,
            \Modules\Classroom\Policies\ClassroomPolicy::class
        );

        Gate::policy(
            \Modules\Classroom\Models\ClassroomPost::class,
            \Modules\Classroom\Policies\ClassroomPostPolicy::class
        );
    }
}
