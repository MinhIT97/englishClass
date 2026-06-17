<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tgb_daily_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('topic_id')->nullable()->constrained('tgb_topics')->onDelete('set null');
            $table->date('lesson_date');
            $table->enum('status', ['scheduled', 'sent', 'failed', 'skipped'])->default('scheduled');
            $table->unsignedBigInteger('telegram_message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'lesson_date']);
            $table->index('lesson_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tgb_daily_lessons');
    }
};
