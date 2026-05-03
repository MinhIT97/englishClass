<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Feedback\AddFeedbackNoteRequest;
use App\Http\Requests\Feedback\AssignFeedbackRequest;
use App\Http\Requests\Feedback\UpdateFeedbackStatusRequest;
use App\Repositories\Feedback\FeedbackLogRepositoryInterface;
use App\Repositories\Feedback\FeedbackRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Modules\Auth\Repositories\UserRepositoryInterface;

class AdminFeedbackController extends Controller
{
    public function __construct(
        protected FeedbackRepositoryInterface $feedbackRepository,
        protected FeedbackLogRepositoryInterface $feedbackLogRepository,
        protected UserRepositoryInterface $userRepository
    ) {
    }

    public function index()
    {
        $feedbacks = $this->feedbackRepository
            ->with(['user', 'assignedUser', 'logs.user'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $admins = $this->userRepository->getAdmins();

        return view('admin.feedback.index', compact('feedbacks', 'admins'));
    }

    public function updateStatus(UpdateFeedbackStatusRequest $request, $id)
    {
        $feedback = $this->feedbackRepository->find($id);
        $oldStatus = $feedback->status;

        $this->feedbackRepository->update(['status' => $request->status], $id);

        $this->feedbackLogRepository->create([
            'feedback_id' => $id,
            'user_id' => auth()->id(),
            'action' => 'status_changed',
            'content' => "Status changed from {$oldStatus} to {$request->status}",
        ]);

        Cache::forget('admin.pending_feedback_count');

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully!',
                'status' => $request->status,
            ]);
        }

        return back()->with('success', 'Feedback status updated successfully!');
    }

    public function assignUser(AssignFeedbackRequest $request, $id)
    {
        $this->feedbackRepository->update(['assigned_to' => $request->user_id], $id);

        $feedback = $this->feedbackRepository->with('assignedUser')->find($id);
        $assignedUserName = $feedback->assignedUser ? $feedback->assignedUser->name : 'Unassigned';

        $this->feedbackLogRepository->create([
            'feedback_id' => $id,
            'user_id' => auth()->id(),
            'action' => 'assigned',
            'content' => "Assigned to {$assignedUserName}",
        ]);

        return back()->with('success', 'Feedback assigned successfully!');
    }

    public function addNote(AddFeedbackNoteRequest $request, $id)
    {
        $this->feedbackLogRepository->create([
            'feedback_id' => $id,
            'user_id' => auth()->id(),
            'action' => 'note_added',
            'content' => $request->note,
        ]);

        return back()->with('success', 'Note added successfully!');
    }

    public function destroy($id)
    {
        $this->feedbackRepository->delete($id);
        Cache::forget('admin.pending_feedback_count');

        return back()->with('success', 'Feedback deleted successfully!');
    }
}
