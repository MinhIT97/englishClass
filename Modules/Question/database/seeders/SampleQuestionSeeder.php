<?php

namespace Modules\Question\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Question\Models\Question;

class SampleQuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $questions = [
            // READING - MCQ
            [
                'skill' => 'reading',
                'type' => 'mcq',
                'topic' => 'Climate Change',
                'difficulty' => 'medium',
                'content' => [
                    'text' => "Climate change is significantly impacting polar regions. The melting of Arctic ice leads to rising sea levels, which threatens coastal cities globally. Scientists suggest that immediate reduction in carbon emissions is required to mitigate these effects.\n\nAccording to the passage, why are coastal cities threatened?",
                    'answer' => 'Rising sea levels caused by melting ice',
                    'options' => [
                        'Heavy rainfall in summer',
                        'Rising sea levels caused by melting ice',
                        'Increase in global tourism',
                        'Lack of proper drainage systems'
                    ],
                    'explanation' => "The text explicitly states: 'The melting of Arctic ice leads to rising sea levels, which threatens coastal cities globally.'"
                ]
            ],
            // LISTENING - GAP FILL
            [
                'skill' => 'listening',
                'type' => 'gap_fill',
                'topic' => 'Campus Orientation',
                'difficulty' => 'easy',
                'content' => [
                    'text' => "Student: Where is the main library located?\nOfficer: It's right next to the [____] building behind the cafeteria.\n\n(Audio transcript hint: The library is next to the Science building).",
                    'answer' => 'Science',
                    'explanation' => "In the transcript context, the library is mentioned to be next to the Science building."
                ]
            ],
            // WRITING - TASK 2
            [
                'skill' => 'writing',
                'type' => 'task_2',
                'topic' => 'Technology & Society',
                'difficulty' => 'hard',
                'content' => [
                    'text' => "Some people believe that the increasing use of technology is making us more isolated, while others argue that it brings people closer together. Discuss both views and give your opinion.",
                    'answer' => 'Social isolation vs Connectivity',
                    'explanation' => "This is a standard 'Discuss both views' essay prompt."
                ]
            ],
             // SPEAKING - PART 1
             [
                'skill' => 'speaking',
                'type' => 'part_1',
                'topic' => 'Hometown',
                'difficulty' => 'easy',
                'content' => [
                    'text' => "Can you tell me about the town or city where you grew up?",
                    'answer' => 'Personal description',
                    'explanation' => "Part 1 speaking questions are meant to be simple and personal."
                ]
            ],
            // READING - ANOTHER MCQ
            [
                'skill' => 'reading',
                'type' => 'mcq',
                'topic' => 'Ancient Egypt',
                'difficulty' => 'hard',
                'content' => [
                    'text' => "The Great Pyramid of Giza was built for the Pharaoh Khufu. It remained the tallest man-made structure in the world for over 3,800 years. Its construction involved millions of limestone blocks and advanced engineering techniques that still baffle researchers today.\n\nHow long did the Great Pyramid hold the record for the tallest structure?",
                    'answer' => 'More than 3,000 years',
                    'options' => [
                        'Around 100 years',
                        'Over 3,800 years',
                        'Less than 1,000 years',
                        'Exactly 5,000 years'
                    ],
                    'explanation' => "The passage states: 'It remained the tallest man-made structure in the world for over 3,800 years.'"
                ]
            ]
        ];

        foreach ($questions as $q) {
            Question::create($q);
        }
    }
}
