<?php

namespace Modules\Question\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'skill',
        'type',
        'topic',
        'content',
        'difficulty',
    ];

    protected $casts = [
        'content' => 'json',
    ];

    /**
     * Get parsed content as object.
     */
    public function getParsedContentAttribute()
    {
        return (object) $this->content;
    }

    /**
     * Local scope to apply filters using a pipeline.
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['skill'] ?? null, fn (Builder $q, string $skill) => $q->where('skill', $skill))
            ->when($filters['type'] ?? null, fn (Builder $q, string $type) => $q->where('type', $type))
            ->when($filters['topic'] ?? null, fn (Builder $q, string $topic) => $q->where('topic', $topic));
    }

    public function scopeForSkill(Builder $query, string $skill): Builder
    {
        return $query->where('skill', $skill);
    }
}
