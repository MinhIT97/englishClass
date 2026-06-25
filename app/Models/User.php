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

// SECURITY (SEC-019): $fillable is intentionally restricted to user-controlled
// profile data only. Privilege / quota / gamification fields (role, status,
// is_unlimited, lesson_limit, can_request_extra_lesson, xp, streak) MUST NOT be
// mass-assignable. Defense-in-depth: even if a future controller does
// User::create($request->all()) or $user->update($request->all()), Eloquent will
// silently drop these fields because they are not in $fillable.
//
// To mutate these fields from trusted code (admin controllers, Telegram approval
// webhook, bulk import), use direct property assignment + save() — which bypasses
// $fillable — so the intent of an explicit privilege grant is visible in code.
#[Fillable(['name', 'email', 'password', 'target_band'])]
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
