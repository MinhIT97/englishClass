<?php

namespace Modules\Classroom\Services;

use Modules\Classroom\Services\Contracts\ClassroomServiceInterface;
use Modules\Classroom\Repositories\Contracts\ClassroomRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Str;
use Modules\Classroom\Models\Classroom;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        if ($user->role === 'admin' || $user->role === 'teacher') {
            return $this->repository->getByTeacher($user->id);
        }
        
        return $this->repository->getByStudent($user->id);
    }

    /**
     * @inheritDoc
     */
    public function createClassroom(array $data, User $teacher): Classroom
    {
        $data['teacher_id'] = $teacher->id;
        $data['invite_code'] = $this->generateInviteCode();

        return $this->repository->create($data);
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

        $this->repository->attachStudent($classroom, $student->id);

        return $classroom;
    }

    /**
     * Generate a unique 6-character invite code.
     */
    private function generateInviteCode(): string
    {
        return strtoupper(Str::random(6));
    }
}
