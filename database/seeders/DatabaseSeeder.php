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
        // Spatie roles + permissions MUST exist before any code that
        // does $user->assignRole() or $user->hasRole() runs. Run this
        // seeder first so the demo users below can sync to the pivot
        // safely (see AuthService::register + RoleSynced event).
        $this->call([
            RolesAndPermissionsSeeder::class,
        ]);

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

        // Sync demo users into Spatie pivot so hasRole()/can() checks
        // work out-of-the-box. Real registrations go through
        // AuthService::register which dispatches RoleSynced.
        foreach (User::whereIn('email', ['admin@ielts.com', 'student@ielts.com', 'pending@ielts.com'])->get() as $demoUser) {
            $demoUser->syncRoles([$demoUser->role]);
        }

        $this->call([
            \Modules\Question\database\seeders\SampleQuestionSeeder::class,
            \Modules\IeltsSet\database\seeders\IeltsSetDatabaseSeeder::class,
        ]);
    }
}
