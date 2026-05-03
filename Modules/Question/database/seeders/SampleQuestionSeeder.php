<?php

namespace Modules\Question\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Question\Models\Question;

class SampleQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $questions = [
            // READING
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
                        'Lack of proper drainage systems',
                    ],
                    'explanation' => "The passage explains that melting Arctic ice raises sea levels, which directly threatens coastal cities.",
                ],
            ],
            [
                'skill' => 'reading',
                'type' => 'mcq',
                'topic' => 'Ancient Egypt',
                'difficulty' => 'hard',
                'content' => [
                    'text' => "The Great Pyramid of Giza was built for the Pharaoh Khufu. It remained the tallest man-made structure in the world for over 3,800 years. Its construction involved millions of limestone blocks and advanced engineering techniques that still baffle researchers today.\n\nHow long did the Great Pyramid hold the record for the tallest structure?",
                    'answer' => 'Over 3,800 years',
                    'options' => [
                        'Around 100 years',
                        'Over 3,800 years',
                        'Less than 1,000 years',
                        'Exactly 5,000 years',
                    ],
                    'explanation' => "The text explicitly says the pyramid remained the tallest structure for over 3,800 years.",
                ],
            ],
            [
                'skill' => 'reading',
                'type' => 'mcq',
                'topic' => 'Space Exploration',
                'difficulty' => 'medium',
                'content' => [
                    'text' => "Private companies have changed the pace of space exploration by reducing launch costs and increasing mission frequency. Reusable rocket technology has made it possible to send satellites, scientific instruments, and even astronauts into orbit more efficiently than before.\n\nWhat is the main benefit of reusable rockets mentioned in the passage?",
                    'answer' => 'They lower the cost of launching missions',
                    'options' => [
                        'They can travel faster than traditional rockets',
                        'They lower the cost of launching missions',
                        'They replace the need for satellites',
                        'They make space tourism free',
                    ],
                    'explanation' => "The passage links reusable rockets to reduced launch costs and better mission efficiency.",
                ],
            ],
            [
                'skill' => 'reading',
                'type' => 'mcq',
                'topic' => 'Nutrition',
                'difficulty' => 'easy',
                'content' => [
                    'text' => "Nutritionists recommend eating a balanced diet that includes vegetables, fruit, lean protein, and whole grains. While vitamin supplements can help in some cases, they are not considered a replacement for healthy eating habits.\n\nAccording to the passage, what should not replace healthy eating habits?",
                    'answer' => 'Vitamin supplements',
                    'options' => [
                        'Whole grains',
                        'Vegetables',
                        'Vitamin supplements',
                        'Lean protein',
                    ],
                    'explanation' => "The text clearly says supplements may help but should not replace healthy eating habits.",
                ],
            ],
            [
                'skill' => 'reading',
                'type' => 'mcq',
                'topic' => 'Remote Work',
                'difficulty' => 'medium',
                'content' => [
                    'text' => "Remote work offers flexibility and saves commuting time, but it also requires workers to manage distractions and maintain communication with colleagues. Many companies now use virtual meetings and project tools to keep teams connected.\n\nWhy do companies use virtual meetings and project tools?",
                    'answer' => 'To keep remote teams connected',
                    'options' => [
                        'To shorten lunch breaks',
                        'To keep remote teams connected',
                        'To replace internet access',
                        'To reduce employee salaries',
                    ],
                    'explanation' => "The passage states these tools are used to keep distributed teams connected.",
                ],
            ],
            [
                'skill' => 'reading',
                'type' => 'mcq',
                'topic' => 'Urban Farming',
                'difficulty' => 'hard',
                'content' => [
                    'text' => "Urban farming has emerged as a practical response to food insecurity in densely populated areas. Rooftop gardens and hydroponic systems allow residents to produce fresh vegetables even where traditional farmland is unavailable. Although yields are often smaller than rural farms, urban farming can shorten supply chains and reduce transport emissions.\n\nWhat is one environmental advantage of urban farming?",
                    'answer' => 'It can reduce transport emissions',
                    'options' => [
                        'It eliminates all food waste',
                        'It can reduce transport emissions',
                        'It replaces the need for water',
                        'It guarantees larger harvests than rural farms',
                    ],
                    'explanation' => "The final sentence notes that urban farming can shorten supply chains and reduce transport emissions.",
                ],
            ],

            // LISTENING
            [
                'skill' => 'listening',
                'type' => 'gap_fill',
                'topic' => 'Campus Orientation',
                'difficulty' => 'easy',
                'content' => [
                    'text' => "Student: Where is the main library located?\nOfficer: It's right next to the [____] building behind the cafeteria.\n\n(Audio transcript hint: The library is next to the Science building).",
                    'answer' => 'Science',
                    'explanation' => "The speaker says the library is next to the Science building.",
                ],
            ],
            [
                'skill' => 'listening',
                'type' => 'gap_fill',
                'topic' => 'Airport Announcement',
                'difficulty' => 'easy',
                'content' => [
                    'text' => "Announcement: Passengers travelling to Singapore on flight GA218 should proceed to Gate [____] immediately.\n\n(Audio transcript hint: The gate number is twelve.)",
                    'answer' => '12',
                    'explanation' => "The announcement tells passengers for flight GA218 to proceed to Gate 12.",
                ],
            ],
            [
                'skill' => 'listening',
                'type' => 'gap_fill',
                'topic' => 'Doctor Appointment',
                'difficulty' => 'medium',
                'content' => [
                    'text' => "Receptionist: Dr. Brown can see you on Thursday at [____] in the morning.\n\n(Audio transcript hint: The appointment is at 10:30 a.m.)",
                    'answer' => '10:30',
                    'explanation' => "The receptionist offers an appointment time of 10:30 in the morning.",
                ],
            ],
            [
                'skill' => 'listening',
                'type' => 'gap_fill',
                'topic' => 'Hotel Booking',
                'difficulty' => 'medium',
                'content' => [
                    'text' => "Guest: I'd like to book a room for three nights.\nClerk: Certainly. May I have your [____], please?\n\n(Audio transcript hint: The clerk asks for the guest's passport.)",
                    'answer' => 'passport',
                    'explanation' => "The hotel clerk requests the guest's passport for the booking.",
                ],
            ],
            [
                'skill' => 'listening',
                'type' => 'gap_fill',
                'topic' => 'Community Event',
                'difficulty' => 'hard',
                'content' => [
                    'text' => "Organizer: The charity run will begin at the town [____], and volunteers should arrive thirty minutes earlier.\n\n(Audio transcript hint: The event begins at the town square.)",
                    'answer' => 'square',
                    'explanation' => "The organizer says the charity run starts at the town square.",
                ],
            ],
            [
                'skill' => 'listening',
                'type' => 'gap_fill',
                'topic' => 'Online Order',
                'difficulty' => 'easy',
                'content' => [
                    'text' => "Agent: Your package was sent yesterday and should arrive by [____].\n\n(Audio transcript hint: It is expected to arrive by Friday.)",
                    'answer' => 'Friday',
                    'explanation' => "The delivery estimate given by the agent is Friday.",
                ],
            ],

            // WRITING
            [
                'skill' => 'writing',
                'type' => 'task_2',
                'topic' => 'Technology & Society',
                'difficulty' => 'hard',
                'content' => [
                    'text' => "Some people believe that the increasing use of technology is making us more isolated, while others argue that it brings people closer together. Discuss both views and give your opinion.",
                    'answer' => 'Discuss both views and give your opinion on technology and social connection.',
                    'explanation' => "A strong answer should discuss both isolation and connectivity before presenting a clear personal view.",
                ],
            ],
            [
                'skill' => 'writing',
                'type' => 'task_2',
                'topic' => 'Education Policy',
                'difficulty' => 'medium',
                'content' => [
                    'text' => "Some people think all university students should study whatever they like. Others believe they should only be allowed to study subjects that will be useful in the future, such as science and technology. Discuss both views and give your own opinion.",
                    'answer' => 'Discuss freedom of subject choice versus job-focused education.',
                    'explanation' => "Candidates should balance personal interest against economic demand and support their own position clearly.",
                ],
            ],
            [
                'skill' => 'writing',
                'type' => 'task_2',
                'topic' => 'Work-Life Balance',
                'difficulty' => 'medium',
                'content' => [
                    'text' => "Many people nowadays work long hours and have little time for leisure activities. Why is this happening, and what can be done to solve this problem?",
                    'answer' => 'Explain causes of long working hours and propose realistic solutions.',
                    'explanation' => "A successful essay should identify social or economic causes and offer practical solutions from employers and governments.",
                ],
            ],
            [
                'skill' => 'writing',
                'type' => 'task_2',
                'topic' => 'Environmental Responsibility',
                'difficulty' => 'hard',
                'content' => [
                    'text' => "Some people say that individuals can do little to improve the environment, and only governments and large companies can make a real difference. To what extent do you agree or disagree?",
                    'answer' => 'Evaluate the roles of individuals, governments, and companies in environmental action.',
                    'explanation' => "A high-band essay should show a nuanced argument instead of treating the issue as entirely one-sided.",
                ],
            ],
            [
                'skill' => 'writing',
                'type' => 'task_1',
                'topic' => 'Tourism Statistics',
                'difficulty' => 'medium',
                'content' => [
                    'text' => "The chart below shows the number of international tourists visiting three different countries between 2010 and 2020. Summarise the information by selecting and reporting the main features, and make comparisons where relevant.",
                    'answer' => 'Summarise trends, highlight main comparisons, and avoid giving opinions.',
                    'explanation' => "Task 1 responses should focus on overview, key changes, and comparisons rather than reasons or personal views.",
                ],
            ],
            [
                'skill' => 'writing',
                'type' => 'task_1',
                'topic' => 'Office Energy Use',
                'difficulty' => 'easy',
                'content' => [
                    'text' => "The diagram illustrates how electricity is used in a modern office building over a typical working day. Summarise the information by selecting and reporting the main features, and make comparisons where relevant.",
                    'answer' => 'Describe major stages, peak periods, and comparisons in energy use.',
                    'explanation' => "Task 1 diagram answers should describe process or patterns clearly and logically.",
                ],
            ],

            // SPEAKING
            [
                'skill' => 'speaking',
                'type' => 'part_1',
                'topic' => 'Hometown',
                'difficulty' => 'easy',
                'content' => [
                    'text' => "Can you tell me about the town or city where you grew up?",
                    'answer' => 'A natural personal description with details and examples.',
                    'explanation' => "Part 1 answers should be personal, direct, and slightly extended rather than one-word replies.",
                ],
            ],
            [
                'skill' => 'speaking',
                'type' => 'part_1',
                'topic' => 'Daily Routine',
                'difficulty' => 'easy',
                'content' => [
                    'text' => "What is the busiest part of your day, and why?",
                    'answer' => 'A clear explanation of a daily routine with reasons.',
                    'explanation' => "Candidates should answer naturally and support their ideas with small details.",
                ],
            ],
            [
                'skill' => 'speaking',
                'type' => 'part_2',
                'topic' => 'Memorable Teacher',
                'difficulty' => 'medium',
                'content' => [
                    'text' => "Describe a teacher who has influenced you. You should say who the person is, what subject they taught, how they influenced you, and explain why you still remember them.",
                    'answer' => 'A structured long-turn response with detail and reflection.',
                    'explanation' => "Part 2 answers should cover all prompts and include a personal explanation.",
                ],
            ],
            [
                'skill' => 'speaking',
                'type' => 'part_2',
                'topic' => 'Useful App',
                'difficulty' => 'medium',
                'content' => [
                    'text' => "Describe an app you use regularly. You should say what the app is, what you use it for, when you started using it, and explain why it is useful to you.",
                    'answer' => 'A well-organized response about an app, with examples and reasons.',
                    'explanation' => "Part 2 should sound fluent and connected rather than like a list of short answers.",
                ],
            ],
            [
                'skill' => 'speaking',
                'type' => 'part_3',
                'topic' => 'Public Transport',
                'difficulty' => 'hard',
                'content' => [
                    'text' => "Do you think governments should invest more in public transport than in building new roads? Why or why not?",
                    'answer' => 'A reasoned opinion with comparison and supporting arguments.',
                    'explanation' => "Part 3 requires abstract thinking, comparison, and clear justification.",
                ],
            ],
            [
                'skill' => 'speaking',
                'type' => 'part_3',
                'topic' => 'Artificial Intelligence',
                'difficulty' => 'hard',
                'content' => [
                    'text' => "How do you think artificial intelligence will change the way people work over the next decade?",
                    'answer' => 'A thoughtful discussion of future impacts, benefits, and risks.',
                    'explanation' => "A strong answer should include prediction, examples, and balanced reasoning.",
                ],
            ],
        ];

        foreach ($questions as $question) {
            Question::firstOrCreate($question);
        }
    }
}
