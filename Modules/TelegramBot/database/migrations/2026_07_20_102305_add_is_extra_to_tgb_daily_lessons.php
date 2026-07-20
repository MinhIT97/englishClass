<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tgb_daily_lessons', function (Blueprint $table) {
            // Drop the old unique index first.
            $table->dropUnique('tgb_daily_lessons_user_id_lesson_date_unique');

            // Add is_extra column to distinguish extra lessons from scheduled ones.
            $table->boolean('is_extra')->default(false)->after('lesson_date');

            // Re-create the unique index with is_extra included, so a user
            // can have both a scheduled lesson and one or more extra lessons
            // on the same date.
            $table->unique(['user_id', 'lesson_date', 'is_extra']);
        });
    }

    public function down(): void
    {
        Schema::table('tgb_daily_lessons', function (Blueprint $table) {
            $table->dropUnique('tgb_daily_lessons_user_id_lesson_date_is_extra_unique');
            $table->dropColumn('is_extra');
            $table->unique(['user_id', 'lesson_date']);
        });
    }
};
