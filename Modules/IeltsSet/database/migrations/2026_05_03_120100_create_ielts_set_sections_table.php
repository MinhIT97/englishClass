<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ielts_set_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ielts_set_id')->constrained('ielts_sets')->cascadeOnDelete();
            $table->string('skill');
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->unsignedInteger('section_order')->default(1);
            $table->unsignedInteger('time_limit_minutes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ielts_set_sections');
    }
};
