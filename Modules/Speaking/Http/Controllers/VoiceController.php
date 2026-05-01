<?php

namespace Modules\Speaking\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Speaking\Services\VoiceSessionManager;
use App\Jobs\ProcessVoiceChunkJob;

class VoiceController extends Controller
{
    public function __construct(protected VoiceSessionManager $manager) {}

    /**
     * Nhận chunk âm thanh Realtime từ Frontend
     */
    public function handleChunk(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
            'chunk'      => 'required|string',
            'history'    => 'required|array'
        ]);

        $sessionId = $request->session_id;

        // 1. Lưu vào buffer Redis
        $this->manager->append($sessionId, $request->chunk);

        // 2. Kiểm tra ngưỡng để kích hoạt Job xử lý
        if ($this->manager->shouldProcess($sessionId)) {
            ProcessVoiceChunkJob::dispatch($sessionId, $request->history);
        }

        return response()->json(['status' => 'buffered']);
    }
}
