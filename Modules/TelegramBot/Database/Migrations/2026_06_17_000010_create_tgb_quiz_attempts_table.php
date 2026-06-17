<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tgb_quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('daily_lesson_id')
                ->nullable()
                ->constrained('tgb_daily_lessons')
                ->onDelete('set null');
            $table->foreignId('vocabulary_entry_id')
                ->nullable()
                ->constrained('tgb_vocabulary_entries')
                ->onDelete('set null');
            $table->enum('quiz_type', [
                'multiple_choice', 'fill_blank', 'word_scramble', 'match_pairs',
            ])->default('multiple_choice');
            $table->json('question_payload')->nullable();
            $table->text('user_answer')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->unsignedTinyInteger('xp_awarded')->default(0);
            $table->timestamp('attempted_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'attempted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tgb_quiz_attempts');
    }
};
