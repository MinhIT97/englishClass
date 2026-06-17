<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('tgb_grammar_entries');
    }
};
