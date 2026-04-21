<?php

namespace Modules\Classroom\Repositories\Contracts;

use Prettus\Repository\Contracts\RepositoryInterface;
use Modules\Classroom\Models\Classroom;
use Illuminate\Database\Eloquent\Collection;

/**
 * Interface ClassroomRepositoryInterface
 * @package namespace Modules\Classroom\Repositories\Contracts;
 */
interface ClassroomRepositoryInterface extends RepositoryInterface
{
    /**
     * Get all classrooms created by a specific teacher.
     */
    public function getByTeacher(int $teacherId): Collection;

    /**
     * Get all classrooms a specific student has joined.
     */
    public function getByStudent(int $studentId): Collection;

    /**
     * Sync a student to a classroom.
     */
    public function attachStudent(Classroom $classroom, int $studentId): void;

    /**
     * Find a classroom by its generated invite code.
     */
    public function findByInviteCode(string $inviteCode): ?Classroom;
}
