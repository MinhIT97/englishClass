<?php

namespace Modules\TelegramBot\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Question\Models\Question;
use Modules\TelegramBot\Models\ReadingPassage;
use Modules\TelegramBot\Models\ReadingPassageQuestion;

/**
 * Admin-side CRUD for ReadingPassage.
 *
 * Why a separate service from ReadingPassageService? The user-facing
 * service is a read-only/SRS wrapper; this one owns the write paths
 * (create/update/delete) and the join-table bookkeeping. Keeping them
 * separate makes the read paths cheap and the write paths easy to audit.
 *
 * Question rows are upserted by their (skill, type, topic, content
 * hash) tuple so re-saving a passage doesn't duplicate its questions.
 * In practice we look up an existing question by exact content match
 * and reuse its id; if not found, we create a fresh row.
 */
class ReadingPassageAdminService
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = ReadingPassage::query()->with(['topic']);

        if (! empty($filters['topic_id'])) {
            $query->where('topic_id', (int) $filters['topic_id']);
        }
        if (! empty($filters['difficulty'])) {
            $query->where('difficulty', $filters['difficulty']);
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }
        if (! empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('body', 'like', $term);
            });
        }

        return $query->orderByDesc('id')->paginate($perPage)->withQueryString();
    }

    public function find(int $id): ?ReadingPassage
    {
        return ReadingPassage::query()
            ->with(['topic', 'passageQuestions.question'])
            ->where('id', $id)
            ->first();
    }

    /**
     * Create a passage and attach the supplied questions in a single
     * transaction. Returns the new passage id.
     */
    public function create(array $data): ReadingPassage
    {
        return DB::transaction(function () use ($data) {
            $passage = ReadingPassage::query()->create($this->passageAttributes($data));

            $this->syncQuestions($passage, $data['questions'] ?? []);

            return $passage->fresh(['passageQuestions.question']);
        });
    }

    /**
     * Update a passage. We replace the join-table rows entirely — the
     * admin form is the source of truth for the question list.
     */
    public function update(ReadingPassage $passage, array $data): ReadingPassage
    {
        return DB::transaction(function () use ($passage, $data) {
            $passage->update($this->passageAttributes($data, $passage));

            ReadingPassageQuestion::query()
                ->where('reading_passage_id', $passage->id)
                ->delete();

            $this->syncQuestions($passage, $data['questions'] ?? []);

            return $passage->fresh(['passageQuestions.question']);
        });
    }

    /**
     * Delete a passage. The FK cascade on reading_passage_questions and
     * reading_attempts cleans up child rows automatically. We keep
     * existing per-user ReadingPassageReview rows because users may have
     * progress on the passage; their schedule row will simply point at a
     * now-missing passage (handled by the admin hiding it via is_active
     * rather than hard-deleting).
     */
    public function delete(ReadingPassage $passage): void
    {
        DB::transaction(function () use ($passage) {
            $passage->delete();
        });
    }

    public function toggleActive(ReadingPassage $passage): ReadingPassage
    {
        $passage->is_active = ! $passage->is_active;
        $passage->save();
        return $passage;
    }

    /**
     * Sync the questions attached to a passage. For each incoming
     * question definition we either:
     *   - reuse an existing Question row with the same content hash, or
     *   - create a fresh Question row.
     * Then we insert (or reinsert) the join row with the requested order.
     */
    protected function syncQuestions(ReadingPassage $passage, array $questions): void
    {
        $order = 1;
        foreach ($questions as $definition) {
            $content = $definition['content'] ?? [];
            $contentHash = substr(md5(json_encode([
                'q' => $content['question'] ?? null,
                'a' => $content['answer'] ?? null,
            ])), 0, 12);

            $question = Question::query()
                ->where('skill', $definition['skill'])
                ->where('type', $definition['type'])
                ->where('topic', $definition['topic'])
                ->where('content->answer', $content['answer'] ?? '')
                ->first();

            if (! $question) {
                $question = Question::query()->create([
                    'skill' => $definition['skill'],
                    'type' => $definition['type'],
                    'topic' => $definition['topic'],
                    'difficulty' => $definition['difficulty'],
                    'content' => $content,
                ]);
            }

            ReadingPassageQuestion::query()->updateOrCreate(
                [
                    'reading_passage_id' => $passage->id,
                    'question_id' => $question->id,
                ],
                ['order_index' => $order],
            );
            $order++;
        }
    }

    /**
     * Pull only the column set we want to write to the passage row.
     * Keeping this in one place means a future column addition won't
     * silently leak into the model.
     */
    protected function passageAttributes(array $data, ?ReadingPassage $existing = null): array
    {
        return [
            'title' => $data['title'],
            'slug' => $data['slug'] ?? \Illuminate\Support\Str::slug($data['title']),
            'body' => $data['body'],
            'source' => $data['source'] ?? null,
            'difficulty' => $data['difficulty'],
            'word_count' => $data['word_count'] ?? str_word_count(strip_tags((string) $data['body'])),
            'estimated_minutes' => $data['estimated_minutes'] ?? max(3, (int) round(($data['word_count'] ?? 0) / 200)),
            'topic_id' => $data['topic_id'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'tags' => $data['tags'] ?? null,
        ];
    }
}
