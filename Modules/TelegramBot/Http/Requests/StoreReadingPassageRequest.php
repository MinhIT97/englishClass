<?php

namespace Modules\TelegramBot\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation rules for creating / updating a ReadingPassage.
 *
 * `questions` is an array of question definitions; the controller will
 * upsert one Question row per entry and link them via
 * reading_passage_questions. The shape mirrors what the admin form sends
 * in (see resources/views/admin/reading-passages/{create,edit}.blade.php).
 */
class StoreReadingPassageRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permission is enforced at the route level (`can:admin-access`).
        return auth()->check();
    }

    public function rules(): array
    {
        $passageId = $this->route('passage')?->id ?? null;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:128',
                'regex:/^[a-z0-9\-]+$/',
                Rule::unique('reading_passages', 'slug')->ignore($passageId),
            ],
            'body' => ['required', 'string', 'min:50', 'max:20000'],
            'source' => ['nullable', 'string', 'max:128'],
            'difficulty' => ['required', Rule::in(['easy', 'medium', 'hard'])],
            'word_count' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:60'],
            'topic_id' => ['nullable', 'integer', 'exists:tgb_topics,id'],
            'is_active' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'string', 'max:255'],

            // Questions array — required at least 1.
            'questions' => ['required', 'array', 'min:1', 'max:20'],
            'questions.*.skill' => ['required', Rule::in(['reading'])],
            'questions.*.type' => ['required', Rule::in(['mcq', 'gap_fill', 'short_answer'])],
            'questions.*.topic' => ['required', 'string', 'max:128'],
            'questions.*.difficulty' => ['required', Rule::in(['easy', 'medium', 'hard'])],
            'questions.*.content' => ['required', 'array'],
            'questions.*.content.question' => ['required', 'string', 'max:1000'],
            'questions.*.content.text' => ['nullable', 'string', 'max:5000'],
            'questions.*.content.answer' => ['required', 'string', 'max:1000'],
            'questions.*.content.options' => ['nullable', 'array', 'min:2', 'max:8'],
            'questions.*.content.options.*' => ['string', 'max:500'],
            'questions.*.content.explanation' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.min' => 'Passage must be at least 50 characters long.',
            'questions.required' => 'A passage must include at least one question.',
            'questions.*.content.answer.required' => 'Every question needs a correct answer.',
            'slug.regex' => 'Slug can only contain lowercase letters, numbers, and dashes.',
        ];
    }

    /**
     * Pre-process the incoming payload: auto-derive slug from title if
     * missing, split tags string into array, coerce word_count from
     * body length if not provided.
     */
    protected function prepareForValidation(): void
    {
        $payload = $this->all();

        if (empty($payload['slug']) && ! empty($payload['title'])) {
            $payload['slug'] = \Illuminate\Support\Str::slug($payload['title']);
        }

        if (! empty($payload['body']) && empty($payload['word_count'])) {
            $payload['word_count'] = str_word_count(strip_tags((string) $payload['body']));
        }

        if (! empty($payload['tags']) && is_string($payload['tags'])) {
            $payload['tags'] = array_values(array_filter(array_map('trim', explode(',', $payload['tags']))));
        }

        if (! isset($payload['is_active'])) {
            $payload['is_active'] = true;
        }

        $this->merge($payload);
    }
}
