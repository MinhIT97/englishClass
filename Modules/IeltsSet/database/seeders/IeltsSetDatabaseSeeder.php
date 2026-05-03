<?php

namespace Modules\IeltsSet\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\IeltsSet\Models\IeltsSet;
use Modules\IeltsSet\Models\IeltsSetSection;
use Modules\Question\Models\Question;

class IeltsSetDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $readingQuestions = Question::query()->where('skill', 'reading')->orderBy('id')->get();
        $listeningQuestions = Question::query()->where('skill', 'listening')->orderBy('id')->get();
        $writingQuestions = Question::query()->where('skill', 'writing')->orderBy('id')->get();
        $speakingQuestions = Question::query()->where('skill', 'speaking')->orderBy('id')->get();

        $sets = [
            [
                'title' => 'Reading & Listening Sprint',
                'slug' => 'reading-listening-sprint',
                'topic' => 'Mixed Essentials',
                'set_type' => 'skill',
                'target_band' => '5.5-6.5',
                'skill_focus' => 'reading,listening',
                'description' => 'A focused starter set for quick daily practice across reading and listening.',
                'difficulty' => 'medium',
                'duration_minutes' => 35,
                'sections' => [
                    [
                        'skill' => 'reading',
                        'title' => 'Reading Warm-Up',
                        'instructions' => 'Work through the reading questions and identify the most accurate answer from the passage.',
                        'time_limit_minutes' => 15,
                        'questions' => $readingQuestions->take(3)->pluck('id')->all(),
                    ],
                    [
                        'skill' => 'listening',
                        'title' => 'Listening Focus',
                        'instructions' => 'Listen carefully and complete each gap with the exact answer you hear.',
                        'time_limit_minutes' => 20,
                        'questions' => $listeningQuestions->take(3)->pluck('id')->all(),
                    ],
                ],
            ],
            [
                'title' => 'Writing Ideas Builder',
                'slug' => 'writing-ideas-builder',
                'topic' => 'Academic Writing',
                'set_type' => 'skill',
                'target_band' => '6.5-7.5',
                'skill_focus' => 'writing',
                'description' => 'A compact set of writing prompts designed to build structure, task response, and argument quality.',
                'difficulty' => 'hard',
                'duration_minutes' => 50,
                'sections' => [
                    [
                        'skill' => 'writing',
                        'title' => 'Task 2 Argument Practice',
                        'instructions' => 'Plan your ideas before writing. Focus on clear position, logical support, and precise vocabulary.',
                        'time_limit_minutes' => 35,
                        'questions' => $writingQuestions->where('type', 'task_2')->take(2)->pluck('id')->values()->all(),
                    ],
                    [
                        'skill' => 'writing',
                        'title' => 'Task 1 Summary Practice',
                        'instructions' => 'Write an overview first, then compare the main features without personal opinion.',
                        'time_limit_minutes' => 15,
                        'questions' => $writingQuestions->where('type', 'task_1')->take(1)->pluck('id')->values()->all(),
                    ],
                ],
            ],
            [
                'title' => 'Speaking Confidence Pack',
                'slug' => 'speaking-confidence-pack',
                'topic' => 'Daily Life & Ideas',
                'set_type' => 'skill',
                'target_band' => '6.0-7.0',
                'skill_focus' => 'speaking',
                'description' => 'A structured speaking set covering short answers, long turns, and discussion questions.',
                'difficulty' => 'medium',
                'duration_minutes' => 30,
                'sections' => [
                    [
                        'skill' => 'speaking',
                        'title' => 'Part 1 and Part 2',
                        'instructions' => 'Answer naturally, extend your responses, and support your ideas with examples.',
                        'time_limit_minutes' => 18,
                        'questions' => $speakingQuestions->take(4)->pluck('id')->all(),
                    ],
                    [
                        'skill' => 'speaking',
                        'title' => 'Part 3 Discussion',
                        'instructions' => 'Develop your reasoning and compare different viewpoints.',
                        'time_limit_minutes' => 12,
                        'questions' => $speakingQuestions->slice(4, 2)->pluck('id')->values()->all(),
                    ],
                ],
            ],
            [
                'title' => 'Full Skills Starter Set',
                'slug' => 'full-skills-starter-set',
                'topic' => 'Balanced Multi-Skill Review',
                'set_type' => 'full',
                'target_band' => '6.5-7.5',
                'skill_focus' => 'reading,listening,writing,speaking',
                'description' => 'A guided multi-skill set that helps students move through all four IELTS components in one sequence.',
                'difficulty' => 'medium',
                'duration_minutes' => 90,
                'sections' => [
                    [
                        'skill' => 'reading',
                        'title' => 'Reading Section',
                        'instructions' => 'Read carefully and manage time across short passages.',
                        'time_limit_minutes' => 20,
                        'questions' => $readingQuestions->take(2)->pluck('id')->all(),
                    ],
                    [
                        'skill' => 'listening',
                        'title' => 'Listening Section',
                        'instructions' => 'Listen once, focus on key words, and write the exact answer form.',
                        'time_limit_minutes' => 20,
                        'questions' => $listeningQuestions->take(2)->pluck('id')->all(),
                    ],
                    [
                        'skill' => 'writing',
                        'title' => 'Writing Section',
                        'instructions' => 'Produce a clear answer with strong organization and relevant support.',
                        'time_limit_minutes' => 30,
                        'questions' => $writingQuestions->take(2)->pluck('id')->all(),
                    ],
                    [
                        'skill' => 'speaking',
                        'title' => 'Speaking Section',
                        'instructions' => 'Speak fluently and expand your ideas with examples and explanations.',
                        'time_limit_minutes' => 20,
                        'questions' => $speakingQuestions->take(2)->pluck('id')->all(),
                    ],
                ],
            ],
        ];

        foreach ($sets as $data) {
            $sectionPayloads = $data['sections'];
            unset($data['sections']);

            $data['total_questions'] = collect($sectionPayloads)->sum(fn ($section) => count($section['questions']));

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
}
