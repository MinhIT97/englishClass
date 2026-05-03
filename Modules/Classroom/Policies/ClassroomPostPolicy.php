<?php

namespace Modules\Classroom\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Modules\Classroom\Models\ClassroomPost;

class ClassroomPostPolicy
{
    public function comment(User $user, ClassroomPost $post): bool
    {
        return $post->classroom->teacher_id === $user->id
            || $post->classroom->students()->whereKey($user->id)->exists()
            || $user->role === UserRole::Admin->value;
    }

    public function giveFeedback(User $user, ClassroomPost $post): bool
    {
        return $user->role === UserRole::Admin->value
            || ($user->role === UserRole::Teacher->value && $post->classroom->teacher_id === $user->id);
    }
}
