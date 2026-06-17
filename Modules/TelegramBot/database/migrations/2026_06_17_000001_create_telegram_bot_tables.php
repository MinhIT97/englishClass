<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TelegramBot module — single combined migration that creates all module
 * tables. Kept as one file so it loads atomically and avoids the
 * PascalCase/lowercase path issue we hit earlier.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) tgb_user_telegram_links — link between web user and Telegram chat.
        Schema::create('tgb_user_telegram_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
            $table->string('telegram_chat_id', 64)->unique();
            $table->string('telegram_username', 64)->nullable();
            $table->timestamp('linked_at')->nullable();
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamps();

            $table->index('telegram_chat_id');
        });

        // 2) tgb_linking_codes — short-lived codes used by /start to bind accounts.
        Schema::create('tgb_linking_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('code', 12)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
        });

        // 3) tgb_learning_profiles — purpose / level / send-time preferences.
        Schema::create('tgb_learning_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
            $table->enum('purpose', ['ielts', 'daily', 'business'])->default('ielts');
            $table->enum('level', ['beginner', 'intermediate', 'advanced'])->default('intermediate');
            $table->decimal('target_band', 3, 1)->nullable();
            $table->unsignedTinyInteger('daily_send_hour')->default(7);
            $table->string('timezone', 64)->default('Asia/Ho_Chi_Minh');
            $table->boolean('is_paused')->default(false);
            $table->timestamp('onboarded_at')->nullable();
            $table->timestamps();
        });

        // 4) tgb_topics — vocabulary/grammar topics, grouped by purpose.
        Schema::create('tgb_topics', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->enum('purpose', ['ielts', 'daily', 'business'])->index();
            $table->string('name_vi', 128);
            $table->string('name_en', 128);
            $table->unsignedInteger('order_index')->default(0);
            $table->unsignedTinyInteger('difficulty')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['purpose', 'order_index']);
        });

        // 5) tgb_user_paths — per-user position in the topic sequence.
        Schema::create('tgb_user_paths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('topic_id')->constrained('tgb_topics')->onDelete('cascade');
            $table->enum('status', ['locked', 'current', 'completed', 'skipped'])->default('locked');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedTinyInteger('word_count_target')->default(5);
            $table->timestamps();

            $table->unique(['user_id', 'topic_id']);
            $table->index(['user_id', 'status']);
        });

        // 6) tgb_vocabulary_entries — words the user has learned via the bot.
        Schema::create('tgb_vocabulary_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('topic_id')->nullable()->constrained('tgb_topics')->onDelete('set null');
            $table->string('word', 128);
            $table->string('pos', 16)->nullable();
            $table->string('ipa', 64)->nullable();
            $table->string('meaning_vi', 255);
            $table->string('meaning_en', 255)->nullable();
            $table->text('example_en')->nullable();
            $table->text('example_vi')->nullable();
            $table->unsignedTinyInteger('difficulty')->default(1);
            $table->timestamps();

            $table->unique(['user_id', 'word']);
            $table->index(['user_id', 'topic_id']);
        });

        // 7) tgb_grammar_entries — grammar structures per user per topic.
        Schema::create('tgb_grammar_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('topic_id')->nullable()->constrained('tgb_topics')->onDelete('set null');
            $table->string('structure', 128);
            $table->text('explanation_vi')->nullable();
            $table->text('explanation_en')->nullable();
            $table->text('example_en')->nullable();
            $table->text('example_vi')->nullable();
            $table->unsignedTinyInteger('difficulty')->default(1);
            $table->timestamps();

            $table->index(['user_id', 'topic_id']);
        });

        // 8) tgb_review_schedules — SM-2 spaced repetition state per word.
        Schema::create('tgb_review_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('vocabulary_entry_id')
                ->constrained('tgb_vocabulary_entries')
                ->onDelete('cascade');
            $table->decimal('ease_factor', 3, 2)->default(2.50);
            $table->unsignedInteger('interval_days')->default(0);
            $table->unsignedTinyInteger('repetitions')->default(0);
            $table->timestamp('next_review_at')->nullable()->index();
            $table->timestamp('last_reviewed_at')->nullable();
            $table->unsignedTinyInteger('last_grade')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'vocabulary_entry_id']);
        });

        // 9) tgb_daily_lessons — one row per user per date.
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

        // 10) tgb_quiz_attempts — per-question record for analytics and XP.
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

        // 11) tgb_conversation_states — short-lived state for multi-step flows.
        Schema::create('tgb_conversation_states', function (Blueprint $table) {
            $table->id();
            $table->string('telegram_chat_id', 64)->unique();
            $table->string('current_command', 32)->nullable();
            $table->json('state_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Drop in reverse order to respect FKs.
        Schema::dropIfExists('tgb_conversation_states');
        Schema::dropIfExists('tgb_quiz_attempts');
        Schema::dropIfExists('tgb_daily_lessons');
        Schema::dropIfExists('tgb_review_schedules');
        Schema::dropIfExists('tgb_grammar_entries');
        Schema::dropIfExists('tgb_vocabulary_entries');
        Schema::dropIfExists('tgb_user_paths');
        Schema::dropIfExists('tgb_topics');
        Schema::dropIfExists('tgb_learning_profiles');
        Schema::dropIfExists('tgb_linking_codes');
        Schema::dropIfExists('tgb_user_telegram_links');
    }
};
