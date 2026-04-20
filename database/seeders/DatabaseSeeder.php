<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Admin
        User::create([
            'name' => 'Admin Teacher',
            'email' => 'admin@ielts.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        // Create Active Student
        User::create([
            'name' => 'Active Student',
            'email' => 'student@ielts.com',
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
            'target_band' => '7.5',
        ]);

        // Create Pending Student
        User::create([
            'name' => 'New Applicant',
            'email' => 'pending@ielts.com',
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'pending',
            'target_band' => '6.5',
        ]);

        $this->call([
            \Modules\Question\database\seeders\SampleQuestionSeeder::class,
        ]);
    }
}
