<?php

namespace App\Http\View\Composers;

use App\Repositories\Feedback\FeedbackRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class FeedbackComposer
{
    public function __construct(protected FeedbackRepositoryInterface $feedbackRepository)
    {
    }

    public function compose(View $view)
    {
        $pendingFeedbackCount = 0;

        if (auth()->check() && auth()->user()->role === 'admin') {
            $pendingFeedbackCount = Cache::remember(
                'admin.pending_feedback_count',
                now()->addMinute(),
                fn () => $this->feedbackRepository->countPending()
            );
        }

        $view->with('pendingFeedbackCount', $pendingFeedbackCount);
    }
}
