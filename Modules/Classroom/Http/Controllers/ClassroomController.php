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
        $classroom = Classroom::with(['posts.user', 'posts.comments.user', 'students'])->findOrFail($id);
        
        return view('classroom::show', compact('classroom'));
    }

    /**
     * Store a comment on a classroom post.
     */
    public function storeComment(Request $request, $id)
    {
        $post = \Modules\Classroom\Models\ClassroomPost::findOrFail($id);

        $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        $comment = $post->comments()->create([
            'user_id' => auth()->id(),
            'content' => $request->content,
        ]);

        // Load user for broadcast
        $comment->load('user');

        // Broadcast comment
        broadcast(new \App\Events\CommentPublished($comment))->toOthers();

        // Notify post author if it's not the same person
        if ($post->user_id !== auth()->id()) {
            $post->user->notify(new \App\Notifications\ClassroomNotification(
                'New comment on your post',
                auth()->user()->name . ' commented on your post in ' . $post->classroom->name,
                route('classroom.show', $post->classroom_id)
            ));
        }

        return back()->with('success', 'Comment added!');
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
        
        $request->validate([
            'content' => 'required|string',
            'type' => 'required|string|in:announcement,schedule,meeting,material,video,pronunciation',
            'attachment' => 'nullable|file|max:51200', // max 50MB
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('classroom_attachments/' . $classroom->id, 'public');
        }

        $post = $classroom->posts()->create([
            'user_id' => auth()->id(),
            'content' => $request->content,
            'type' => $request->type,
            'attachment_path' => $attachmentPath,
        ]);

        // Broadcast notification
        broadcast(new \App\Events\NewPostPublished($post))->toOthers();

        // Notify all members of the classroom EXCEPT the author
        $members = collect($classroom->students)->merge([$classroom->teacher])->filter(function ($member) {
            return $member && $member->id !== auth()->id();
        });

        if ($members->count() > 0) {
            \Illuminate\Support\Facades\Notification::send($members, new \App\Notifications\ClassroomNotification(
                $classroom->name,
                auth()->user()->name . ' posted a new ' . $request->type,
                route('classroom.show', $classroom->id)
            ));
        }

        return back()->with('success', 'Post published successfully!');
    }

    /**
     * Store feedback for a student's pronunciation task.
     */
    public function storeFeedback(Request $request, $id)
    {
        $post = \Modules\Classroom\Models\ClassroomPost::findOrFail($id);

        // Only teacher or admin can give feedback
        if (auth()->user()->role !== 'admin' && auth()->user()->role !== 'teacher') {
            return back()->with('error', 'Unauthorized.');
        }

        $request->validate([
            'feedback_content' => 'required|string',
            'grade' => 'nullable|string|max:50',
        ]);

        $post->update([
            'feedback_content' => $request->feedback_content,
            'grade' => $request->grade,
            'feedback_by' => auth()->id(),
        ]);

        return back()->with('success', 'Feedback submitted!');
    }
}
