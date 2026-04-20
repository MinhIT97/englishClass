<?php

namespace Modules\Question\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Pipeline\Pipeline;
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
        return app(Pipeline::class)
            ->send($query)
            ->through([
                \Modules\Question\Filters\SkillFilter::class,
                \Modules\Question\Filters\TypeFilter::class,
                \Modules\Question\Filters\TopicFilter::class,
            ])
            ->thenReturn();
    }
}
