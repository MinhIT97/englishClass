<?php

namespace Modules\TelegramBot\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\TelegramBot\Http\Requests\StoreReadingPassageRequest;
use Modules\TelegramBot\Models\ReadingPassage;
use Modules\TelegramBot\Models\Topic;
use Modules\TelegramBot\Services\ReadingPassageAdminService;

/**
 * Admin CRUD for reading passages.
 *
 *   GET    /admin/reading-passages               -> list + filters
 *   GET    /admin/reading-passages/create        -> form
 *   POST   /admin/reading-passages               -> store
 *   GET    /admin/reading-passages/{passage}/edit -> edit form
 *   PUT    /admin/reading-passages/{passage}     -> update
 *   DELETE /admin/reading-passages/{passage}      -> delete
 *   POST   /admin/reading-passages/{passage}/toggle -> flip is_active
 *
 * Mounted under the `can:admin-access` middleware so only admins reach it.
 */
class ReadingPassageAdminController extends Controller
{
    public function __construct(private readonly ReadingPassageAdminService $service)
    {
    }

    public function index(Request $request)
    {
        $filters = $request->only(['topic_id', 'difficulty', 'is_active', 'search', 'page']);
        $passages = $this->service->paginate($filters, 15);
        $topics = Topic::query()->orderBy('name_en')->get(['id', 'name_en', 'name_vi']);

        return view('telegrambot::admin.reading-passages.index', [
            'passages' => $passages,
            'topics' => $topics,
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        $topics = Topic::query()->orderBy('name_en')->get(['id', 'name_en', 'name_vi']);

        return view('telegrambot::admin.reading-passages.create', [
            'topics' => $topics,
            'passage' => null,
        ]);
    }

    public function store(StoreReadingPassageRequest $request)
    {
        try {
            $passage = $this->service->create($request->validated());
        } catch (\Throwable $e) {
            Log::error('[ReadingPassageAdmin] create failed', ['error' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Could not create passage: ' . $e->getMessage());
        }

        return redirect()->route('admin.reading-passages.index')
            ->with('success', "Passage #{$passage->id} created with " . $passage->passageQuestions->count() . ' question(s).');
    }

    public function edit(ReadingPassage $passage)
    {
        $passage->load(['passageQuestions.question']);
        $topics = Topic::query()->orderBy('name_en')->get(['id', 'name_en', 'name_vi']);

        return view('telegrambot::admin.reading-passages.edit', [
            'passage' => $passage,
            'topics' => $topics,
        ]);
    }

    public function update(StoreReadingPassageRequest $request, ReadingPassage $passage)
    {
        try {
            $this->service->update($passage, $request->validated());
        } catch (\Throwable $e) {
            Log::error('[ReadingPassageAdmin] update failed', [
                'passage_id' => $passage->id,
                'error' => $e->getMessage(),
            ]);
            return back()->withInput()->with('error', 'Could not update passage: ' . $e->getMessage());
        }

        return redirect()->route('admin.reading-passages.index')
            ->with('success', "Passage #{$passage->id} updated.");
    }

    public function destroy(ReadingPassage $passage)
    {
        try {
            $this->service->delete($passage);
        } catch (\Throwable $e) {
            Log::error('[ReadingPassageAdmin] delete failed', [
                'passage_id' => $passage->id,
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'Could not delete passage: ' . $e->getMessage());
        }

        return redirect()->route('admin.reading-passages.index')
            ->with('success', "Passage deleted.");
    }

    public function toggle(ReadingPassage $passage)
    {
        $passage = $this->service->toggleActive($passage);
        $state = $passage->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "Passage #{$passage->id} {$state}.");
    }
}
