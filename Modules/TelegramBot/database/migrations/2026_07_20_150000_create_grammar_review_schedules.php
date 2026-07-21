<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tgb_grammar_review_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('grammar_entry_id')
                ->constrained('tgb_grammar_entries')
                ->onDelete('cascade');
            $table->decimal('ease_factor', 3, 2)->default(2.50);
            $table->unsignedInteger('interval_days')->default(0);
            $table->unsignedTinyInteger('repetitions')->default(0);
            $table->timestamp('next_review_at')->nullable()->index();
            $table->timestamp('last_reviewed_at')->nullable();
            $table->unsignedTinyInteger('last_grade')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'grammar_entry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tgb_grammar_review_schedules');
    }
};
