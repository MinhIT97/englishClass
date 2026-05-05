<?php

namespace Modules\Classroom\Repositories\Eloquent;

use App\Enums\UserRole;
use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Criteria\RequestCriteria;
use Modules\Classroom\Repositories\Contracts\ClassroomRepositoryInterface;
use Modules\Classroom\Models\Classroom;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Modules\Classroom\Models\ClassroomComment;
use Modules\Classroom\Models\ClassroomPost;

/**
 * Class ClassroomRepository
 * @package namespace Modules\Classroom\Repositories\Eloquent;
 */
class ClassroomRepository extends BaseRepository implements ClassroomRepositoryInterface
{
    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return Classroom::class;
    }

    /**
     * Boot up the repository, pushing criteria
     */
    public function boot()
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }

    /**
     * @inheritDoc
     */
    public function getAccessibleByUser(User $user): Collection
    {
        if ($user->role === UserRole::Admin->value) {
            return $this->model
                ->newQuery()
                ->with('teacher')
                ->latest()
                ->get();
        }

        if ($user->role === UserRole::Teacher->value) {
            return $this->model
                ->newQuery()
                ->with('teacher')
                ->where('teacher_id', $user->id)
                ->latest()
                ->get();
        }

        return $user->classrooms()
            ->with('teacher')
            ->latest('classrooms.created_at')
            ->get();
    }

    /**
     * @inheritDoc
     */
    public function attachStudent(Classroom $classroom, int $studentId): void
    {
        $classroom->students()->syncWithoutDetaching([$studentId]);
    }

    /**
     * @inheritDoc
     */
    public function findByInviteCode(string $inviteCode): ?Classroom
    {
        return $this->findWhere(['invite_code' => $inviteCode])->first();
    }

    /**
     * @inheritDoc
     */
    public function findForFeed(int $classroomId): Classroom
    {
        return $this->model
            ->newQuery()
            ->with([
                'teacher',
                'students',
                'posts.user',
                'posts.feedbackBy',
                'posts.comments.user',
            ])
            ->findOrFail($classroomId);
    }

    /**
     * @inheritDoc
     */
    public function hasStudent(Classroom $classroom, int $userId): bool
    {
        return $classroom->students()->whereKey($userId)->exists();
    }

    /**
     * @inheritDoc
     */
    public function findPostWithRelations(int $postId): ClassroomPost
    {
        return ClassroomPost::query()
            ->with(['classroom.teacher', 'classroom.students', 'user', 'comments.user', 'feedbackBy'])
            ->findOrFail($postId);
    }

    /**
     * @inheritDoc
     */
    public function createPost(Classroom $classroom, array $attributes): ClassroomPost
    {
        return $classroom->posts()->create($attributes);
    }

    /**
     * @inheritDoc
     */
    public function createComment(ClassroomPost $post, array $attributes): ClassroomComment
    {
        return $post->comments()->create($attributes);
    }
}
