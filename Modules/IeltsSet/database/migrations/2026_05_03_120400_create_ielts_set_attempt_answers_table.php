<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ielts_set_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ielts_set_attempt_id')->constrained('ielts_set_attempts')->cascadeOnDelete();
            $table->foreignId('ielts_set_section_id')->constrained('ielts_set_sections')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->longText('answer_text')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('points_earned')->default(0);
            $table->text('correct_answer')->nullable();
            $table->longText('feedback')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['ielts_set_attempt_id', 'question_id'],
                'ielts_set_attempt_question_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ielts_set_attempt_answers');
    }
};
