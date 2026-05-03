<?php

namespace Modules\IeltsSet\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class IeltsSet extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'topic',
        'set_type',
        'target_band',
        'skill_focus',
        'description',
        'difficulty',
        'duration_minutes',
        'total_questions',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function sections()
    {
        return $this->hasMany(IeltsSetSection::class)->orderBy('section_order');
    }

    public function attempts()
    {
        return $this->hasMany(IeltsSetAttempt::class);
    }

    public function latestAttemptFor(?User $user): ?IeltsSetAttempt
    {
        if (!$user) {
            return null;
        }

        return $this->attempts()->where('user_id', $user->id)->latest('started_at')->first();
    }

    public function currentAttemptFor(int $userId): ?IeltsSetAttempt
    {
        return $this->attempts()
            ->where('user_id', $userId)
            ->where('status', 'in_progress')
            ->latest('started_at')
            ->first();
    }
}
