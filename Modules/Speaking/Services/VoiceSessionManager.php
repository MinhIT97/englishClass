<?php

namespace Modules\Speaking\Services;

use Illuminate\Support\Facades\Redis;

class VoiceSessionManager
{
    protected string $prefix = 'voice_sess:';

    /**
     * Thêm chunk âm thanh vào buffer của session
     */
    public function appendChunk(string $sessionId, string $base64Chunk): void
    {
        $key = $this->prefix . $sessionId . ':buffer';
        Redis::append($key, base64_decode($base64Chunk));
        Redis::expire($key, 300); // Tự hủy sau 5 phút nếu không hoạt động
    }

    /**
     * Lấy toàn bộ buffer và xóa nó (Reset cho câu tiếp theo)
     */
    public function getAndResetBuffer(string $sessionId): ?string
    {
        $key = $this->prefix . $sessionId . ':buffer';
        $data = Redis::get($key);
        Redis::del($key);
        
        return $data ? base64_encode($data) : null;
    }

    /**
     * Kiểm tra dung lượng buffer để quyết định khi nào xử lý (Ngưỡng ví dụ: 320KB ~ 2-3s audio)
     */
    public function shouldProcess(string $sessionId): bool
    {
        $key = $this->prefix . $sessionId . ':buffer';
        $size = Redis::strlen($key);
        return $size > 160000; // ~160KB
    }
}
