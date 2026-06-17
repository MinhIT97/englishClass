<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tgb_topics', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->enum('purpose', ['ielts', 'daily', 'business'])->index();
            $table->string('name_vi', 128);
            $table->string('name_en', 128);
            $table->unsignedInteger('order_index')->default(0);
            $table->unsignedTinyInteger('difficulty')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['purpose', 'order_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tgb_topics');
    }
};
