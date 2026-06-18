<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only audit log for sensitive actions.
     *
     * Captured via AuditLogger::log() inside controllers, services, or
     * the AuditAdminActions middleware. Rows are never updated or
     * deleted by application code (no model events fire on this table
     * because AuditLog does not boot the standard SoftDeletes trait).
     *
     * Retention: prune rows older than 365 days via a scheduled
     * `php artisan model:prune` job (see App\Console\Kernel).
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->string('target_type')->nullable();
            $table->string('target_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['actor_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};