<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\Classroom\Models\Classroom;

/**
 * Teacher dashboard — at-a-glance view of all the classrooms the
 * teacher owns. Highlights students who haven't been active for 7+
 * days and surfaces recent submissions needing review.
 */
class TeacherController extends Controller
{
    public function dashboard(Request $request): View
    {
        $teacher = $request->user();

        $classrooms = Classroom::query()
            ->where('teacher_id', $teacher->id)
            ->with(['students', 'posts'])
            ->get();

        $classroomIds = $classrooms->pluck('id');

        // Aggregate stats
        $totalStudents = $classrooms->sum(fn ($c) => $c->students->count());

        $activeLast7Days = DB::table('user_answers')
            ->whereIn('user_id', $classrooms->flatMap(fn ($c) => $c->students->pluck('id'))->unique())
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->distinct('user_id')
            ->count('user_id');

        // At-risk students (in teacher's classes, no activity > 7 days).
        $studentIds = $classrooms->flatMap(fn ($c) => $c->students->pluck('id'))->unique();
        $recentlyActiveIds = DB::table('user_answers')
            ->whereIn('user_id', $studentIds)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->pluck('user_id');

        $atRisk = User::query()
            ->whereIn('id', $studentIds)
            ->whereNotIn('id', $recentlyActiveIds)
            ->where('status', 'active')
            ->limit(10)
            ->get();

        // Recent submissions / posts needing feedback.
        $recentPosts = DB::table('classroom_posts')
            ->whereIn('classroom_id', $classroomIds)
            ->whereNull('feedback_by')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        return view('teacher.dashboard', [
            'classrooms' => $classrooms,
            'totalStudents' => $totalStudents,
            'activeLast7Days' => $activeLast7Days,
            'atRisk' => $atRisk,
            'recentPosts' => $recentPosts,
        ]);
    }
}
