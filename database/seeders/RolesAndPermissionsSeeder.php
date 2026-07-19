<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Seeds Spatie roles + permissions and binds them to the existing
 * UserRole enum semantics.
 *
 * Dual-source policy:
 *   - This seeder writes ONLY to Spatie's `roles`, `permissions`,
 *     and `role_has_permissions` tables.
 *   - It DOES NOT touch the `users.role` column. That column stays
 *     the fast path for the 18 existing string checks; Spatie is
 *     the source of truth for fine-grained `can()` calls.
 *   - `users.role` is kept in lockstep via the `RoleSynced` event
 *     and `SyncSpatieRole` listener (see `app/Events` + `app/Listeners`).
 *
 * Re-runnable: every call is `firstOrCreate` or `syncPermissions`,
 * so running this on an existing DB is idempotent.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions so subsequent code reads
        // the fresh DB state (Spatie caches role/permission lookups
        // aggressively; stale cache after a sync is the #1 footgun).
        $cacheStore = config('permission.cache.store') !== 'default'
            ? config('permission.cache.store')
            : null;
        Cache::store($cacheStore)->forget(config('permission.cache.key'));

        $permissions = [
            'access-ai-tutor',
            'manage-classroom',
            'view-analytics',
            'manage-users',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $teacher = Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        $student = Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $admin->syncPermissions([
            'manage-users',
            'manage-classroom',
            'view-analytics',
            'access-ai-tutor',
        ]);

        $teacher->syncPermissions([
            'manage-classroom',
            'view-analytics',
            'access-ai-tutor',
        ]);

        $student->syncPermissions([
            'access-ai-tutor',
        ]);
    }
}
