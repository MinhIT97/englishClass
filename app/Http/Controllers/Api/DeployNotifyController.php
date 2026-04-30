<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TelegramNotifierService;
use Illuminate\Http\Request;

class DeployNotifyController extends Controller
{
    protected TelegramNotifierService $telegram;

    public function __construct(TelegramNotifierService $telegram)
    {
        $this->telegram = $telegram;
    }

    public function notify(Request $request)
    {
        $request->validate([
            'status' => 'required|string|in:success,failed',
            'branch' => 'nullable|string',
            'commit' => 'nullable|string',
            'health' => 'nullable|array'
        ]);

        $this->telegram->sendDeployNotification($request->all());

        return response()->json(['message' => 'Notification sent']);
    }
}
