<?php

namespace Modules\Classroom\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassroomComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'classroom_post_id',
        'user_id',
        'content',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(ClassroomPost::class, 'classroom_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
