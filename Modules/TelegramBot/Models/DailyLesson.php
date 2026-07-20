<?php

namespace Modules\TelegramBot\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyLesson extends Model
{
    protected $table = 'tgb_daily_lessons';

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'user_id',
        'topic_id',
        'lesson_date',
        'is_extra',
        'status',
        'telegram_message_id',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'lesson_date' => 'date',
        'is_extra' => 'boolean',
        'sent_at' => 'datetime',
        'telegram_message_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class, 'topic_id');
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class, 'daily_lesson_id');
    }
}
