<?php

namespace Modules\Course\Database\Seeders;

use Illuminate\Database\Seeder;

class CourseDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $courses = [
            [
                'title' => 'IELTS Writing Masterclass 📝',
                'description' => 'Master the art of Task 1 and Task 2 with our comprehensive writing guide. Includes 20+ sample essays and detailed feedback templates.',
                'price' => 49.99,
                'status' => 'active',
            ],
            [
                'title' => 'Speaking Pro: 7.5+ Band Edition 🗣️',
                'description' => 'Unlock natural fluency and complex vocabulary. Practice with actual exam questions and receive AI-powered feedback on your pronunciation.',
                'price' => 59.99,
                'status' => 'active',
            ],
            [
                'title' => 'Grammar & Vocabulary for IELTS 📚',
                'description' => 'The foundation of a high band score. Learn the critical structures and academic word lists used by top-performing students.',
                'price' => 29.00,
                'status' => 'active',
            ],
            [
                'title' => 'Reading & Listening Speed Drills ⚡',
                'description' => 'Stop running out of time. Techniques for skimming, scanning, and identifying tricked-options in record time.',
                'price' => 35.50,
                'status' => 'active',
            ],
        ];

        foreach ($courses as $course) {
            \Modules\Course\Models\Course::updateOrCreate(['title' => $course['title']], $course);
        }
    }
}
