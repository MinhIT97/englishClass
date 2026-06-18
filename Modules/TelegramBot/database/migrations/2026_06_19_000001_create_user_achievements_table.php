<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create `tgb_user_achievements` table.
 *
 * Records which achievements each user has unlocked and when. Used by
 * AchievementService::checkAndUnlock() — the unique constraint on
 * (user_id, achievement_key) makes the check naturally idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tgb_user_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('achievement_key', 64);
            $table->timestamp('unlocked_at')->useCurrent();
            $table->timestamps();

            // Each user can unlock any given achievement at most once.
            $table->unique(['user_id', 'achievement_key']);
            // Cheap query for "user's recently-unlocked badges".
            $table->index(['user_id', 'unlocked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tgb_user_achievements');
    }
};