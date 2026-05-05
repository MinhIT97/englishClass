<?php

namespace Modules\Classroom\Services;

use Modules\Classroom\Services\Contracts\ClassroomServiceInterface;
use Modules\Classroom\Repositories\Contracts\ClassroomRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Models\ClassroomComment;
use Modules\Classroom\Models\ClassroomPost;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Modules\Classroom\Events\ClassroomCommentCreated;
use Modules\Classroom\Events\ClassroomPostCreated;

class ClassroomService implements ClassroomServiceInterface
{
    protected $repository;

    public function __construct(ClassroomRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @inheritDoc
     */
    public function getUserClassrooms(User $user): Collection
    {
        return $this->repository->getAccessibleByUser($user);
    }

    /**
     * @inheritDoc
     */
    public function createClassroom(array $data, User $teacher): Classroom
    {
        return DB::transaction(function () use ($data, $teacher) {
            $data['teacher_id'] = $teacher->id;
            $data['invite_code'] = $this->generateUniqueInviteCode();

            return $this->repository->create($data);
        });
    }

    /**
     * @inheritDoc
     */
    public function getClassroomFeed(Classroom $classroom): Classroom
    {
        return $this->repository->findForFeed($classroom->id);
    }

    /**
     * @inheritDoc
     */
    public function joinClassroom(string $inviteCode, User $student): Classroom
    {
        $classroom = $this->repository->findByInviteCode(strtoupper($inviteCode));

        if (!$classroom) {
            throw new NotFoundHttpException('Invalid invite code.');
        }

        DB::transaction(function () use ($classroom, $student) {
            if (!$this->repository->hasStudent($classroom, $student->id)) {
                $this->repository->attachStudent($classroom, $student->id);
            }
        });

        return $classroom;
    }

    /**
     * @inheritDoc
     */
    public function createPost(Classroom $classroom, array $data, User $author): ClassroomPost
    {
        $post = DB::transaction(function () use ($classroom, $data, $author) {
            if (!empty($data['attachment'])) {
                $data['attachment_path'] = $data['attachment']->store('classroom_attachments/' . $classroom->id, 'public');
            }

            unset($data['attachment']);

            return $this->repository->createPost($classroom, [
                'user_id' => $author->id,
                'content' => $data['content'],
                'type' => $data['type'],
                'attachment_path' => $data['attachment_path'] ?? null,
            ]);
        });

        $post = $this->repository->findPostWithRelations($post->id);
        ClassroomPostCreated::dispatch($post);

        return $post;
    }

    /**
     * @inheritDoc
     */
    public function createComment(ClassroomPost $post, array $data, User $author): ClassroomComment
    {
        $comment = DB::transaction(function () use ($post, $data, $author) {
            return $this->repository->createComment($post, [
                'user_id' => $author->id,
                'content' => $data['content'],
            ]);
        });

        $comment->load(['user', 'post.user', 'post.classroom']);
        ClassroomCommentCreated::dispatch($comment);

        return $comment;
    }

    /**
     * @inheritDoc
     */
    public function addFeedback(ClassroomPost $post, array $data, User $reviewer): ClassroomPost
    {
        DB::transaction(function () use ($post, $data, $reviewer) {
            $post->update([
                'feedback_content' => $data['feedback_content'],
                'grade' => $data['grade'] ?? null,
                'feedback_by' => $reviewer->id,
            ]);
        });

        return $post->fresh(['feedbackBy', 'user', 'classroom']);
    }

    /**
     * Generate a unique 6-character invite code.
     */
    private function generateUniqueInviteCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while ($this->repository->findByInviteCode($code) !== null);

        return $code;
    }
}
