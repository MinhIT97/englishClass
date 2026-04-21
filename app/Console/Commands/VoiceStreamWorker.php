<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use WebSocket\Client;
use Illuminate\Support\Facades\Log;
// We'll broadcast manually via Event or Redis

#[Signature('voice:stream-worker')]
#[Description('Background daemon to process real-time voice streams to Gemini Live API')]
class VoiceStreamWorker extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Starting Voice Stream Worker...");
        $apiKey = config('services.gemini.key');
        
        if (empty($apiKey)) {
            $this->error("Gemini API key is completely missing!");
            return;
        }

        $url = "wss://generativelanguage.googleapis.com/ws/google.ai.generativelanguage.v1alpha.GenerativeService.BidiGenerateContent?key=" . $apiKey;

        try {
            $client = new Client($url, ['timeout' => 0.1]); // 100ms non-blocking
        } catch (\Exception $e) {
            $this->error("WebSocket Init failed: " . $e->getMessage());
            return;
        }

        // Send Setup Message
        $setup = [
            'setup' => [
                'model' => 'models/gemini-2.0-flash', // Natively supports BIDI
                'generation_config' => [
                    'response_modalities' => ['AUDIO'],
                ]
            ]
        ];
        
        $client->send(json_encode($setup));
        
        // Wait for setup completion
        try {
            $setupResp = $client->receive();
            $this->info("Connected to Gemini Live API: " . $setupResp);
        } catch (\Exception $e) {
            $this->error("Failed connection: " . $e->getMessage());
        }

        $this->info("Listening for audio frames on Redis voice_stream_queue...");

        while (true) {
            // 1. Process Frontend -> Gemini
            $item = Redis::lpop('voice_stream_queue');
            if ($item) {
                $payload = json_decode($item, true);
                if (isset($payload['audio'])) {
                    $realtimeInput = [
                        'realtime_input' => [
                            'media_chunks' => [
                                [
                                    'mime_type' => 'audio/webm', // Or PCM
                                    'data' => $payload['audio']
                                ]
                            ]
                        ]
                    ];
                    $client->send(json_encode($realtimeInput));
                }
            }

            // 2. Process Gemini -> Frontend (WebSocket -> Reverb Broadcast)
            try {
                $response = $client->receive();
                if ($response && str_contains($response, 'serverContent')) {
                    $data = json_decode($response, true);
                    
                    if (isset($data['serverContent']['modelTurn']['parts'])) {
                        foreach ($data['serverContent']['modelTurn']['parts'] as $part) {
                            if (isset($part['inlineData']) && isset($part['inlineData']['data'])) {
                                $audioBase64 = $part['inlineData']['data'];
                                // Use basic event broadcast
                                broadcast(new \App\Events\VoiceResponseEvent($payload['session_id'] ?? 'global', null, $audioBase64))->toOthers();
                            }
                            if (isset($part['text'])) {
                                // Text stream
                                broadcast(new \App\Events\VoiceResponseEvent($payload['session_id'] ?? 'global', $part['text'], null))->toOthers();
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                // Ignore timeouts!
            }

            usleep(20000); // 20ms
        }
    }
}

