<?php

namespace Modules\TelegramBot\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Verifies the X-Telegram-Bot-Api-Secret-Token header matches config.
 * Returns 403 if invalid (instead of 401, to keep Telegram's retry policy calm).
 */
class VerifyTelegramSecret
{
    public function handle(Request $request, Closure $next)
    {
        $expected = (string) config('telegram.webhook_secret', '');
        if ($expected === '') {
            // No secret configured - skip verification (dev only).
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
