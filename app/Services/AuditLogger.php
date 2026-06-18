<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Single entry-point for writing audit log rows.
 *
 * Usage:
 *   app(AuditLogger::class)->log('user.approved', $user, ['note' => 'auto']);
 *
 * Why centralise: ensures every entry carries consistent fields
 * (ip, user_agent from the current request, ISO-8601 timestamp) so
 * that the admin audit-log viewer can filter and join reliably.
 */
class AuditLogger
{
    public function log(string $action, ?Model $target = null, array $metadata = [], ?User $actor = null): AuditLog
    {
        $request = request();

        return AuditLog::create([
            'actor_id' => $actor?->id ?? $this->resolveActorId($request),
            'action' => $action,
            'target_type' => $target ? $target->getMorphClass() : null,
            'target_id' => $target ? (string) $target->getKey() : null,
            'ip' => $request ? $request->ip() : null,
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 500) : null,
            'metadata' => $metadata ?: null,
        ]);
    }

    private function resolveActorId(?Request $request): ?int
    {
        if (! $request) {
            return null;
        }

        // Auth via web session or API guard; both expose ->user().
        $user = $request->user();
        return $user?->getKey();
    }
}