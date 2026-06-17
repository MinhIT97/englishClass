<?php

namespace Modules\TelegramBot\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\TelegramBot\Console\Commands\SendDailyLessonsCommand;
use Modules\TelegramBot\Console\Commands\SetWebhookCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class TelegramBotServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'TelegramBot';

    protected string $nameLower = 'telegrambot';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        SendDailyLessonsCommand::class,
        SetWebhookCommand::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        RouteServiceProvider::class,
    ];

    /**
     * Register bindings, observers and any other boot work.
     */
    public function register(): void
    {
        parent::register();
    }

    /**
     * Hook module-level scheduled tasks here.
     * (Used as a fallback when the app-level scheduler isn't picked up.)
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('tgb:send-daily')
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer();
    }
}
