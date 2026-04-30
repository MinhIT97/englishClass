<?php

namespace Modules\Auth\Services;

use Modules\Auth\Repositories\UserRepositoryInterface;

class UserService
{
    protected $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Approve a user (set status to active).
     */
    public function approveUser(int $id)
    {
        return $this->userRepository->updateStatus($id, 'active');
    }

    /**
     * Reject a user (set status to rejected).
     */
    public function rejectUser(int $id)
    {
        return $this->userRepository->updateStatus($id, 'rejected');
    }

    /**
     * List users by status.
     */
    public function listByStatus(string $status, int $perPage = 15)
    {
        $this->userRepository->pushCriteria(new \Prettus\Repository\Criteria\RequestCriteria(request()));
        return $this->userRepository->scopeQuery(function($query) use ($status) {
            return $query->where('status', $status)->where('role', 'student');
        })->paginate($perPage);
    }
}
