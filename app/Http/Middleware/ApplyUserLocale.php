<?php

namespace App\Http\Middleware;

use App\Models\UserPreference;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the authenticated user's saved locale preference at the
 * start of each request, BEFORE controllers run. Falls back to the
 * default app locale if no preference is stored.
 */
class ApplyUserLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user) {
            $pref = UserPreference::where('user_id', $user->id)->value('locale');
            if ($pref) {
                app()->setLocale($pref);
            }
        }
        return $next($request);
    }
}