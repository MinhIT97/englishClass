<?php

namespace Modules\Course\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Course\Http\Requests\CourseRequest;
use Modules\Course\Http\Resources\CourseResource;
use Modules\Course\Services\CourseService;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    protected $service;

    public function __construct(CourseService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $courses = $this->service->paginate(request('limit', 15));
        return CourseResource::collection($courses);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CourseRequest $request)
    {
        $course = $this->service->create($request->validated());
        return new CourseResource($course);
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        $course = $this->service->find($id);
        return new CourseResource($course);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CourseRequest $request, $id)
    {
        $course = $this->service->update($id, $request->validated());
        return new CourseResource($course);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $this->service->delete($id);
        return response()->json(['message' => 'Course deleted successfully']);
    }
}
