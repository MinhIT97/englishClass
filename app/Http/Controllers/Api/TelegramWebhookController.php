<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TelegramNotifierService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    protected TelegramNotifierService $telegram;

    public function __construct(TelegramNotifierService $telegram)
    {
        $this->telegram = $telegram;
    }

    public function handle(Request $request)
    {
        $update = $request->all();
        Log::info("Telegram Webhook received", $update);

        if (isset($update['callback_query'])) {
            return $this->handleCallbackQuery($update['callback_query']);
        }

        return response()->json(['status' => 'ignored']);
    }

    protected function handleCallbackQuery(array $callbackQuery)
    {
        $callbackQueryId = $callbackQuery['id'];
        $data = $callbackQuery['data'];
        $chatId = $callbackQuery['message']['chat']['id'];
        $messageId = $callbackQuery['message']['message_id'];

        // 1. Parse action and user ID
        if (preg_match('/^(approve|reject)_user_(\d+)$/', $data, $matches)) {
            $action = $matches[1];
            $userId = $matches[2];

            $user = User::find($userId);

            if (!$user) {
                return $this->telegram->answerCallbackQuery($callbackQueryId, "User not found.");
            }

            // 2. Prevent double action
            if ($user->status === 'approved' || $user->status === 'rejected') {
                return $this->telegram->answerCallbackQuery($callbackQueryId, "This user has already been processed.");
            }

            // 3. Process action
            $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
            $user->update(['status' => $newStatus]);

            // 4. Stop loading spinner & Notify
            $this->telegram->answerCallbackQuery($callbackQueryId, "User {$action}d successfully!");

            // 5. Update Message UI (Remove buttons)
            $newText = $callbackQuery['message']['text'] . "\n\n✅ *Action:* User has been " . strtoupper($newStatus);
            $this->telegram->editMessageText($chatId, $messageId, $newText);
        }

        return response()->json(['status' => 'ok']);
    }
}
