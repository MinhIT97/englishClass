<?php

namespace Modules\Speaking\Services;

use Illuminate\Support\Facades\Redis;

class VoiceSessionManager
{
    protected string $prefix = 'voice_buffer:';

    public function append(string $sessionId, string $base64Chunk): void
    {
        $key = $this->prefix . $sessionId;
        Redis::append($key, base64_decode($base64Chunk));
        Redis::expire($key, 600);
    }

    public function getAndClear(string $sessionId): ?string
    {
        $key = $this->prefix . $sessionId;
        $data = Redis::get($key);
        Redis::del($key);
        return $data ? base64_encode($data) : null;
    }

    public function shouldProcess(string $sessionId): bool
    {
        $key = $this->prefix . $sessionId;
        return Redis::strlen($key) > 200000; // ~200KB (~2-3s audio)
    }
}
