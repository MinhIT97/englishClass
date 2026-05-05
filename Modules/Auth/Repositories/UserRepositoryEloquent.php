<?php

namespace Modules\Auth\Repositories;

use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Criteria\RequestCriteria;
use App\Models\User;

/**
 * Class UserRepositoryEloquent.
 *
 * @package namespace Modules\Auth\Repositories;
 */
class UserRepositoryEloquent extends BaseRepository implements UserRepositoryInterface
{
    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return User::class;
    }

    /**
     * Find user by email.
     */
    public function findByEmail(string $email)
    {
        return $this->model->where('email', $email)->first();
    }

    /**
     * Update user status.
     */
    public function updateStatus(int $id, string $status)
    {
        return $this->update(['status' => $status], $id);
    }

    /**
     * Get all users with admin role.
     */
    public function getAdmins()
    {
        return $this->findWhere(['role' => 'admin']);
    }


    /**
     * Boot up the repository, pushing criteria
     */
    public function boot()
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }
}
