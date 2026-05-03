<?php

namespace App\Http\Controllers;

use App\Http\Requests\Feedback\StoreFeedbackRequest;
use App\Repositories\Feedback\FeedbackRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class FeedbackController extends Controller
{
    public function __construct(protected FeedbackRepositoryInterface $feedbackRepository)
    {
    }

    public function store(StoreFeedbackRequest $request)
    {
        $this->feedbackRepository->create([
            'user_id' => auth()->id(),
            'type' => $request->feedback_type,
            'rating' => $request->rating,
            'message' => $request->message,
            'email' => $request->email,
        ]);

        Cache::forget('admin.pending_feedback_count');

        return response()->json([
            'success' => true,
            'message' => __('ui.feedback_success'),
        ]);
    }
}
