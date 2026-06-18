<?php

namespace Modules\Classroom\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LessonRequest;
use App\Services\LessonQuotaService;
use Modules\Classroom\Services\Contracts\ClassroomServiceInterface;
use Modules\Classroom\Http\Requests\StoreClassroomRequest;
use Modules\Classroom\Http\Resources\ClassroomResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassroomController extends Controller
{
    protected $classroomService;

    public function __construct(
        ClassroomServiceInterface $classroomService,
        protected LessonQuotaService $quota
    ) {
        $this->classroomService = $classroomService;
    }

    /**
     * Display a listing of accessible classrooms via API.
     */
    public function index(Request $request): JsonResponse
    {
        $classrooms = $this->classroomService->getUserClassrooms($request->user());

        return response()->json([
            'data' => ClassroomResource::collection($classrooms)
        ]);
    }

    /**
     * Store a newly created classroom via API.
     */
    public function store(StoreClassroomRequest $request): JsonResponse
    {
        $user = $request->user();
        $check = $this->quota->check($user, LessonRequest::TYPE_CLASSROOM);

        if (! $check['allowed']) {
            return response()->json([
                'message' => 'Bạn đã đạt giới hạn tạo lớp học trong ngày.',
                'reason' => $check['reason'],
                'used' => $check['used'],
                'limit' => $check['limit'],
            ], 403);
        }

        $classroom = $this->classroomService->createClassroom(
            $request->validated(),
            $user
        );

        return response()->json([
            'message' => 'Classroom created successfully!',
            'data' => new ClassroomResource($classroom)
        ], 201);
    }
}
