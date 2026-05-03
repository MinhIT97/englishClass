<?php

namespace Modules\Classroom\Repositories\Contracts;

use Prettus\Repository\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Models\ClassroomComment;
use Modules\Classroom\Models\ClassroomPost;
use App\Models\User;

/**
 * Interface ClassroomRepositoryInterface
 * @package namespace Modules\Classroom\Repositories\Contracts;
 */
interface ClassroomRepositoryInterface extends RepositoryInterface
{
    /**
     * Get all classrooms the current user can access.
     */
    public function getAccessibleByUser(User $user): Collection;

    /**
     * Get a classroom with feed data loaded.
     */
    public function findForFeed(int $classroomId): Classroom;

    /**
     * Sync a student to a classroom.
     */
    public function attachStudent(Classroom $classroom, int $studentId): void;

    /**
     * Find a classroom by its generated invite code.
     */
    public function findByInviteCode(string $inviteCode): ?Classroom;

    /**
     * Determine if a user already joined the classroom.
     */
    public function hasStudent(Classroom $classroom, int $userId): bool;

    /**
     * Find a classroom post with relations needed for write flows.
     */
    public function findPostWithRelations(int $postId): ClassroomPost;

    /**
     * Persist a post in a classroom.
     */
    public function createPost(Classroom $classroom, array $attributes): ClassroomPost;

    /**
     * Persist a comment in a post.
     */
    public function createComment(ClassroomPost $post, array $attributes): ClassroomComment;
}
