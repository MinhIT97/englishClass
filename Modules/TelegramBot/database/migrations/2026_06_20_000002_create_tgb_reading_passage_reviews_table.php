<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SM-2 spaced repetition schedule for READING PASSAGES.
 *
 * We keep this in a dedicated table (rather than reusing tgb_review_schedules
 * with a polymorphic FK) for two reasons:
 *   1. The existing tgb_review_schedules.vocabulary_entry_id has a hard FK
 *      and is referenced by FlashcardController / SpacedRepetitionService /
 *      TelegramBotCommandService. Touching it would mean touching a lot of
 *      proven code paths.
 *   2. Reading passages and vocabulary entries are different objects with
 *      different lifecycles (a passage has many questions graded together
 *      and counts toward topic completion separately from vocab). A
 *      dedicated table makes that explicit and avoids the bookkeeping of
 *      a polymorphic schedule.
 *
 * Schema mirrors tgb_review_schedules so the same SpacedRepetitionService
 * can grade any schedule object via a thin wrapper.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tgb_reading_passage_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reading_passage_id')
                ->constrained('reading_passages')
                ->cascadeOnDelete();
            $table->decimal('ease_factor', 3, 2)->default(2.50);
            $table->unsignedInteger('interval_days')->default(0);
            $table->unsignedTinyInteger('repetitions')->default(0);
            $table->timestamp('next_review_at')->nullable()->index();
            $table->timestamp('last_reviewed_at')->nullable();
            $table->unsignedTinyInteger('last_grade')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'reading_passage_id']);
            $table->index(['user_id', 'next_review_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tgb_reading_passage_reviews');
    }
};
