<?php

namespace Modules\Question\database\seeders;

use App\Support\IeltsTopicCatalog;
use Illuminate\Database\Seeder;
use Modules\Question\Models\Question;

class SampleQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $questions = array_merge(
            $this->buildTopicDrivenQuestionBank(),
            $this->buildTaskOneQuestionBank()
        );

        foreach ($questions as $question) {
            Question::firstOrCreate($question);
        }
    }

    private function buildTopicDrivenQuestionBank(): array
    {
        $catalog = IeltsTopicCatalog::all();
        $questions = [];
        $difficultyCycle = ['easy', 'medium', 'hard'];
        $readingTemplates = [
            [
                'text' => 'An article about {topic} explains that {a} creates pressure on long-term planning. It also notes that {b} shapes public attitudes. However, the writer concludes that the most durable progress comes from {c}, because it changes behaviour in a more lasting way than temporary campaigns.',
                'question' => 'According to the passage, what creates the most durable progress in {topic}?',
                'answer' => '{c}',
                'options' => ['{a}', '{b}', '{c}', 'short-term publicity'],
                'explanation' => 'The final sentence says the most durable progress comes from {c}.',
            ],
            [
                'text' => 'Researchers studying {topic} compared several strategies. Their findings suggest that {a} can bring quick results, but these changes are often short-lived. In contrast, {b} is slower to implement yet has broader effects. The report finally recommends combining both approaches, with particular emphasis on {c} in areas that need structural reform.',
                'question' => 'What does the report place particular emphasis on?',
                'answer' => '{c}',
                'options' => ['{a}', '{b}', '{c}', 'private advertising only'],
                'explanation' => 'The report recommends combining approaches but gives particular emphasis to {c}.',
            ],
            [
                'text' => 'A survey on {topic} found that people often support change in theory but behave differently in practice. The main reason, according to the report, is that {a} remains difficult for ordinary citizens. Although {b} has improved in recent years, experts argue that real progress still depends on {c}, especially when governments want lasting results.',
                'question' => 'Why do experts believe real progress still depends on {c}?',
                'answer' => 'Because governments want lasting results',
                'options' => [
                    'Because it is cheaper than every other solution',
                    'Because it attracts more tourists',
                    'Because governments want lasting results',
                    'Because it removes the need for public debate',
                ],
                'explanation' => 'The passage directly states that {c} matters most when governments want lasting results.',
            ],
        ];
        $listeningTemplates = [
            [
                'text' => 'Host: Today we are discussing {topic}. Guest: The first challenge is {a}, but the practical priority is to improve {b}. Host: So the note for next year should be [____].\n\n(Audio transcript hint: The speaker says the note for next year should be {b}.)',
                'question' => 'Complete the note with the exact phrase used by the speaker.',
                'answer' => '{b}',
                'explanation' => 'The guest says the practical priority is to improve {b}.',
            ],
            [
                'text' => 'Lecturer: In this session on {topic}, remember that {a} is often visible first, while {b} appears more gradually. If you are asked about the central recommendation, write down [____].\n\n(Audio transcript hint: The lecturer says the central recommendation is {c}.)',
                'question' => 'Write the recommendation in no more than two words.',
                'answer' => '{c}',
                'explanation' => 'The lecturer identifies {c} as the central recommendation.',
            ],
            [
                'text' => 'Coordinator: We have reviewed the {topic} proposal. The team agreed that {a} is still a concern, yet the immediate action should focus on {c}. Please enter [____] on the summary form.\n\n(Audio transcript hint: The immediate action is {c}.)',
                'question' => 'Complete the summary form with the immediate action.',
                'answer' => '{c}',
                'explanation' => 'The coordinator says the immediate action should focus on {c}.',
            ],
        ];

        $index = 0;
        foreach ($catalog as $topic => $topicData) {
            $vocabulary = array_values($topicData['vocabulary']);
            $promptQuestions = array_values($topicData['questions']);
            $difficulty = $difficultyCycle[$index % count($difficultyCycle)];
            $tokens = [
                '{topic}' => $topic,
                '{a}' => $vocabulary[0] ?? 'long-term planning',
                '{b}' => $vocabulary[1] ?? 'public awareness',
                '{c}' => $vocabulary[2] ?? ($vocabulary[0] ?? 'community support'),
            ];

            foreach ($readingTemplates as $templateIndex => $template) {
                $questions[] = [
                    'skill' => 'reading',
                    'type' => 'mcq',
                    'topic' => $topic,
                    'difficulty' => $difficultyCycle[($index + $templateIndex) % count($difficultyCycle)],
                    'content' => [
                        'text' => strtr($template['text'], $tokens),
                        'question' => strtr($template['question'], $tokens),
                        'answer' => strtr($template['answer'], $tokens),
                        'options' => array_map(fn (string $option) => strtr($option, $tokens), $template['options']),
                        'explanation' => strtr($template['explanation'], $tokens),
                    ],
                ];
            }

            foreach ($listeningTemplates as $templateIndex => $template) {
                $questions[] = [
                    'skill' => 'listening',
                    'type' => 'gap_fill',
                    'topic' => $topic,
                    'difficulty' => $difficultyCycle[($index + $templateIndex) % count($difficultyCycle)],
                    'content' => [
                        'text' => strtr($template['text'], $tokens),
                        'question' => strtr($template['question'], $tokens),
                        'answer' => strtr($template['answer'], $tokens),
                        'explanation' => strtr($template['explanation'], $tokens),
                    ],
                ];
            }

            $questions[] = [
                'skill' => 'writing',
                'type' => 'task_2',
                'topic' => $topic,
                'difficulty' => $difficulty,
                'content' => [
                    'text' => $topicData['writing_task'],
                    'question' => $topicData['writing_task'],
                    'answer' => "Develop a clear position on {$topic}, support it with relevant examples, and keep the response logically organised.",
                    'explanation' => "A strong Task 2 essay should present a clear thesis, balanced development, and relevant examples connected to {$topic}.",
                ],
            ];

            $questions[] = [
                'skill' => 'speaking',
                'type' => 'part_1',
                'topic' => $topic,
                'difficulty' => 'easy',
                'content' => [
                    'text' => $promptQuestions[0] ?? "What are your first thoughts about {$topic}?",
                    'question' => $promptQuestions[0] ?? "What are your first thoughts about {$topic}?",
                    'answer' => 'A natural personal answer with one or two supporting details is enough for a good Part 1 response.',
                    'explanation' => 'Part 1 answers should sound personal, direct, and slightly extended.',
                ],
            ];

            $questions[] = [
                'skill' => 'speaking',
                'type' => 'part_2',
                'topic' => $topic,
                'difficulty' => 'medium',
                'content' => [
                    'text' => "Describe an experience, person, or situation connected to {$topic}. You should say what it was, when it happened, why it was memorable, and explain what you learned from it.",
                    'question' => "Describe an experience, person, or situation connected to {$topic}.",
                    'cue_card' => 'You should say what it was, when it happened, why it was memorable, and explain what you learned from it.',
                    'answer' => 'A strong Part 2 answer should cover each cue point, use linking phrases, and include one clear personal example.',
                    'explanation' => 'Part 2 responses should be organised, extended, and easy to follow.',
                ],
            ];

            $questions[] = [
                'skill' => 'speaking',
                'type' => 'part_3',
                'topic' => $topic,
                'difficulty' => 'hard',
                'content' => [
                    'text' => $promptQuestions[2] ?? "How might {$topic} change in the future?",
                    'question' => $promptQuestions[2] ?? "How might {$topic} change in the future?",
                    'follow_up' => $promptQuestions[1] ?? null,
                    'answer' => 'A strong Part 3 answer should compare viewpoints, explain reasons, and give a broader perspective.',
                    'explanation' => 'Part 3 requires analysis, comparison, and clear reasoning rather than a short personal answer.',
                ],
            ];

            $index++;
        }

        return $questions;
    }

    private function buildTaskOneQuestionBank(): array
    {
        $taskOnePrompts = [
            ['topic' => 'University Enrollment Trends', 'difficulty' => 'medium', 'text' => 'The line graph shows changes in university enrollment in three subjects between 2005 and 2025. Summarise the information by selecting and reporting the main features, and make comparisons where relevant.'],
            ['topic' => 'City Transport Usage', 'difficulty' => 'medium', 'text' => 'The bar chart compares how residents in four cities used different forms of public transport in one year. Summarise the information by selecting and reporting the main features, and make comparisons where relevant.'],
            ['topic' => 'Household Energy Consumption', 'difficulty' => 'easy', 'text' => 'The pie charts illustrate how energy was used in an average household in 1995 and 2025. Summarise the information by selecting and reporting the main features, and make comparisons where relevant.'],
            ['topic' => 'Tourism Revenue Overview', 'difficulty' => 'medium', 'text' => 'The table gives information about tourism revenue in five countries over a ten-year period. Summarise the information by selecting and reporting the main features, and make comparisons where relevant.'],
            ['topic' => 'Office Water Cycle', 'difficulty' => 'easy', 'text' => 'The diagram shows how water is recycled in a large office building. Summarise the information by selecting and reporting the main features, and make comparisons where relevant.'],
            ['topic' => 'Online Shopping Growth', 'difficulty' => 'hard', 'text' => 'The line graph illustrates the percentage of people in five age groups who shopped online between 2010 and 2024. Summarise the information by selecting and reporting the main features, and make comparisons where relevant.'],
            ['topic' => 'Hospital Departments', 'difficulty' => 'medium', 'text' => 'The bar chart compares the number of patients treated in six hospital departments during a typical month. Summarise the information by selecting and reporting the main features, and make comparisons where relevant.'],
            ['topic' => 'Urban Waste Process', 'difficulty' => 'hard', 'text' => 'The process diagram illustrates how waste is sorted and processed in a modern recycling plant. Summarise the information by selecting and reporting the main features, and make comparisons where relevant.'],
            ['topic' => 'Library Membership Changes', 'difficulty' => 'easy', 'text' => 'The table compares library membership in five districts between 2012 and 2022. Summarise the information by selecting and reporting the main features, and make comparisons where relevant.'],
            ['topic' => 'Airport Expansion Plan', 'difficulty' => 'hard', 'text' => 'The diagrams show the layout of an airport now and the planned changes after redevelopment. Summarise the information by selecting and reporting the main features, and make comparisons where relevant.'],
            ['topic' => 'Factory Production Process', 'difficulty' => 'medium', 'text' => 'The process diagram illustrates how packaged food is produced in a modern factory. Summarise the information by selecting and reporting the main features, and make comparisons where relevant.'],
            ['topic' => 'Student Device Ownership', 'difficulty' => 'medium', 'text' => 'The bar chart shows the percentage of students who owned different digital devices in 2010, 2017 and 2025. Summarise the information by selecting and reporting the main features, and make comparisons where relevant.'],
        ];

        return array_map(function (array $prompt) {
            return [
                'skill' => 'writing',
                'type' => 'task_1',
                'topic' => $prompt['topic'],
                'difficulty' => $prompt['difficulty'],
                'content' => [
                    'text' => $prompt['text'],
                    'question' => $prompt['text'],
                    'answer' => 'Write an overview first, then compare the key features without adding personal opinions.',
                    'explanation' => 'Task 1 responses should focus on trends, comparisons, or stages clearly and objectively.',
                ],
            ];
        }, $taskOnePrompts);
    }
}
