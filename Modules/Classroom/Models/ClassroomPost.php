<?php

namespace Modules\Classroom\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
// use Modules\Classroom\Database\Factories\ClassroomPostFactory;

class ClassroomPost extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'classroom_id',
        'user_id',
        'content',
        'type',
        'attachment_path',
        'feedback_content',
        'grade',
        'feedback_by',
    ];

    public function feedbackBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'feedback_by');
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ClassroomComment::class)->oldest();
    }

    // protected static function newFactory(): ClassroomPostFactory
    // {
    //     // return ClassroomPostFactory::new();
    // }
}
