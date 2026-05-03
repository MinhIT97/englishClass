<?php

namespace Modules\Classroom\Services\Contracts;

use Modules\Classroom\Models\Classroom;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Modules\Classroom\Models\ClassroomComment;
use Modules\Classroom\Models\ClassroomPost;

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
     * Get classroom feed with required relations.
     */
    public function getClassroomFeed(Classroom $classroom): Classroom;

    /**
     * Join a student to a classroom via an invite code.
     */
    public function joinClassroom(string $inviteCode, User $student): Classroom;

    /**
     * Create a post inside a classroom.
     */
    public function createPost(Classroom $classroom, array $data, User $author): ClassroomPost;

    /**
     * Create a comment for a classroom post.
     */
    public function createComment(ClassroomPost $post, array $data, User $author): ClassroomComment;

    /**
     * Add feedback to a classroom post.
     */
    public function addFeedback(ClassroomPost $post, array $data, User $reviewer): ClassroomPost;
}
