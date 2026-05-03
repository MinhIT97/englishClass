<?php

namespace Modules\IeltsSet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Question\Models\Question;

class UpsertIeltsSetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin-access') ?? false;
    }

    public function rules(): array
    {
        $setId = $this->route('set')?->id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('ielts_sets', 'slug')->ignore($setId)],
            'topic' => ['required', 'string', 'max:255'],
            'set_type' => ['required', Rule::in(['full', 'skill'])],
            'target_band' => ['required', 'string', 'max:50'],
            'skill_focus' => ['required', 'array', 'min:1'],
            'skill_focus.*' => ['required', Rule::in(['reading', 'listening', 'writing', 'speaking'])],
            'description' => ['nullable', 'string'],
            'difficulty' => ['required', Rule::in(['easy', 'medium', 'hard'])],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'is_published' => ['nullable', 'boolean'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.id' => ['nullable', 'integer'],
            'sections.*.skill' => ['required', Rule::in(['reading', 'listening', 'writing', 'speaking'])],
            'sections.*.title' => ['required', 'string', 'max:255'],
            'sections.*.instructions' => ['nullable', 'string'],
            'sections.*.time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:240'],
            'sections.*.question_ids' => ['required', 'array', 'min:1'],
            'sections.*.question_ids.*' => ['required', 'integer', Rule::exists('questions', 'id')],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $sections = $this->input('sections', []);

            foreach ($sections as $index => $section) {
                $skill = $section['skill'] ?? null;
                $questionIds = collect($section['question_ids'] ?? [])
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->values();

                if (!$skill || $questionIds->isEmpty()) {
                    continue;
                }

                $mismatchedCount = Question::query()
                    ->whereIn('id', $questionIds)
                    ->where('skill', '!=', $skill)
                    ->count();

                if ($mismatchedCount > 0) {
                    $validator->errors()->add(
                        "sections.{$index}.question_ids",
                        'All selected questions must match the section skill.'
                    );
                }
            }
        });
    }
}
