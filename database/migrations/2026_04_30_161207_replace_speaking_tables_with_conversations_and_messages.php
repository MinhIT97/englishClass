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
        // Drop old tables safely
        Schema::dropIfExists('transcripts');
        Schema::dropIfExists('speaking_sessions');

        // Create new conversations table
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('session_id')->unique();
            $table->timestamps();
            
            $table->index('user_id');
        });

        // Create new messages table
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->enum('role', ['system', 'user', 'assistant']);
            $table->text('content');
            $table->json('ai_feedback')->nullable(); // Stores original, corrected, explanation
            $table->string('audio_url')->nullable();
            $table->timestamps();
            
            $table->index(['conversation_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');

        // Note: We are not recreating the old tables in down() because this is a destructive replacement.
    }
};
