<?php

namespace Modules\IeltsSet\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\IeltsSet\Http\Requests\UpsertIeltsSetRequest;
use Modules\IeltsSet\Models\IeltsSet;
use Modules\IeltsSet\Models\IeltsSetSection;
use Modules\Question\Models\Question;

class AdminIeltsSetController extends Controller
{
    public function index()
    {
        $sets = IeltsSet::query()
            ->withCount(['sections', 'attempts'])
            ->orderByDesc('updated_at')
            ->paginate(12);

        return view('ieltset::admin.index', compact('sets'));
    }

    public function create()
    {
        return view('ieltset::admin.form', [
            'set' => new IeltsSet([
                'set_type' => 'full',
                'difficulty' => 'medium',
                'duration_minutes' => 165,
                'target_band' => '6.0-7.0',
            ]),
            'formAction' => route('admin.sets.store'),
            'formMethod' => 'POST',
            'questionsBySkill' => $this->questionsBySkill(),
            'formSections' => old('sections', []),
        ]);
    }

    public function store(UpsertIeltsSetRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            $set = IeltsSet::query()->create($this->setPayload($data));
            $this->syncSections($set, $data['sections'], false);
        });

        return redirect()
            ->route('admin.sets.index')
            ->with('success', 'IELTS set created successfully.');
    }

    public function edit(IeltsSet $set)
    {
        $set->load(['sections.questions', 'attempts']);

        $formSections = old('sections', $set->sections
            ->sortBy('section_order')
            ->values()
            ->map(function (IeltsSetSection $section) {
                return [
                    'id' => $section->id,
                    'skill' => $section->skill,
                    'title' => $section->title,
                    'instructions' => $section->instructions,
                    'time_limit_minutes' => $section->time_limit_minutes,
                    'question_ids' => $section->questions->pluck('id')->all(),
                ];
            })
            ->all());

        return view('ieltset::admin.form', [
            'set' => $set,
            'formAction' => route('admin.sets.update', $set),
            'formMethod' => 'PUT',
            'questionsBySkill' => $this->questionsBySkill(),
            'formSections' => $formSections,
        ]);
    }

    public function update(UpsertIeltsSetRequest $request, IeltsSet $set): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $set) {
            $set->update($this->setPayload($data));
            $this->syncSections($set->fresh('sections.questions', 'attempts'), $data['sections'], true);
        });

        return redirect()
            ->route('admin.sets.index')
            ->with('success', 'IELTS set updated successfully.');
    }

    public function destroy(IeltsSet $set): RedirectResponse
    {
        $set->delete();

        return redirect()
            ->route('admin.sets.index')
            ->with('success', 'IELTS set deleted successfully.');
    }

    private function setPayload(array $data): array
    {
        return [
            'title' => $data['title'],
            'slug' => Str::slug($data['slug']),
            'topic' => $data['topic'],
            'set_type' => $data['set_type'],
            'target_band' => $data['target_band'],
            'skill_focus' => implode(',', $data['skill_focus']),
            'description' => $data['description'] ?? null,
            'difficulty' => $data['difficulty'],
            'duration_minutes' => (int) $data['duration_minutes'],
            'is_published' => (bool) ($data['is_published'] ?? false),
            'total_questions' => collect($data['sections'])->sum(fn (array $section) => count($section['question_ids'] ?? [])),
        ];
    }

    private function syncSections(IeltsSet $set, array $sections, bool $isUpdate): void
    {
        $existingSections = $set->sections()->get()->keyBy('id');
        $submittedSectionIds = collect($sections)
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($isUpdate && $set->attempts()->exists()) {
            $missingSectionIds = $existingSections->keys()->diff($submittedSectionIds);

            if ($missingSectionIds->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'sections' => 'You cannot remove existing sections after learners have started attempts on this set.',
                ]);
            }
        }

        foreach (array_values($sections) as $index => $sectionData) {
            $questionIds = collect($sectionData['question_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $section = null;
            $existingId = isset($sectionData['id']) ? (int) $sectionData['id'] : null;

            if ($existingId && $existingSections->has($existingId)) {
                $section = $existingSections->get($existingId);

                if ($set->attempts()->exists() && $section->skill !== $sectionData['skill']) {
                    throw ValidationException::withMessages([
                        'sections' => 'You cannot change the skill of an existing section after attempts have started.',
                    ]);
                }

                $section->update([
                    'skill' => $sectionData['skill'],
                    'title' => $sectionData['title'],
                    'instructions' => $sectionData['instructions'] ?? null,
                    'section_order' => $index + 1,
                    'time_limit_minutes' => $sectionData['time_limit_minutes'] ?? null,
                ]);
            } else {
                $section = $set->sections()->create([
                    'skill' => $sectionData['skill'],
                    'title' => $sectionData['title'],
                    'instructions' => $sectionData['instructions'] ?? null,
                    'section_order' => $index + 1,
                    'time_limit_minutes' => $sectionData['time_limit_minutes'] ?? null,
                ]);
            }

            $pivot = [];
            foreach ($questionIds as $questionIndex => $questionId) {
                $pivot[$questionId] = ['question_order' => $questionIndex + 1];
            }

            $section->questions()->sync($pivot);
        }

        if ($isUpdate && !$set->attempts()->exists()) {
            $existingSections
                ->reject(fn (IeltsSetSection $section) => $submittedSectionIds->contains($section->id))
                ->each(fn (IeltsSetSection $section) => $section->delete());
        }
    }

    private function questionsBySkill(): array
    {
        return Question::query()
            ->orderBy('skill')
            ->orderBy('topic')
            ->orderBy('id')
            ->get()
            ->groupBy('skill')
            ->map(function ($questions) {
                return $questions->map(function (Question $question) {
                    $prompt = $question->content['question'] ?? $question->content['text'] ?? 'Question prompt';

                    return [
                        'id' => $question->id,
                        'label' => sprintf(
                            '#%d | %s | %s | %s',
                            $question->id,
                            ucfirst($question->type),
                            $question->topic,
                            Str::limit($prompt, 90)
                        ),
                    ];
                })->values()->all();
            })
            ->toArray();
    }
}
