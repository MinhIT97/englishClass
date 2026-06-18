<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Notification preferences
            $table->boolean('notify_lesson_reminder')->default(true);
            $table->boolean('notify_quota_request')->default(true);
            $table->boolean('notify_achievement')->default(true);
            $table->boolean('notify_feedback')->default(true);
            $table->string('notification_digest', 20)->default('realtime'); // realtime | daily | weekly | off

            // Learning preferences
            $table->unsignedSmallInteger('daily_review_goal')->default(20);
            $table->string('preferred_study_time', 20)->nullable(); // morning | afternoon | evening | night
            $table->unsignedSmallInteger('session_duration_minutes')->default(25);

            // Privacy
            $table->boolean('show_in_leaderboard')->default(false);
            $table->boolean('show_study_notes_publicly')->default(false);

            // Locale
            $table->string('locale', 10)->default('vi');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};