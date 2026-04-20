<?php

namespace Modules\Classroom\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
    ];

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    // protected static function newFactory(): ClassroomPostFactory
    // {
    //     // return ClassroomPostFactory::new();
    // }
}
