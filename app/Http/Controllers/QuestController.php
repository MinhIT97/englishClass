<?php

namespace App\Http\Controllers;

use App\Services\GamificationService2;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuestController extends Controller
{
    public function __construct(protected GamificationService2 $engine)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'quests' => $this->engine->activeQuestsForUser($request->user()),
        ]);
    }
}