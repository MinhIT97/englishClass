<?php

namespace Modules\Speaking\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

class SpeakingServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Speaking';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'speaking';

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
            \Modules\Speaking\Repositories\SpeakingSessionRepositoryInterface::class,
            \Modules\Speaking\Repositories\SpeakingSessionRepositoryEloquent::class
        );

        $this->app->bind(
            \Modules\Speaking\Repositories\TranscriptRepositoryInterface::class,
            \Modules\Speaking\Repositories\TranscriptRepositoryEloquent::class
        );
    }
}
