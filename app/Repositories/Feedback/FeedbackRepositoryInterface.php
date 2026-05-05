<?php

namespace App\Repositories\Feedback;

use Prettus\Repository\Contracts\RepositoryInterface;

/**
 * Interface FeedbackRepositoryInterface.
 *
 * @package namespace App\Repositories\Feedback;
 */
interface FeedbackRepositoryInterface extends RepositoryInterface
{
    public function countPending();
}

