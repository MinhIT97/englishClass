<?php

namespace Modules\Classroom\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Modules\Classroom\Models\Classroom;

class ClassroomPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin->value, UserRole::Teacher->value], true);
    }

    public function view(User $user, Classroom $classroom): bool
    {
        return $classroom->teacher_id === $user->id
            || $classroom->students()->whereKey($user->id)->exists()
            || $user->role === UserRole::Admin->value;
    }

    public function createPost(User $user, Classroom $classroom): bool
    {
        return $this->view($user, $classroom);
    }
}
