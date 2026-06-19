<?php

namespace App\Services;

use App\Models\LessonRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Centralized lesson-quota authority.
 *
 * Every controller/service that creates a lesson on behalf of a user
 * must consult this service first. The contract is:
 *
 *   $result = $quota->check($user, LessonRequest::TYPE_COURSE);
 *   if (! $result['allowed']) { abort(403, $result['reason']); }
 *
 * Bypass rules (in order):
 *   1. role === 'admin' -> unlimited, no record kept.
 *   2. user.is_unlimited = true -> unlimited, no record kept.
 *   3. Otherwise: counted against `user.lesson_limit` per calendar day
 *      (today, in the app timezone).
 *
 * Counting strategy:
 *   - TYPE_COURSE / TYPE_CLASSROOM -> query the DB created_at of the
 *     respective table for rows created today.
 *   - TYPE_DAILY_LESSON -> read the same cache counter the Telegram
 *     bot uses (`tgb:extra_count:{user}:{date}`) so the two paths share
 *     a single source of truth.
 */
class LessonQuotaService
{
    /** Sentinel reason codes used by callers to render user-friendly errors. */
    public const REASON_OK = 'ok';
    public const REASON_BYPASS_ADMIN = 'bypass_admin';
    public const REASON_BYPASS_UNLIMITED = 'bypass_unlimited';
    public const REASON_DAILY_LIMIT = 'daily_limit';

    public function check(User $user, string $lessonType): array
    {
        if ($user->hasUnlimitedLessons()) {
            return [
                'allowed' => true,
                'reason' => $user->isAdmin() ? self::REASON_BYPASS_ADMIN : self::REASON_BYPASS_UNLIMITED,
                'used' => null,
                'limit' => null,
            ];
        }

        $limit = (int) ($user->lesson_limit ?: User::DEFAULT_LESSON_LIMIT);
        $used = $this->usedToday($user, $lessonType);

        if ($used >= $limit) {
            return [
                'allowed' => false,
                'reason' => self::REASON_DAILY_LIMIT,
                'used' => $used,
                'limit' => $limit,
            ];
        }

        return [
            'allowed' => true,
            'reason' => self::REASON_OK,
            'used' => $used,
            'limit' => $limit,
        ];
    }

    public function usedToday(User $user, string $lessonType): int
    {
        $today = Carbon::now()->toDateString();

        return match ($lessonType) {
            LessonRequest::TYPE_COURSE => $this->countCreatedToday('courses', $user->id, 'teacher_id', $today),
            LessonRequest::TYPE_CLASSROOM => $this->countCreatedToday('classrooms', $user->id, 'teacher_id', $today),
            LessonRequest::TYPE_DAILY_LESSON => (int) Cache::get($this->extraCountKey($user, $today), 0),
            default => 0,
        };
    }

    public function remainingToday(User $user, string $lessonType): int
    {
        $check = $this->check($user, $lessonType);
        if (! $check['allowed'] || $check['limit'] === null) {
            return $check['limit'] === null ? PHP_INT_MAX : 0;
        }

        return max(0, $check['limit'] - $check['used']);
    }

    /**
     * Apply an admin approval: bump user.lesson_limit OR flip
     * is_unlimited. Returns the updated user.
     */
    public function applyApproval(User $user, LessonRequest $request): User
    {
        return DB::transaction(function () use ($user, $request) {
            if ($request->grant_unlimited) {
                $user->is_unlimited = true;
            } elseif ($request->approved_extra !== null && $request->approved_extra > 0) {
                $user->lesson_limit = (int) $user->lesson_limit + (int) $request->approved_extra;
            }
            $user->save();

            return $user->fresh();
        });
    }

    private function countCreatedToday(string $table, int $userId, string $userColumn, string $date): int
    {
        $query = DB::table($table)->whereDate('created_at', $date);
        $query->where($userColumn, $userId);

        return $query->count();
    }

    private function extraCountKey(User $user, string $date): string
    {
        return "tgb:extra_count:{$user->id}:{$date}";
    }
}
