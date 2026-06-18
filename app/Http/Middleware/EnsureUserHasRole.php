<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reusable role guard.
 *
 * Usage:
 *   Route::middleware('role:admin')->group(...)
 *   Route::middleware('role:admin,teacher')->group(...)
 *
 * Why a separate middleware (instead of using `can:gate-name`):
 *   - Simpler mental model — admin routes just say `role:admin`.
 *   - Always returns 403 (never 401) so we don't leak whether the
 *     session is authenticated vs. authorized.
 *   - Works with the existing UserRole enum so role names stay
 *     refactor-safe.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if (! in_array($user->role, $roles, true)) {
            abort(403, 'Insufficient privileges.');
        }

        return $next($request);
    }
}