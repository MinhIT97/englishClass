<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lesson request workflow.
     *
     * When a non-admin user hits their lesson_limit, they submit a
     * request explaining why they need more lessons. Admins review the
     * queue in the admin panel and either:
     *   - approve -> bumps user.lesson_limit by approved_extra, OR
     *               sets user.is_unlimited = true if grant_unlimited.
     *   - reject  -> leaves user quota unchanged, stores reason.
     *
     * lesson_type mirrors the three creation paths the quota covers:
     *   course, classroom, daily_lesson.
     */
    public function up(): void
    {
        Schema::create('lesson_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('lesson_type'); // course | classroom | daily_lesson
            $table->unsignedSmallInteger('requested_extra')->default(1);
            $table->text('reason')->nullable();
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->unsignedSmallInteger('approved_extra')->nullable();
            $table->boolean('grant_unlimited')->default(false);
            $table->text('admin_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_requests');
    }
};