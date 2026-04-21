<?php

namespace Modules\Classroom\Repositories\Eloquent;

use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Criteria\RequestCriteria;
use Modules\Classroom\Repositories\Contracts\ClassroomRepositoryInterface;
use Modules\Classroom\Models\Classroom;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

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
    public function getByTeacher(int $teacherId): Collection
    {
        return $this->findWhere(['teacher_id' => $teacherId]);
    }

    /**
     * @inheritDoc
     */
    public function getByStudent(int $studentId): Collection
    {
        $user = User::find($studentId);
        return $user ? $user->classrooms : new Collection();
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
}
