<?php

namespace Modules\TelegramBot\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Modules\TelegramBot\Models\LinkingCode;
use Modules\TelegramBot\Services\TelegramOnboardingService;

class LinkingCodeController extends Controller
{
    /**
     * POST /student/settings/telegram/linking-code
     * Generate a fresh 8-char code (max 3 per hour per user).
     */
    public function generate(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $key = 'tgb:linking:' . $user->id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->withErrors([
                'linking_code' => "Bạn đã tạo quá nhiều mã. Vui lòng thử lại sau {$seconds} giây.",
            ]);
        }

        RateLimiter::hit($key, 3600);

        $code = TelegramOnboardingService::generateCode($user->id);

        return back()->with([
            'linking_code' => $code->code,
            'linking_code_expires_at' => $code->expires_at->toIso8601String(),
        ]);
    }
}
