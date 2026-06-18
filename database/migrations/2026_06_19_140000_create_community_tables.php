<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('content');
            $table->boolean('is_public')->default(false);
            $table->unsignedBigInteger('lesson_id')->nullable();
            $table->timestamps();
            $table->index(['is_public', 'created_at']);
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('commentable');
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('study_buddy_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_buddy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('matched_at')->useCurrent();
            $table->unique(['study_buddy_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_buddy_user');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('study_notes');
    }
};