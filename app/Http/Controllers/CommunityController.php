<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\StudyNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommunityController extends Controller
{
    public function notesIndex(Request $request): View
    {
        $notes = StudyNote::query()
            ->where('is_public', true)
            ->with('user')
            ->latest()
            ->paginate(20);

        return view('community.notes', compact('notes'));
    }

    public function noteStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'content' => ['required', 'string', 'max:5000'],
            'is_public' => ['nullable', 'boolean'],
        ]);

        StudyNote::create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'content' => $data['content'],
            'is_public' => (bool) ($data['is_public'] ?? false),
        ]);

        return back()->with('success', 'Đã lưu ghi chú.');
    }

    public function commentStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'commentable_type' => ['required', 'string'],
            'commentable_id' => ['required', 'integer'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        Comment::create([
            'user_id' => $request->user()->id,
            'commentable_type' => $data['commentable_type'],
            'commentable_id' => $data['commentable_id'],
            'body' => $data['body'],
        ]);

        return back()->with('success', 'Đã đăng bình luận.');
    }

    /**
     * Stub for "match me with a buddy" — pairs the user with another
     * active learner at the same target_band. Naive matching for now.
     */
    public function findBuddy(Request $request): JsonResponse
    {
        $user = $request->user();
        $match = \App\Models\User::query()
            ->where('role', 'student')
            ->where('status', 'active')
            ->where('id', '!=', $user->id)
            ->where('target_band', $user->target_band)
            ->inRandomOrder()
            ->first();

        if (! $match) {
            return response()->json(['matched' => false]);
        }

        return response()->json([
            'matched' => true,
            'buddy' => [
                'id' => $match->id,
                'name' => $match->name,
                'target_band' => $match->target_band,
            ],
        ]);
    }
}