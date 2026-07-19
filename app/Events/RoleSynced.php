<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired whenever a user's role changes (or is set for the first time).
 *
 * The `users.role` string column is the authoritative write side — the
 * 18 existing string checks (`$user->role === 'admin'`, `EnsureUserHasRole`
 * middleware, `Gate::define('admin-access', ...)`) keep working as-is.
 * This event is the bridge that keeps the Spatie pivot in lockstep so
 * `hasRole()` / `can()` checks return the same answer.
 *
 * Fire from:
 *   - `Modules\Auth\Services\AuthService::register` (new user, role='student')
 *   - `Modules\Auth\Http\Controllers\AdminUserController::{webApprove,approve}`
 *     (after admin changes `users.role`)
 *   - Anywhere else `users.role` is mutated by trusted code.
 */
class RoleSynced
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $newRole,
    ) {
    }
}
