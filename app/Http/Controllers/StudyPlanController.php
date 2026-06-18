<?php

namespace App\Http\Controllers;

use App\Models\StudyPlan;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StudyPlanController extends Controller
{
    public function index(Request $request): View
    {
        $start = Carbon::parse($request->query('start', Carbon::now()->startOfMonth()->toDateString()));
        $end = $start->copy()->endOfMonth();

        $plans = StudyPlan::query()
            ->where('user_id', $request->user()->id)
            ->whereBetween('scheduled_at', [$start, $end])
            ->orderBy('scheduled_at')
            ->get();

        return view('study-plan.index', [
            'plans' => $plans,
            'start' => $start,
            'end' => $end,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'scheduled_at' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'type' => ['required', 'in:lesson,mock_test,review,practice,rest'],
        ]);

        StudyPlan::create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'scheduled_at' => Carbon::parse($data['scheduled_at']),
            'duration_minutes' => $data['duration_minutes'] ?? 30,
            'type' => $data['type'],
            'status' => StudyPlan::STATUS_PENDING,
        ]);

        return back()->with('success', 'Đã thêm lịch học.');
    }

    public function complete(Request $request, StudyPlan $plan): JsonResponse
    {
        abort_unless($plan->user_id === $request->user()->id, 403);

        $plan->update([
            'status' => StudyPlan::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, StudyPlan $plan): RedirectResponse
    {
        abort_unless($plan->user_id === $request->user()->id, 403);

        $plan->delete();
        return back()->with('success', 'Đã xoá lịch học.');
    }
}