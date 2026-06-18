<?php

namespace Modules\Classroom\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LessonRequest;
use App\Services\LessonQuotaService;
use Illuminate\Http\Request;
use Modules\Classroom\Http\Requests\JoinClassroomRequest;
use Modules\Classroom\Http\Requests\StoreClassroomCommentRequest;
use Modules\Classroom\Http\Requests\StoreClassroomFeedbackRequest;
use Modules\Classroom\Http\Requests\StoreClassroomPostRequest;
use Modules\Classroom\Http\Requests\StoreClassroomRequest;
use Modules\Classroom\Http\Resources\ClassroomCommentResource;
use Modules\Classroom\Http\Resources\ClassroomPostResource;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Models\ClassroomPost;
use Modules\Classroom\Services\Contracts\ClassroomServiceInterface;

class ClassroomController extends Controller
{
    public function __construct(
        protected ClassroomServiceInterface $classroomService,
        protected LessonQuotaService $quota
    ) {
    }

    /**
     * Display a listing of classrooms.
     */
    public function index(Request $request)
    {
        $classrooms = $this->classroomService->getUserClassrooms($request->user());

        return view('classroom::index', compact('classrooms'));
    }

    /**
     * Store a newly created classroom.
     *
     * Only admins/teachers pass the ClassroomPolicy::create check.
     * Within that group, admins are still unlimited; teachers fall
     * under the same per-user lesson quota as students.
     */
    public function store(StoreClassroomRequest $request)
    {
        $this->authorize('create', Classroom::class);

        $user = $request->user();
        $check = $this->quota->check($user, LessonRequest::TYPE_CLASSROOM);

        if (! $check['allowed']) {
            return back()->with('error', "Bạn đã tạo {$check['used']}/{$check['limit']} lớp học hôm nay. Gửi yêu cầu xin thêm cho admin để được nâng giới hạn.")
                         ->withInput();
        }

        $this->classroomService->createClassroom($request->validated(), $user);

        return back()->with('success', 'Classroom created successfully!');
    }

    /**
     * Show a specific classroom (Feed).
     */
    public function show(Classroom $classroom)
    {
        $this->authorize('view', $classroom);

        $classroom = $this->classroomService->getClassroomFeed($classroom);

        return view('classroom::show', compact('classroom'));
    }

    /**
     * Store a comment on a classroom post.
     */
    public function storeComment(StoreClassroomCommentRequest $request, ClassroomPost $post)
    {
        $comment = $this->classroomService->createComment($post, $request->validated(), $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'comment' => (new ClassroomCommentResource($comment))->resolve(),
            ]);
        }

        return back()->with('success', 'Comment added!');
    }

    /**
     * Student join a classroom.
     */
    public function join(JoinClassroomRequest $request)
    {
        $classroom = $this->classroomService->joinClassroom($request->validated('invite_code'), $request->user());

        return redirect()->route('classroom.show', $classroom->id)->with('success', 'Joined class successfully!');
    }

    /**
     * Store a post in the classroom.
     */
    public function storePost(StoreClassroomPostRequest $request, Classroom $classroom)
    {
        $post = $this->classroomService->createPost($classroom, $request->validated(), $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'post' => (new ClassroomPostResource($post))->resolve(),
            ]);
        }

        return back()->with('success', 'Post published successfully!');
    }

    /**
     * Store feedback for a student's pronunciation task.
     */
    public function storeFeedback(StoreClassroomFeedbackRequest $request, ClassroomPost $post)
    {
        $this->classroomService->addFeedback($post, $request->validated(), $request->user());

        return back()->with('success', 'Feedback submitted!');
    }
}
