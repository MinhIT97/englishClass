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
        User::updateOrCreate(
            ['email' => 'admin@ielts.com'],
            [
                'name' => 'Admin Teacher',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'status' => 'active',
            ]
        );

        User::updateOrCreate(
            ['email' => 'student@ielts.com'],
            [
                'name' => 'Active Student',
                'password' => Hash::make('password'),
                'role' => 'student',
                'status' => 'active',
                'target_band' => '7.5',
            ]
        );

        User::updateOrCreate(
            ['email' => 'pending@ielts.com'],
            [
                'name' => 'New Applicant',
                'password' => Hash::make('password'),
                'role' => 'student',
                'status' => 'pending',
                'target_band' => '6.5',
            ]
        );

        $this->call([
            \Modules\Question\database\seeders\SampleQuestionSeeder::class,
            \Modules\IeltsSet\database\seeders\IeltsSetDatabaseSeeder::class,
        ]);
    }
}
