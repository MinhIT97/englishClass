<?php

namespace Modules\TelegramBot\Database\Seeders;

use App\Support\IeltsTopicCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Question\Models\Question;

/**
 * Seeds a starter reading-passage library.
 *
 * For every IELTS topic in IeltsTopicCatalog we create one passage with
 * three MCQ questions. The passage is linked to the matching tgb_topics
 * row by slug (purpose=ielts), so the roadmap can find it.
 *
 * Important: the questions we create are *only* for the reading-review
 * flow — they should NOT appear in the legacy "Reading Drills" random
 * practice (PracticeController::loadDrill('reading')), which pulls
 * individual questions. To keep them out, we tag each question with
 * source = 'reading-passage:<passage_slug>' and scope by that. The
 * SampleQuestionSeeder in Modules/Question uses source = 'sample-bank',
 * so the two pools don't overlap by accident.
 *
 * Idempotent: re-running the seeder doesn't duplicate passages or
 * questions (we look up by 4-tuple key + content fingerprint).
 */
class ReadingPassageSeeder extends Seeder
{
    /** Prefix on Question.source so PracticeController can filter. */
    public const SOURCE_PREFIX = 'reading-passage:';

    public function run(): void
    {
        $catalog = IeltsTopicCatalog::all();

        $topicSlugByName = [];
        foreach ($catalog as $name => $_) {
            $topicSlugByName[$name] = 'ielts-' . Str::slug($name, '-');
        }

        $existingTopicIds = DB::table('tgb_topics')
            ->whereIn('slug', array_values($topicSlugByName))
            ->pluck('id', 'slug');

        $created = 0;
        $skipped = 0;

        foreach ($catalog as $name => $data) {
            $slug = $topicSlugByName[$name];
            $topicId = $existingTopicIds[$slug] ?? null;

            $passageSlug = 'rp-' . Str::slug($name, '-');
            $existingPassageId = DB::table('reading_passages')
                ->where('slug', $passageSlug)
                ->value('id');

            if ($existingPassageId) {
                $skipped++;
                continue;
            }

            $body = $this->buildPassageBody($name, $data['vocabulary']);
            $wordCount = str_word_count(strip_tags($body));
            $source = self::SOURCE_PREFIX . $passageSlug;

            $passageId = DB::table('reading_passages')->insertGetId([
                'slug' => $passageSlug,
                'title' => 'Reading: ' . $name,
                'body' => $body,
                'source' => 'IELTS Sample Bank',
                'difficulty' => 'medium',
                'word_count' => $wordCount,
                'estimated_minutes' => max(3, (int) round($wordCount / 200)),
                'tags' => json_encode([$name, 'ielts']),
                'topic_id' => $topicId,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $questions = $this->buildQuestionsForTopic($name, $data['vocabulary']);
            foreach ($questions as $i => $q) {
                // Lookup key: (skill, type, source) + content fingerprint.
                // We don't include 'topic' because two passages on the same
                // topic (e.g. 2 different difficulty levels) would collide.
                // Source is our namespace, content->answer is the fingerprint.
                $question = Question::query()
                    ->where('skill', 'reading')
                    ->where('type', $q['type'])
                    ->where('source', $source)
                    ->where('content->answer', $q['content']['answer'])
                    ->first();

                if (! $question) {
                    $question = Question::query()->create([
                        'skill' => 'reading',
                        'type' => $q['type'],
                        'topic' => $name,
                        'difficulty' => $q['difficulty'],
                        'source' => $source,
                        'content' => $q['content'],
                    ]);
                }

                DB::table('reading_passage_questions')->insertOrIgnore([
                    'reading_passage_id' => $passageId,
                    'question_id' => $question->id,
                    'order_index' => $i + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $created++;
        }

        $this->command?->info("ReadingPassageSeeder: created={$created} skipped={$skipped}");
    }

    /**
     * Build a multi-paragraph passage that reuses the topic's vocabulary
     * so the questions feel grounded in the text.
     */
    private function buildPassageBody(string $topic, array $vocabulary): string
    {
        [$a, $b, $c] = array_pad($vocabulary, 3, 'long-term planning');
        $topicLower = strtolower($topic);

        return <<<TEXT
There has been a great deal of public discussion about {$topicLower} in recent years. Specialists now agree that the most important issues are interconnected, and that short-term fixes rarely produce durable results. Recent reports, for example, point out that {$a} is one of the most visible symptoms of the broader problem, but it is not, by itself, a cause.

A second line of research focuses on {$b}. Several long-term studies show that improvements in this area have a positive effect on overall well-being, even when the immediate economic indicators are mixed. According to the same studies, communities that invest early in {$b} tend to adapt more easily to change than those that wait for outside support.

The third and perhaps most surprising factor is {$c}. Although it is often overlooked in policy debates, evidence from a number of pilot programmes suggests that {$c} can be the most cost-effective lever when governments are looking for results that last. The challenge, as the authors of one major report put it, is to design systems in which {$c} is supported on a permanent basis, not merely as a reaction to crisis.

In conclusion, the passage argues that the three factors must be addressed together. Treating {$a} in isolation, ignoring {$b}, or underfunding {$c} will not, on its own, lead to the kind of progress that the public is looking for.
TEXT;
    }

    /**
     * Three MCQ questions per passage, each with a clear, single correct
     * answer that is supported by a specific sentence in the body.
     */
    private function buildQuestionsForTopic(string $topic, array $vocabulary): array
    {
        [$a, $b, $c] = array_pad($vocabulary, 3, 'long-term planning');

        return [
            [
                'type' => 'mcq',
                'difficulty' => 'easy',
                'content' => [
                    'text' => 'According to the passage, what does the writer say about ' . $a . '?',
                    'question' => 'According to the passage, what does the writer say about ' . $a . '?',
                    'answer' => 'It is a visible symptom, not a cause on its own.',
                    'options' => [
                        'It is a visible symptom, not a cause on its own.',
                        'It is the primary cause of the broader problem.',
                        'It has no connection to the broader problem.',
                        'It is the only issue that needs funding.',
                    ],
                    'explanation' => 'The first paragraph states that ' . $a . ' is one of the most visible symptoms but not, by itself, a cause.',
                ],
            ],
            [
                'type' => 'mcq',
                'difficulty' => 'medium',
                'content' => [
                    'text' => 'What do long-term studies say about communities that invest in ' . $b . '?',
                    'question' => 'What do long-term studies say about communities that invest in ' . $b . '?',
                    'answer' => 'They adapt more easily to change.',
                    'options' => [
                        'They adapt more easily to change.',
                        'They perform worse in economic indicators.',
                        'They rarely see any benefit at all.',
                        'They depend heavily on outside support.',
                    ],
                    'explanation' => 'The second paragraph says that communities investing in ' . $b . ' tend to adapt more easily to change than those that wait for outside support.',
                ],
            ],
            [
                'type' => 'mcq',
                'difficulty' => 'hard',
                'content' => [
                    'text' => 'Why does the writer single out ' . $c . ' as the most surprising factor?',
                    'question' => 'Why does the writer single out ' . $c . ' as the most surprising factor?',
                    'answer' => 'Because it is often overlooked but pilot programmes show it is the most cost-effective lever.',
                    'options' => [
                        'Because it is often overlooked but pilot programmes show it is the most cost-effective lever.',
                        'Because it is mentioned more often than the other factors.',
                        'Because it is the cheapest option for governments.',
                        'Because it has been the focus of debate for decades.',
                    ],
                    'explanation' => 'The third paragraph says that ' . $c . ' is often overlooked in policy debates, but pilot programmes show it can be the most cost-effective lever for lasting results.',
                ],
            ],
        ];
    }
}
