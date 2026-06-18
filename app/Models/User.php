<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

#[Fillable(['name', 'email', 'password', 'role', 'status', 'target_band', 'xp', 'streak', 'can_request_extra_lesson', 'lesson_limit', 'is_unlimited'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Default daily lesson cap for non-admin users. */
    public const DEFAULT_LESSON_LIMIT = 3;

    public function scopeStudents($query)
    {
        return $query->where('role', \App\Enums\UserRole::Student->value);
    }

    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function isAdmin(): bool
    {
        return $this->role === \App\Enums\UserRole::Admin->value;
    }

    public function isTeacher(): bool
    {
        return $this->role === \App\Enums\UserRole::Teacher->value;
    }

    /**
     * Admins and unlimited-flagged users bypass the daily quota. This
     * is the single gate every controller/service should consult before
     * creating a lesson on behalf of a user.
     */
    public function hasUnlimitedLessons(): bool
    {
        return $this->isAdmin() || (bool) $this->is_unlimited;
    }

    public function lessonRequests()
    {
        return $this->hasMany(LessonRequest::class);
    }

    public function classrooms()
    {
        return $this->belongsToMany(\Modules\Classroom\Models\Classroom::class, 'classroom_user')
                    ->withPivot('role')
                    ->withTimestamps();
    }

    public function enrolledCourses()
    {
        return $this->belongsToMany(\Modules\Course\Models\Course::class, 'course_user')
                    ->withPivot('status')
                    ->withTimestamps();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'can_request_extra_lesson' => 'boolean',
            'is_unlimited' => 'boolean',
            'lesson_limit' => 'integer',
        ];
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [
            'role' => $this->role,
            'status' => $this->status,
        ];
    }
}
