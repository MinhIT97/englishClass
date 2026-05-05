<?php

namespace App\Repositories\Feedback;

use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Criteria\RequestCriteria;
use App\Models\FeedbackLog;

/**
 * Class FeedbackLogRepositoryEloquent.
 *
 * @package namespace App\Repositories\Feedback;
 */
class FeedbackLogRepositoryEloquent extends BaseRepository implements FeedbackLogRepositoryInterface
{
    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return FeedbackLog::class;
    }

    /**
     * Boot up the repository, pushing criteria
     */
    public function boot()
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }
    
}
