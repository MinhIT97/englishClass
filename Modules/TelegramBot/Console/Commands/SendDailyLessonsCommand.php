<?php

namespace Modules\TelegramBot\Console\Commands;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\TelegramBot\Models\LearningProfile;
use Modules\TelegramBot\Models\UserTelegramLink;
use Modules\TelegramBot\Services\TelegramLearningService;

class SendDailyLessonsCommand extends Command
{
    protected $signature = 'tgb:send-daily
                            {--user= : Send only for this user id}
                            {--force : Send even if a lesson was already sent today}
                            {--dry-run : Show what would be sent without actually sending}';

    protected $description = 'Generate and send daily Telegram lessons to all eligible users.';

    public function handle(TelegramLearningService $service): int
    {
        $now = Carbon::now();
        $currentHour = (int) $now->format('G');

        $this->info("Running tgb:send-daily at {$now->toDateTimeString()} (hour={$currentHour})");

        $query = LearningProfile::query()
            ->where('is_paused', false)
            ->where('onboarded_at', '!=', null);

        if ($userId = $this->option('user')) {
            $query->where('user_id', (int) $userId);
        } else {
            $query->where('daily_send_hour', $currentHour);
        }

        $profiles = $query->get();
        $this->info("Found {$profiles->count()} eligible profile(s).");

        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($profiles as $profile) {
            $user = User::find($profile->user_id);
            if (! $user) {
                $skipped++;
                continue;
            }

            $link = UserTelegramLink::query()->where('user_id', $user->id)->first();
            if (! $link) {
                $skipped++;
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("  [dry-run] Would send to user #{$user->id} ({$user->email})");
                $sent++;
                continue;
            }

            try {
                $ok = $service->sendDailyLesson($user, $now);
                if ($ok) {
                    $sent++;
                    $this->line("  ✓ Sent to user #{$user->id}");
                } else {
                    $skipped++;
                    $this->warn("  - Skipped user #{$user->id} (already sent or no topic)");
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->error("  ✗ Failed for user #{$user->id}: " . $e->getMessage());
                \Log::error('[tgb:send-daily] exception', [
                    'user_id' => $user->id,
                    'msg' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done. sent={$sent} skipped={$skipped} failed={$failed}");
        return self::SUCCESS;
    }
}
