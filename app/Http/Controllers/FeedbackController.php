<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Feedback;


use App\Http\Requests\Feedback\StoreFeedbackRequest;
use App\Repositories\Feedback\FeedbackRepositoryInterface;

class FeedbackController extends Controller
{
    protected $feedbackRepository;

    public function __construct(FeedbackRepositoryInterface $feedbackRepository)
    {
        $this->feedbackRepository = $feedbackRepository;
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

        return response()->json([
            'success' => true,
            'message' => __('ui.feedback_success')
        ]);
    }
}


