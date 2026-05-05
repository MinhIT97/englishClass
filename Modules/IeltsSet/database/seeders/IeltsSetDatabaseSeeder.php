<?php

namespace Modules\IeltsSet\database\seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Modules\IeltsSet\Models\IeltsSet;
use Modules\IeltsSet\Models\IeltsSetSection;
use Modules\Question\Models\Question;

class IeltsSetDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $readingQuestions = Question::query()->forSkill('reading')->orderBy('id')->get();
        $listeningQuestions = Question::query()->forSkill('listening')->orderBy('id')->get();
        $writingQuestions = Question::query()->forSkill('writing')->orderBy('id')->get();
        $speakingQuestions = Question::query()->forSkill('speaking')->orderBy('id')->get();

        $sets = $this->buildAcademicMockSets(
            $readingQuestions,
            $listeningQuestions,
            $writingQuestions,
            $speakingQuestions
        );

        foreach ($sets as $data) {
            $sectionPayloads = $data['sections'];
            unset($data['sections']);

            $data['total_questions'] = collect($sectionPayloads)->sum(fn (array $section) => count($section['questions']));

            $set = IeltsSet::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );

            $set->sections()->delete();

            foreach ($sectionPayloads as $index => $sectionData) {
                $questionIds = $sectionData['questions'];
                unset($sectionData['questions']);

                $section = IeltsSetSection::create([
                    'ielts_set_id' => $set->id,
                    'skill' => $sectionData['skill'],
                    'title' => $sectionData['title'],
                    'instructions' => $sectionData['instructions'],
                    'section_order' => $index + 1,
                    'time_limit_minutes' => $sectionData['time_limit_minutes'],
                ]);

                $pivot = [];
                foreach ($questionIds as $questionIndex => $questionId) {
                    $pivot[$questionId] = ['question_order' => $questionIndex + 1];
                }

                if ($pivot !== []) {
                    $section->questions()->sync($pivot);
                }
            }
        }
    }

    private function buildAcademicMockSets(
        Collection $readingQuestions,
        Collection $listeningQuestions,
        Collection $writingQuestions,
        Collection $speakingQuestions
    ): array {
        $blueprints = [
            [
                'slug' => 'reading-listening-sprint',
                'title' => 'IELTS Academic Mock Test 01',
                'topic' => 'Education, Technology & Society',
                'target_band' => '5.5-6.5',
                'difficulty' => 'medium',
                'topics' => ['Education', 'Technology', 'Education Technology', 'Social Media'],
            ],
            [
                'slug' => 'writing-ideas-builder',
                'title' => 'IELTS Academic Mock Test 02',
                'topic' => 'Environment, Government & Science',
                'target_band' => '6.0-7.0',
                'difficulty' => 'medium',
                'topics' => ['Environment', 'Government', 'Science', 'Transport'],
            ],
            [
                'slug' => 'speaking-confidence-pack',
                'title' => 'IELTS Academic Mock Test 03',
                'topic' => 'Health, Food & Well-being',
                'target_band' => '5.5-6.5',
                'difficulty' => 'medium',
                'topics' => ['Health', 'Food', 'Sports', 'Happiness'],
            ],
            [
                'slug' => 'full-skills-starter-set',
                'title' => 'IELTS Academic Mock Test 04',
                'topic' => 'Work, Advertising & Family',
                'target_band' => '6.5-7.5',
                'difficulty' => 'hard',
                'topics' => ['Work & Career', 'Advertising', 'Family', 'Social Media'],
            ],
            [
                'slug' => 'ielts-academic-mock-test-05',
                'title' => 'IELTS Academic Mock Test 05',
                'topic' => 'Travel, Culture & Globalization',
                'target_band' => '6.0-7.0',
                'difficulty' => 'medium',
                'topics' => ['Travel', 'Culture', 'Globalization', 'Housing'],
            ],
            [
                'slug' => 'ielts-academic-mock-test-06',
                'title' => 'IELTS Academic Mock Test 06',
                'topic' => 'Crime, Policy & Public Life',
                'target_band' => '6.5-7.5',
                'difficulty' => 'hard',
                'topics' => ['Crime', 'Government', 'Transport', 'Globalization'],
            ],
            [
                'slug' => 'ielts-academic-mock-test-07',
                'title' => 'IELTS Academic Mock Test 07',
                'topic' => 'Housing, Cities & Infrastructure',
                'target_band' => '5.5-6.5',
                'difficulty' => 'medium',
                'topics' => ['Housing', 'Transport', 'Environment', 'Work & Career'],
            ],
            [
                'slug' => 'ielts-academic-mock-test-08',
                'title' => 'IELTS Academic Mock Test 08',
                'topic' => 'Science, Innovation & Education',
                'target_band' => '6.5-7.5',
                'difficulty' => 'hard',
                'topics' => ['Science', 'Technology', 'Education', 'Education Technology'],
            ],
        ];

        $taskOnePool = $writingQuestions->where('type', 'task_1')->values();
        $sets = [];

        foreach ($blueprints as $index => $blueprint) {
            $topics = $blueprint['topics'];

            $sets[] = [
                'title' => $blueprint['title'],
                'slug' => $blueprint['slug'],
                'topic' => $blueprint['topic'],
                'set_type' => 'full',
                'target_band' => $blueprint['target_band'],
                'skill_focus' => 'reading,listening,writing,speaking',
                'description' => 'An IELTS Academic-style mock set with listening parts, reading passages, writing tasks, and speaking interview stages.',
                'difficulty' => $blueprint['difficulty'],
                'duration_minutes' => 165,
                'is_published' => true,
                'sections' => [
                    [
                        'skill' => 'listening',
                        'title' => 'Listening Part 1',
                        'instructions' => 'Listen for factual details and complete the missing information accurately.',
                        'time_limit_minutes' => 8,
                        'questions' => $this->collectQuestionIdsByTopics($listeningQuestions, [$topics[0]], null, 3),
                    ],
                    [
                        'skill' => 'listening',
                        'title' => 'Listening Part 2',
                        'instructions' => 'Follow the talk carefully and note the speaker’s key recommendations.',
                        'time_limit_minutes' => 8,
                        'questions' => $this->collectQuestionIdsByTopics($listeningQuestions, [$topics[1]], null, 3),
                    ],
                    [
                        'skill' => 'listening',
                        'title' => 'Listening Part 3',
                        'instructions' => 'Track the discussion and identify the important points made by the speakers.',
                        'time_limit_minutes' => 8,
                        'questions' => $this->collectQuestionIdsByTopics($listeningQuestions, [$topics[2]], null, 3),
                    ],
                    [
                        'skill' => 'listening',
                        'title' => 'Listening Part 4',
                        'instructions' => 'Listen to the lecture and write the exact answer forms in the gaps.',
                        'time_limit_minutes' => 8,
                        'questions' => $this->collectQuestionIdsByTopics($listeningQuestions, [$topics[3]], null, 3),
                    ],
                    [
                        'skill' => 'reading',
                        'title' => 'Reading Passage 1',
                        'instructions' => 'Read the first passage and choose the most accurate answer for each question.',
                        'time_limit_minutes' => 20,
                        'questions' => $this->collectQuestionIdsByTopics($readingQuestions, [$topics[0]], null, 3),
                    ],
                    [
                        'skill' => 'reading',
                        'title' => 'Reading Passage 2',
                        'instructions' => 'Read the second passage carefully and focus on key ideas and supporting evidence.',
                        'time_limit_minutes' => 20,
                        'questions' => $this->collectQuestionIdsByTopics($readingQuestions, [$topics[1]], null, 3),
                    ],
                    [
                        'skill' => 'reading',
                        'title' => 'Reading Passage 3',
                        'instructions' => 'Handle the final passage with close attention to the writer’s main claims and conclusions.',
                        'time_limit_minutes' => 20,
                        'questions' => $this->collectQuestionIdsByTopics($readingQuestions, [$topics[2], $topics[3]], null, 3),
                    ],
                    [
                        'skill' => 'writing',
                        'title' => 'Writing Task 1',
                        'instructions' => 'Write an objective summary of the visual data, highlighting the main trends and comparisons.',
                        'time_limit_minutes' => 20,
                        'questions' => $this->pickTaskOneQuestionIds($taskOnePool, $index),
                    ],
                    [
                        'skill' => 'writing',
                        'title' => 'Writing Task 2',
                        'instructions' => 'Write a clear, well-developed essay with a direct position and relevant examples.',
                        'time_limit_minutes' => 40,
                        'questions' => $this->collectQuestionIdsByTopics($writingQuestions, [$topics[0]], 'task_2', 1),
                    ],
                    [
                        'skill' => 'speaking',
                        'title' => 'Speaking Part 1',
                        'instructions' => 'Answer familiar questions naturally and extend each answer with a detail or example.',
                        'time_limit_minutes' => 4,
                        'questions' => $this->collectQuestionIdsByTopics($speakingQuestions, [$topics[0]], 'part_1', 1),
                    ],
                    [
                        'skill' => 'speaking',
                        'title' => 'Speaking Part 2',
                        'instructions' => 'Prepare your ideas briefly, then speak at length with a clear structure and relevant details.',
                        'time_limit_minutes' => 4,
                        'questions' => $this->collectQuestionIdsByTopics($speakingQuestions, [$topics[1]], 'part_2', 1),
                    ],
                    [
                        'skill' => 'speaking',
                        'title' => 'Speaking Part 3',
                        'instructions' => 'Discuss broader issues, compare viewpoints, and justify your opinion clearly.',
                        'time_limit_minutes' => 7,
                        'questions' => $this->collectQuestionIdsByTopics($speakingQuestions, [$topics[2], $topics[3]], 'part_3', 2),
                    ],
                ],
            ];
        }

        return $sets;
    }

    private function pickTaskOneQuestionIds(Collection $taskOnePool, int $index): array
    {
        $question = $taskOnePool->get($index % max(1, $taskOnePool->count()));

        return $question ? [$question->id] : [];
    }

    private function collectQuestionIdsByTopics(
        Collection $questions,
        array $topics,
        ?string $type,
        int $limit
    ): array {
        $filtered = $questions
            ->when($type, fn (Collection $collection) => $collection->where('type', $type))
            ->whereIn('topic', $topics)
            ->take($limit)
            ->pluck('id')
            ->values()
            ->all();

        if (count($filtered) >= $limit) {
            return $filtered;
        }

        $fallback = $questions
            ->when($type, fn (Collection $collection) => $collection->where('type', $type))
            ->pluck('id')
            ->values()
            ->all();

        return array_slice(array_values(array_unique(array_merge($filtered, $fallback))), 0, $limit);
    }
}
