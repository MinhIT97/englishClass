<?php

namespace Modules\TelegramBot\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\TelegramBot\Models\LearningProfile;
use Modules\TelegramBot\Models\UserTelegramLink;

class TelegramSettingsController extends Controller
{
    /**
     * GET /student/settings/telegram
     */
    public function show(Request $request)
    {
        $user = $request->user();

        $link = UserTelegramLink::query()->where('user_id', $user->id)->first();
        $profile = LearningProfile::query()->where('user_id', $user->id)->first();

        return view('telegramBot::student.settings.telegram', [
            'link' => $link,
            'profile' => $profile,
            'botUsername' => config('telegram.bot_username', 'EnglishClassBot'),
        ]);
    }

    /**
     * POST /student/settings/telegram/unlink
     */
    public function unlink(Request $request)
    {
        $user = $request->user();
        UserTelegramLink::query()->where('user_id', $user->id)->delete();
        return back()->with('status', 'Đã hủy liên kết Telegram.');
    }

    /**
     * POST /student/settings/telegram/dismiss-banner
     * Persist a "don't show again" flag for the current session.
     */
    public function dismissBanner(Request $request)
    {
        $request->session()->put('tgb_banner_dismissed', true);
        return response()->json(['ok' => true]);
    }
}
