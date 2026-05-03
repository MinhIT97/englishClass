<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeployNotifyRequest;
use App\Services\TelegramNotifierService;

class DeployNotifyController extends Controller
{
    protected TelegramNotifierService $telegram;

    public function __construct(TelegramNotifierService $telegram)
    {
        $this->telegram = $telegram;
    }

    public function notify(DeployNotifyRequest $request)
    {
        $this->telegram->sendDeployNotification($request->validated());

        return response()->json(['message' => 'Notification sent']);
    }
}
