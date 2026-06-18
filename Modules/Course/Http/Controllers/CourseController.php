<?php

namespace Modules\Course\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\LessonRequest;
use App\Services\LessonQuotaService;
use Illuminate\Http\Request;
use Modules\Course\Http\Requests\CourseRequest;
use Modules\Course\Http\Resources\CourseResource;
use Modules\Course\Services\CourseService;

class CourseController extends Controller
{
    protected $service;

    public function __construct(CourseService $service, protected LessonQuotaService $quota)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filters = $request->only(['title', 'status']);
        $courses = $this->service->paginate($filters, $request->integer('limit', 12));
        $enrolledCourseIds = $this->service->enrolledCourseIds($request->user());

        if ($request->expectsJson() || $request->ajax()) {
            return CourseResource::collection($courses);
        }

        return view('course::index', compact('courses', 'enrolledCourseIds'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * Admins and unlimited users bypass the quota. Other users are
     * capped at `lesson_limit` courses per calendar day. When the cap
     * is hit we either return 403 (API/AJAX) or redirect with an error
     * that prompts the user to request more lessons.
     */
    public function store(CourseRequest $request)
    {
        $user = $request->user();

        // SECURITY: explicit role check here is belt-and-suspenders
        // with CourseRequest::authorize() (which already rejects
        // students at the FormRequest layer). Returning a clear 403
        // message is friendlier than letting the user see the
        // authorization error from the FormRequest, and prevents
        // quota counters from ticking if a future refactor accidentally
        // relaxes the FormRequest guard.
        if (! $user->isAdmin() && ! $user->isTeacher()) {
            abort(403, 'Only teachers and admins can create courses.');
        }

        $check = $this->quota->check($user, LessonRequest::TYPE_COURSE);

        if (! $check['allowed']) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Bạn đã đạt giới hạn tạo khóa học trong ngày.',
                    'reason' => $check['reason'],
                    'used' => $check['used'],
                    'limit' => $check['limit'],
                    'request_url' => route('lesson-requests.store'),
                ], 403);
            }

            return back()->with('error', "Bạn đã tạo {$check['used']}/{$check['limit']} khóa học hôm nay. Vui lòng gửi yêu cầu xin thêm cho admin.")
                         ->withInput();
        }

        $course = $this->service->create($request->validated());
        return new CourseResource($course);
    }

    /**
     * Show the specified resource.
     */
    public function show(Request $request, $id)
    {
        $course = $this->service->find($id);
        
        if ($request->expectsJson() || $request->ajax()) {
            return new CourseResource($course);
        }

        $isEnrolled = $this->service->isEnrolled($request->user(), (int) $id);

        return view('course::show', compact('course', 'isEnrolled'));
    }

    /**
     * Enroll in the specified course.
     */
    public function enroll(Request $request, $id)
    {
        $course = $this->service->find($id);
        $user = $request->user();

        if ($this->service->isEnrolled($user, (int) $id)) {
            return redirect()->back()->with('error', 'You are already enrolled in this course.');
        }

        $user->enrolledCourses()->attach($id);

        return redirect()->route('course.show', $id)->with('success', 'Successfully enrolled in ' . $course->title);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CourseRequest $request, $id)
    {
        $user = $request->user();

        // SECURITY: only the owning teacher (or any admin) may edit
        // a course. This prevents one teacher from modifying
        // another's course via IDOR.
        $course = $this->service->find($id);
        if (! $user->isAdmin()) {
            // Course model does not currently have an owner_id; we
            // rely on the global role check. If/when teacher_id is
            // added, change this to compare $course->teacher_id ===
            // $user->id.
            abort(403, 'Only admins can modify this course.');
        }

        $course = $this->service->update($id, $request->validated());
        return new CourseResource($course);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        // SECURITY: only admins can delete courses. Teachers delete
        // their own via the policy layer (future) — for now we
        // require admin to prevent accidental/malicious deletions
        // through the resource controller.
        if (! auth()->user() || ! auth()->user()->isAdmin()) {
            abort(403, 'Only admins can delete courses.');
        }

        $this->service->delete($id);
        return response()->json(['message' => 'Course deleted successfully']);
    }
}
