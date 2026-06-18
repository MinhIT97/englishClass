<?php

namespace Modules\TelegramBot\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Verifies the X-Telegram-Bot-Api-Secret-Token header matches config.
 * Returns 403 if invalid (instead of 401, to keep Telegram's retry policy calm).
 *
 * SECURITY: An empty secret is treated as misconfiguration in
 * production (HTTP 503) rather than silently disabled. Previously
 * this code skipped verification entirely when the secret was empty,
 * which would have allowed unauthenticated webhook forgery if the
 * env var was forgotten during deploy. Local dev still works because
 * APP_ENV != production.
 */
class VerifyTelegramSecret
{
    public function handle(Request $request, Closure $next)
    {
        $expected = (string) config('telegram.webhook_secret', '');

        if ($expected === '') {
            if (app()->environment('production')) {
                Log::error('[TelegramBot] Webhook secret missing in production — rejecting all requests');
                return response('service misconfigured', 503);
            }

            // Dev only: allow unconfigured secret to keep onboarding easy.
            Log::warning('[TelegramBot] Webhook secret not configured — verification skipped (non-production)');
            return $next($request);
        }

        $provided = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');
        if (! hash_equals($expected, $provided)) {
            Log::warning('[TelegramBot] Webhook secret mismatch', [
                'ip' => $request->ip(),
            ]);
            return response('forbidden', 403);
        }

        return $next($request);
    }
}
