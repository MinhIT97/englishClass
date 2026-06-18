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
        // 15-min cadence so a user who picks e.g. 19:00 actually receives
        // their lesson in the 19:00-19:14 window instead of anywhere
        // within the 19:00-20:00 hour. SendDailyLessonsCommand still
        // filters by LearningProfile::isDailySendTime() so we don't
        // double-send.
        $schedule->command('tgb:send-daily')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->onOneServer();
    }
}
