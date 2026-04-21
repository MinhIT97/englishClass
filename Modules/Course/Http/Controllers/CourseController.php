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
    public function index(Request $request)
    {
        $courses = $this->service->paginate($request->get('limit', 12));
        
        if ($request->expectsJson() || $request->ajax()) {
            return CourseResource::collection($courses);
        }

        return view('course::index', compact('courses'));
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
        
        if (request()->expectsJson() || request()->ajax()) {
            return new CourseResource($course);
        }

        $isEnrolled = auth()->user()->enrolledCourses()->where('course_id', $id)->exists();

        return view('course::show', compact('course', 'isEnrolled'));
    }

    /**
     * Enroll in the specified course.
     */
    public function enroll(Request $request, $id)
    {
        $course = $this->service->find($id);
        $user = auth()->user();

        if ($user->enrolledCourses()->where('course_id', $id)->exists()) {
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
