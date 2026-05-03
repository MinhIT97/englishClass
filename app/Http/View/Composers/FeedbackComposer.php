<?php

namespace App\Http\View\Composers;

use App\Repositories\Feedback\FeedbackRepositoryInterface;
use Illuminate\View\View;

class FeedbackComposer
{
    protected $feedbackRepository;

    public function __construct(FeedbackRepositoryInterface $feedbackRepository)
    {
        $this->feedbackRepository = $feedbackRepository;
    }

    public function compose(View $view)
    {
        $pendingFeedbackCount = 0;
        
        if (auth()->check() && auth()->user()->role === 'admin') {
            $pendingFeedbackCount = $this->feedbackRepository->countPending();
        }

        $view->with('pendingFeedbackCount', $pendingFeedbackCount);
    }
}
