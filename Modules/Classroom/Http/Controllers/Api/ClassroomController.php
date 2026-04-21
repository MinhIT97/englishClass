<?php

namespace Modules\Classroom\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Classroom\Services\Contracts\ClassroomServiceInterface;
use Modules\Classroom\Http\Requests\StoreClassroomRequest;
use Modules\Classroom\Http\Resources\ClassroomResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassroomController extends Controller
{
    protected $classroomService;

    public function __construct(ClassroomServiceInterface $classroomService)
    {
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
        $classroom = $this->classroomService->createClassroom(
            $request->validated(), 
            $request->user()
        );

        return response()->json([
            'message' => 'Classroom created successfully!',
            'data' => new ClassroomResource($classroom)
        ], 201);
    }
}
