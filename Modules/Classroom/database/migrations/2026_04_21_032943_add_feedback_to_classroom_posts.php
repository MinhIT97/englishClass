<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('classroom_posts', function (Blueprint $table) {
            $table->text('feedback_content')->nullable()->after('attachment_path');
            $table->string('grade')->nullable()->after('feedback_content');
            $table->foreignId('feedback_by')->nullable()->after('grade')->constrained('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classroom_posts', function (Blueprint $table) {
            $table->dropForeign(['feedback_by']);
            $table->dropColumn(['feedback_content', 'grade', 'feedback_by']);
        });
    }
};
