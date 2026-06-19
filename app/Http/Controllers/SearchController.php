<?php

namespace App\Http\Controllers;

use App\Models\StudyNote;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Classroom\Models\Classroom;
use Modules\Course\Models\Course;

/**
 * Global search — looks across courses, classrooms, study notes,
 * users (admin only), and vocabulary entries.
 *
 * Lightweight LIKE-based search. For real semantic search we'd
 * plug in Meilisearch / Typesense here.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $user = $request->user();
        $isAdmin = $user?->isAdmin();
        $groups = [];

        // Courses
        $groups['courses'] = Course::query()
            ->where(function ($qb) use ($q) {
                $qb->where('title', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            })
            ->limit(5)->get(['id', 'title', 'slug'])->toArray();

        // Classrooms the user can see.
        $groups['classrooms'] = Classroom::query()
            ->where('name', 'like', "%{$q}%")
            ->when(! $isAdmin, fn ($qb) => $qb->where(function ($accessible) use ($user) {
                $accessible->where('teacher_id', $user->id)
                    ->orWhereHas('students', fn ($students) => $students->whereKey($user->id));
            }))
            ->limit(5)->get(['id', 'name'])->toArray();

        // Public study notes
        $groups['notes'] = StudyNote::query()
            ->where('is_public', true)
            ->where(function ($qb) use ($q) {
                $qb->where('title', 'like', "%{$q}%")
                    ->orWhere('content', 'like', "%{$q}%");
            })
            ->limit(5)->get(['id', 'title'])->toArray();

        // Users — admins only
        if ($isAdmin) {
            $groups['users'] = User::query()
                ->where(function ($qb) use ($q) {
                    $qb->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                })
                ->limit(5)->get(['id', 'name', 'email', 'role'])->toArray();
        }

        return response()->json(['query' => $q, 'groups' => $groups]);
    }
}
