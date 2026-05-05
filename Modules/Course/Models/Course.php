<?php

namespace Modules\Course\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Course extends Model
{
    use HasFactory, HasSlug;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'price',
        'status',
    ];

    public function scopeFilter($query, array $filters)
    {
        return $query
            ->when($filters['title'] ?? null, fn ($q, $title) => $q->where('title', 'like', '%' . $title . '%'))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status));
    }

    /**
     * The students that belong to the course.
     */
    public function students()
    {
        return $this->belongsToMany(\App\Models\User::class, 'course_user')
            ->withPivot('status')
            ->withTimestamps();
    }

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug');
    }
}
