<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('tgb_learning_profiles');
    }
};
