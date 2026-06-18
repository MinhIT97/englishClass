<?php

namespace Modules\TelegramBot\Models;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LearningProfile extends Model
{
    protected $table = 'tgb_learning_profiles';

    public const DEFAULT_TIMEZONE = 'Asia/Ho_Chi_Minh';

    public const PURPOSE_IELTS = 'ielts';

    public const PURPOSE_DAILY = 'daily';

    public const PURPOSE_BUSINESS = 'business';

    public const LEVEL_BEGINNER = 'beginner';

    public const LEVEL_INTERMEDIATE = 'intermediate';

    public const LEVEL_ADVANCED = 'advanced';

    protected $fillable = [
        'user_id',
        'purpose',
        'level',
        'target_band',
        'daily_send_hour',
        'timezone',
        'is_paused',
        'onboarded_at',
    ];

    protected $casts = [
        'target_band' => 'decimal:1',
        'daily_send_hour' => 'integer',
        'is_paused' => 'boolean',
        'onboarded_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function telegramLink(): HasOne
    {
        return $this->hasOne(UserTelegramLink::class, 'user_id', 'user_id');
    }

    public function localDateTime(CarbonInterface $dateTime): Carbon
    {
        try {
            return Carbon::instance($dateTime)
                ->setTimezone($this->timezone ?: self::DEFAULT_TIMEZONE);
        } catch (Exception) {
            return Carbon::instance($dateTime)
                ->setTimezone(self::DEFAULT_TIMEZONE);
        }
    }

    public function isDailySendTime(CarbonInterface $dateTime): bool
    {
        return $this->localDateTime($dateTime)->hour === $this->daily_send_hour;
    }

    public static function purposes(): array
    {
        return [
            self::PURPOSE_IELTS => 'IELTS',
            self::PURPOSE_DAILY => 'Giao tiếp',
            self::PURPOSE_BUSINESS => 'Công việc',
        ];
    }

    public static function levels(): array
    {
        return [
            self::LEVEL_BEGINNER => 'Cơ bản',
            self::LEVEL_INTERMEDIATE => 'Trung cấp',
            self::LEVEL_ADVANCED => 'Nâng cao',
        ];
    }

    public function suggestLevel(?float $targetBand = null): string
    {
        if ($targetBand === null) {
            return $this->level ?? self::LEVEL_INTERMEDIATE;
        }

        if ($targetBand < 5.5) {
            return self::LEVEL_BEGINNER;
        }
        if ($targetBand < 7.0) {
            return self::LEVEL_INTERMEDIATE;
        }

        return self::LEVEL_ADVANCED;
    }
}
