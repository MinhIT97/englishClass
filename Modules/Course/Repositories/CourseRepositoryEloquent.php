<?php

namespace Modules\Course\Repositories;

use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Criteria\RequestCriteria;
use Modules\Course\Models\Course;

/**
 * Class CourseRepositoryEloquent.
 *
 * @package namespace Modules\Course\Repositories;
 */
class CourseRepositoryEloquent extends BaseRepository implements CourseRepository
{
    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return Course::class;
    }

    /**
     * Boot up the repository, pushing criteria
     */
    public function boot()
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }
}
