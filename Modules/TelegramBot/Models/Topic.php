<?php

namespace Modules\TelegramBot\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Topic extends Model
{
    protected $table = 'tgb_topics';

    protected $fillable = [
        'slug',
        'purpose',
        'name_vi',
        'name_en',
        'order_index',
        'difficulty',
        'is_active',
    ];

    protected $casts = [
        'order_index' => 'integer',
        'difficulty' => 'integer',
        'is_active' => 'boolean',
    ];

    public function userPaths(): HasMany
    {
        return $this->hasMany(UserPath::class, 'topic_id');
    }

    public function vocabularyEntries(): HasMany
    {
        return $this->hasMany(VocabularyEntry::class, 'topic_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForPurpose($query, string $purpose)
    {
        return $query->where('purpose', $purpose);
    }
}
