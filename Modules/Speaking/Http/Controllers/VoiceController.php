<?php

namespace Modules\Speaking\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Speaking\Services\VoiceService;
use Modules\Speaking\Services\VoiceSessionManager;

class VoiceController extends Controller
{
    protected VoiceService $voiceService;
    protected VoiceSessionManager $sessionManager;

    public function __construct(VoiceService $voiceService, VoiceSessionManager $sessionManager)
    {
        $this->voiceService = $voiceService;
        $this->sessionManager = $sessionManager;
    }

    /**
     * Nhận chunk âm thanh và quyết định khi nào kích hoạt pipeline
     */
    public function handleChunk(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
            'chunk'      => 'required|string', // Base64
            'history'    => 'required|array'
        ]);

        $sessionId = $request->session_id;

        // 1. Thêm vào buffer
        $this->sessionManager->appendChunk($sessionId, $request->chunk);

        // 2. Nếu buffer đủ lớn (~2 giây), kích hoạt xử lý AI
        if ($this->sessionManager->shouldProcess($sessionId)) {
            // Chạy trong background hoặc xử lý trực tiếp nếu muốn low latency cực thấp
            $this->voiceService->processVoice($sessionId, $request->history);
        }

        return response()->json(['status' => 'received']);
    }
}
