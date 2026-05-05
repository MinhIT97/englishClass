<?php

namespace Modules\Course\Services;

use App\Models\User;
use Modules\Course\Repositories\CourseRepository;

class CourseService
{
    protected $repository;

    public function __construct(CourseRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get list of courses with filtering.
     */
    public function paginate(array $filters = [], int $perPage = 15)
    {
        return $this->repository->model()::query()
            ->filter($filters)
            ->paginate($perPage);
    }

    /**
     * Create a new course.
     */
    public function create(array $data)
    {
        return $this->repository->create($data);
    }

    /**
     * Find a course by ID.
     */
    public function find(int $id)
    {
        return $this->repository->find($id);
    }

    public function enrolledCourseIds(User $user): array
    {
        return $user->enrolledCourses()
            ->pluck('courses.id')
            ->all();
    }

    public function isEnrolled(User $user, int $courseId): bool
    {
        return $user->enrolledCourses()
            ->where('course_id', $courseId)
            ->exists();
    }

    /**
     * Update an existing course.
     */
    public function update(int $id, array $data)
    {
        return $this->repository->update($data, $id);
    }

    /**
     * Delete a course.
     */
    public function delete(int $id)
    {
        return $this->repository->delete($id);
    }
}
