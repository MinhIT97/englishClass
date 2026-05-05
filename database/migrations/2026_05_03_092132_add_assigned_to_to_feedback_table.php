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
        Schema::table('feedback', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feedback', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
        });
    }

};
