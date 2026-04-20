<?php

namespace Modules\Course\Services;

use Modules\Course\Repositories\CourseRepository;
use Illuminate\Pipeline\Pipeline;

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
    public function paginate(int $perPage = 15)
    {
        return app(Pipeline::class)
            ->send($this->repository->model()::query())
            ->through([
                \Modules\Course\Filters\TitleFilter::class,
                \Modules\Course\Filters\StatusFilter::class,
            ])
            ->thenReturn()
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
