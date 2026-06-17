<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
        Schema::dropIfExists('tgb_conversation_states');
    }
};
