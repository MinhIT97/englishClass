<?php

namespace Modules\Course\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

class CourseServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Course';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'course';

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
    public function register(): void
    {
        parent::register();

        $this->app->bind(
            \Modules\Course\Repositories\CourseRepository::class,
            \Modules\Course\Repositories\CourseRepositoryEloquent::class
        );
    }
}
