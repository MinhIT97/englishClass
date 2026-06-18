<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add per-user lesson quota fields.
     *
     *  - lesson_limit: daily cap for non-admin users (default 3, same as
     *    TelegramBot's EXTRA_DAILY_LIMIT so the policy is consistent
     *    across all lesson types).
     *  - is_unlimited: when true, the user bypasses the quota entirely.
     *    Set by admins via the lesson-request approval flow.
     *
     * Admins themselves are not constrained by these fields; the
     * LessonQuotaService short-circuits on `role === 'admin'`.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('lesson_limit')->default(3)->after('can_request_extra_lesson');
            $table->boolean('is_unlimited')->default(false)->after('lesson_limit');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['lesson_limit', 'is_unlimited']);
        });
    }
};