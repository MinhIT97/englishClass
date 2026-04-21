<?php

namespace Modules\Classroom\Services\Contracts;

use Modules\Classroom\Models\Classroom;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface ClassroomServiceInterface
{
    /**
     * Get accessible classrooms for a user based on their role.
     */
    public function getUserClassrooms(User $user): Collection;

    /**
     * Create a new classroom.
     */
    public function createClassroom(array $data, User $teacher): Classroom;

    /**
     * Join a student to a classroom via an invite code.
     */
    public function joinClassroom(string $inviteCode, User $student): Classroom;
}
