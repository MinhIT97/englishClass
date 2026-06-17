<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('tgb_vocabulary_entries');
    }
};
