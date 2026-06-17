<?php

namespace Modules\TelegramBot\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VocabularyEntry extends Model
{
    protected $table = 'tgb_vocabulary_entries';

    protected $fillable = [
        'user_id',
        'topic_id',
        'word',
        'pos',
        'ipa',
        'meaning_vi',
        'meaning_en',
        'example_en',
        'example_vi',
        'difficulty',
    ];

    protected $casts = [
        'difficulty' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class, 'topic_id');
    }

    public function reviewSchedule(): HasOne
    {
        return $this->hasOne(ReviewSchedule::class, 'vocabulary_entry_id');
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class, 'vocabulary_entry_id');
    }
}
