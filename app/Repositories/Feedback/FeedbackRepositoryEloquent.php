<?php

namespace App\Repositories\Feedback;

use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Criteria\RequestCriteria;
use App\Models\Feedback;

/**
 * Class FeedbackRepositoryEloquent.
 *
 * @package namespace App\Repositories\Feedback;
 */
class FeedbackRepositoryEloquent extends BaseRepository implements FeedbackRepositoryInterface
{
    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return Feedback::class;
    }

    /**
     * Boot up the repository, pushing criteria
     */
    public function boot()
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }

    public function countPending()
    {
        return $this->model->pending()->count();
    }
}

