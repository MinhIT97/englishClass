<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ielts_sets', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('topic')->nullable();
            $table->string('set_type')->default('skill');
            $table->string('target_band')->default('6.5-7.5');
            $table->string('skill_focus')->nullable();
            $table->text('description')->nullable();
            $table->string('difficulty')->default('medium');
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->unsignedInteger('total_questions')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ielts_sets');
    }
};
