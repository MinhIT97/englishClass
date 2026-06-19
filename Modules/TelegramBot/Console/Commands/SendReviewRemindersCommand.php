<?php

namespace Modules\TelegramBot\Console\Commands;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\TelegramBot\Models\LearningProfile;
use Modules\TelegramBot\Models\ReadingPassageReview;
use Modules\TelegramBot\Models\ReviewSchedule;
use Modules\TelegramBot\Models\UserTelegramLink;

/**
 * Sends a one-off Telegram nudge to users who have review cards due.
 *
 * We don't try to re-implement the per-day send logic from
 * SendDailyLessonsCommand: that command already gates on
 * `isDailySendTime()`. Here we just check whether the user has ANY due
 * reviews and ping them with a single CTA message.
 *
 * The command is cheap (one COUNT query per user) and is scheduled to
 * run hourly so the timing precision is "within an hour of the user's
 * local send hour" — close enough for an opt-in reminder and well within
 * Telegram rate limits.
 */
class SendReviewRemindersCommand extends Command
{
    protected $signature = 'tgb:send-review-reminders
                            {--user= : Send only for this user id}
                            {--force : Send even if a reminder was already sent today}
                            {--dry-run : Show what would be sent without actually sending}';

    protected $description = 'Send a Telegram reminder to users who have vocab / reading reviews due.';

    public function handle(): int
    {
        $now = Carbon::now('UTC');
        $this->info("Running tgb:send-review-reminders at {$now->toDateTimeString()} UTC");

        $query = LearningProfile::query()
            ->where('is_paused', false)
            ->whereNotNull('onboarded_at');

        if ($userId = $this->option('user')) {
            $query->where('user_id', (int) $userId);
        }

        $profiles = $query->get();
        $this->info("Found {$profiles->count()} eligible profile(s).");

        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($profiles as $profile) {
            $localNow = $profile->localDateTime($now);
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

            // Throttle: one reminder per user per local day. Cache key
            // includes the local date so users in different timezones
            // don't all get the same throttle bucket.
            //
            // We use Cache::add() (atomic set-if-absent) instead of
            // Cache::has() + Cache::put() so two parallel runs (e.g. the
            // app-level hourly schedule AND the module-level 15-min
            // fallback) can't both observe "not present" and both send.
            $localDate = $localNow->toDateString();
            $throttleKey = "tgb:review_nudge:{$user->id}:{$localDate}";
            if (! $this->option('force')) {
                // 36h TTL survives timezone shifts and DST changes; add()
                // returns false if the key already exists, which is our
                // "already reminded today" signal.
                if (! Cache::add($throttleKey, 1, now()->addHours(36))) {
                    $skipped++;
                    continue;
                }
            }

            $dueVocab = $this->countDueVocab($user);
            $dueReading = $this->countDueReading($user);
            $totalDue = $dueVocab + $dueReading;

            if ($totalDue === 0) {
                $skipped++;
                continue;
            }

            $message = $this->buildMessage($user, $dueVocab, $dueReading);
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🔁 Ôn từ vựng', 'callback_data' => 'tgb:rv'],
                        ['text' => '📚 Ôn đọc hiểu', 'callback_data' => 'tgb:reading-review'],
                    ],
                    [
                        ['text' => '🏠 Menu', 'callback_data' => 'tgb:menu'],
                    ],
                ],
            ];

            if ($this->option('dry-run')) {
                $this->line("  [dry-run] user #{$user->id}: vocab={$dueVocab} reading={$dueReading}");
                $sent++;
                continue;
            }

            try {
                $ok = app(\App\Services\TelegramService::class)
                    ->sendMessage($link->telegram_chat_id, $message, $keyboard);

                if ($ok) {
                    $sent++;
                    $this->line("  ✓ Sent to user #{$user->id} (vocab={$dueVocab} reading={$dueReading})");
                } else {
                    // Send returned null — release the throttle so the
                    // next cron tick can retry instead of waiting 36h.
                    Cache::forget($throttleKey);
                    $skipped++;
                    $this->warn("  - Telegram send returned null for user #{$user->id}");
                }
            } catch (\Throwable $e) {
                // Same rationale: a thrown exception should not block
                // future retries by leaving the throttle key in place.
                Cache::forget($throttleKey);
                $failed++;
                $this->error("  ✗ Failed for user #{$user->id}: " . $e->getMessage());
                Log::error('[tgb:send-review-reminders] exception', [
                    'user_id' => $user->id,
                    'msg' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done. sent={$sent} skipped={$skipped} failed={$failed}");
        return self::SUCCESS;
    }

    private function countDueVocab(User $user): int
    {
        return ReviewSchedule::query()
            ->where('user_id', $user->id)
            ->where(function ($q) {
                $q->whereNull('next_review_at')
                    ->orWhere('next_review_at', '<=', now());
            })
            ->count();
    }

    private function countDueReading(User $user): int
    {
        return ReadingPassageReview::query()
            ->forUser($user->id)
            ->due()
            ->count();
    }

    private function buildMessage(User $user, int $vocab, int $reading): string
    {
        $streak = $user->streak ?? 0;
        $streakLine = $streak > 0 ? "🔥 Streak: <b>{$streak} ngày</b>\n" : '';

        $lines = [];
        $lines[] = "🔔 <b>Đến giờ ôn rồi!</b>";
        $lines[] = "━━━━━━━━━━━━━━━━━━━━";
        if ($streakLine) {
            $lines[] = trim($streakLine);
            $lines[] = "";
        }
        $lines[] = "📦 <b>Hôm nay bạn có:</b>";
        if ($vocab > 0) {
            $lines[] = "  • 📚 {$vocab} thẻ từ vựng";
        }
        if ($reading > 0) {
            $lines[] = "  • 📖 {$reading} bài đọc hiểu";
        }
        $lines[] = "";
        $lines[] = "💡 <i>Ôn ~5 phút mỗi ngày giúp nhớ lâu hơn rất nhiều!</i>";

        return implode("\n", $lines);
    }
}
