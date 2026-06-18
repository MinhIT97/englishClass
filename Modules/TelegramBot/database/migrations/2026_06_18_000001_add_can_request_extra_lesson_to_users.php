<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add `can_request_extra_lesson` flag to users.
 *
 * When enabled, the user is allowed to use the Telegram bot's
 * `/extra` command (and the corresponding menu button) to request
 * additional on-demand lessons beyond the daily scheduled one.
 *
 * Default = false (opt-in). Admin grants permission manually via DB
 * or (later) an admin panel. A per-day limit (3 lessons) is enforced
 * in TelegramLearningService::sendExtraLesson().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_request_extra_lesson')
                ->default(false)
                ->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('can_request_extra_lesson');
        });
    }
};