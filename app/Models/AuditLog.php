<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * AuditLog is an append-only record of security-relevant actions.
 *
 * Notes:
 *  - No updated_at column; rows are immutable by convention.
 *  - The target polymorphic relation is nullable to allow logging
 *    against external systems (e.g. a Telegram chat_id).
 *  - actor_id is nullable so we can still log actions when the actor
 *    has been deleted (nullOnDelete on the FK).
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_id',
        'action',
        'target_type',
        'target_id',
        'ip',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}