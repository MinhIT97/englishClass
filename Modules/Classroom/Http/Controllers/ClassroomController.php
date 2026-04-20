<?php

namespace Modules\Classroom\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Modules\Classroom\Models\Classroom;
use Illuminate\Support\Str;

class ClassroomController extends Controller
{
    /**
     * Display a listing of classrooms.
     */
    public function index()
    {
        $user = auth()->user();
        if ($user->role === 'admin' || $user->role === 'teacher') {
            $classrooms = Classroom::where('teacher_id', $user->id)->get();
        } else {
            $classrooms = $user->classrooms; // Assuming relationship in User model
        }

        return view('classroom::index', compact('classrooms'));
    }

    /**
     * Store a newly created classroom.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        Classroom::create([
            'name' => $request->name,
            'description' => $request->description,
            'teacher_id' => auth()->id(),
            'invite_code' => strtoupper(Str::random(6)),
        ]);

        return back()->with('success', 'Classroom created successfully!');
    }

    /**
     * Show a specific classroom (Feed).
     */
    public function show($id)
    {
        $classroom = Classroom::with(['posts.user', 'students'])->findOrFail($id);
        
        return view('classroom::show', compact('classroom'));
    }

    /**
     * Student join a classroom.
     */
    public function join(Request $request)
    {
        $request->validate([
            'invite_code' => 'required|string',
        ]);

        $classroom = Classroom::where('invite_code', strtoupper($request->invite_code))->first();

        if (!$classroom) {
            return back()->withErrors(['invite_code' => 'Invalid invite code.']);
        }

        auth()->user()->classrooms()->syncWithoutDetaching([$classroom->id]);

        return redirect()->route('classroom.show', $classroom->id)->with('success', 'Joined class successfully!');
    }

    /**
     * Store a post in the classroom.
     */
    public function storePost(Request $request, $id)
    {
        $classroom = Classroom::findOrFail($id);
        
        // Authorization check (only teacher or enrolled student depending on settings)
        // For now, let's allow everyone in the class to post like a group
        
        $request->validate([
            'content' => 'required|string',
            'type' => 'required|string|in:announcement,schedule,meeting',
        ]);

        $classroom->posts()->create([
            'user_id' => auth()->id(),
            'content' => $request->content,
            'type' => $request->type,
        ]);

        return back()->with('success', 'Announcement posted!');
    }
}
