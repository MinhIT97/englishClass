<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reading comprehension passages library + per-question attempts.
 *
 * We separate the passage (the long-form text) from the questions so a single
 * passage can have multiple questions of mixed types (mcq, gap-fill, matching).
 * This mirrors the real IELTS reading format and unlocks a proper review
 * queue (a user finishes a passage, the whole passage is graded at once).
 *
 * Tables:
 *   - reading_passages           : the passages themselves
 *   - reading_passage_questions  : N questions attached to each passage
 *   - reading_attempts           : per-question analytics
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reading_passages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 128)->unique();
            $table->string('title');
            $table->text('body');
            $table->string('source', 128)->nullable();
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('medium');
            $table->unsignedSmallInteger('word_count')->nullable();
            $table->unsignedTinyInteger('estimated_minutes')->default(5);
            $table->json('tags')->nullable();

            // Soft link to the topic in the Telegram learning roadmap. We don't
            // hard-require it because passages may also live outside any topic
            // (e.g. mock test bank, admin-only content).
            $table->foreignId('topic_id')
                ->nullable()
                ->constrained('tgb_topics')
                ->nullOnDelete();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['topic_id', 'is_active']);
            $table->index('difficulty');
        });

        Schema::create('reading_passage_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reading_passage_id')
                ->constrained('reading_passages')
                ->cascadeOnDelete();
            $table->foreignId('question_id')
                ->constrained('questions')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('order_index')->default(0);
            $table->timestamps();

            $table->unique(['reading_passage_id', 'question_id']);
            $table->index('reading_passage_id');
        });

        Schema::create('reading_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reading_passage_id')
                ->constrained('reading_passages')
                ->cascadeOnDelete();
            $table->foreignId('question_id')
                ->constrained('questions')
                ->cascadeOnDelete();
            $table->text('student_answer');
            $table->boolean('is_correct');
            $table->unsignedInteger('points_earned')->default(0);
            $table->unsignedInteger('time_spent_ms')->nullable();
            $table->timestamp('attempted_at')->useCurrent();
            $table->timestamps();

            $table->index(['user_id', 'attempted_at']);
            $table->index(['reading_passage_id', 'is_correct']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_attempts');
        Schema::dropIfExists('reading_passage_questions');
        Schema::dropIfExists('reading_passages');
    }
};
