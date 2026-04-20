<?php

namespace Modules\Classroom\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Classroom\Database\Factories\ClassroomFactory;

class Classroom extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'description',
        'teacher_id',
        'invite_code',
        'banner_image',
    ];

    public function teacher()
    {
        return $this->belongsTo(\App\Models\User::class, 'teacher_id');
    }

    public function students()
    {
        return $this->belongsToMany(\App\Models\User::class, 'classroom_user')
                    ->withPivot('role')
                    ->withTimestamps();
    }

    public function posts()
    {
        return $this->hasMany(ClassroomPost::class)->latest();
    }

    // protected static function newFactory(): ClassroomFactory
    // {
    //     // return ClassroomFactory::new();
    // }
}
