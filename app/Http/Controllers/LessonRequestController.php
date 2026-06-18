<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewLessonRequestRequest;
use App\Http\Requests\StoreLessonRequestRequest;
use App\Models\LessonRequest;
use App\Services\LessonQuotaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LessonRequestController extends Controller
{
    public function __construct(
        protected LessonQuotaService $quota
    ) {
    }

    /**
     * User-facing: submit a request for more daily lessons.
     */
    public function store(StoreLessonRequestRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Prevent duplicate pending requests for the same lesson type.
        $existing = LessonRequest::query()
            ->where('user_id', $user->id)
            ->where('lesson_type', $request->validated('lesson_type'))
            ->where('status', LessonRequest::STATUS_PENDING)
            ->exists();

        if ($existing) {
            return back()->with('error', 'Bạn đã có yêu cầu đang chờ duyệt cho loại bài học này.');
        }

        LessonRequest::create([
            'user_id' => $user->id,
            'lesson_type' => $request->validated('lesson_type'),
            'requested_extra' => $request->validated('requested_extra'),
            'reason' => $request->validated('reason'),
            'status' => LessonRequest::STATUS_PENDING,
        ]);

        return back()->with('success', 'Yêu cầu xin thêm bài học đã được gửi đến admin.');
    }

    /**
     * Admin-facing: list pending requests.
     */
    public function index(Request $request)
    {
        $status = $request->query('status', LessonRequest::STATUS_PENDING);

        $requests = LessonRequest::query()
            ->with(['user', 'reviewer'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.lesson-requests.index', [
            'requests' => $requests,
            'status' => $status,
        ]);
    }

    /**
     * Admin-facing: approve or reject a request. On approval, apply the
     * quota change to the requesting user.
     */
    public function review(ReviewLessonRequestRequest $request, LessonRequest $lessonRequest): RedirectResponse
    {
        $admin = $request->user();

        if (! $lessonRequest->isPending()) {
            return back()->with('error', 'Yêu cầu này đã được xử lý trước đó.');
        }

        if ($request->validated('decision') === 'reject') {
            $lessonRequest->update([
                'status' => LessonRequest::STATUS_REJECTED,
                'admin_note' => $request->validated('admin_note'),
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            return back()->with('success', 'Đã từ chối yêu cầu.');
        }

        $grantUnlimited = (bool) $request->boolean('grant_unlimited');
        $approvedExtra = (int) $request->validated('approved_extra');

        $lessonRequest->update([
            'status' => LessonRequest::STATUS_APPROVED,
            'approved_extra' => $grantUnlimited ? null : $approvedExtra,
            'grant_unlimited' => $grantUnlimited,
            'admin_note' => $request->validated('admin_note'),
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $this->quota->applyApproval($lessonRequest->user, $lessonRequest);

        return back()->with('success', $grantUnlimited
            ? 'Đã duyệt và cấp quyền tạo bài không giới hạn cho user.'
            : "Đã duyệt và cộng thêm {$approvedExtra} bài học/ngày cho user.");
    }
}