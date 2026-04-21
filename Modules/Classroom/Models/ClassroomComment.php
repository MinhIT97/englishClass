<?php

namespace Modules\Classroom\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ClassroomComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'classroom_post_id',
        'user_id',
        'content',
    ];

    public function post()
    {
        return $this->belongsTo(ClassroomPost::class, 'classroom_post_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
