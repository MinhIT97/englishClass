<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ielts_set_section_question', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ielts_set_section_id')->constrained('ielts_set_sections')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->unsignedInteger('question_order')->default(1);
            $table->timestamps();
            $table->unique(['ielts_set_section_id', 'question_id'], 'ielts_set_section_question_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ielts_set_section_question');
    }
};
