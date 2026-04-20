<?php

namespace Modules\Question\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

class QuestionServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Question';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'question';

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
            \Modules\Question\Repositories\QuestionRepositoryInterface::class,
            \Modules\Question\Repositories\QuestionRepositoryEloquent::class
        );
    }
}
