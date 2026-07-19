<?php

namespace App\Listeners;

use App\Events\RoleSynced;

/**
 * Keeps Spatie's `model_has_roles` pivot in sync with `users.role`.
 *
 * Why `syncRoles` (not `assignRole`): a single Spatie row per user per
 * role is the contract, and admin role changes can demote a user (e.g.
 * teacher -> student). `syncRoles([$newRole])` is idempotent and
 * replaces the entire role set in one statement — safer than add/remove
 * diffs against an unknown starting state.
 *
 * Failure mode: if `$event->newRole` does not exist as a Role (e.g.
 * someone passed 'super-admin' before that role is seeded), Spatie will
 * throw. Callers MUST only dispatch RoleSynced with role strings that
 * exist in `app/Enums/UserRole.php` (admin / teacher / student).
 */
class SyncSpatieRole
{
    public function handle(RoleSynced $event): void
    {
        $event->user->syncRoles([$event->newRole]);
    }
}
