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
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'voice:stream-worker';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Background daemon to process real-time voice streams to Gemini Live API';

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

        // Send Setup Message with System Instruction
        $setup = [
            'setup' => [
                'model' => 'models/gemini-2.0-flash',
                'generation_config' => [
                    'response_modalities' => ['AUDIO', 'TEXT'], // Request both for feedback
                    'speech_config' => [
                        'voice_config' => [
                            'prebuilt_voice_config' => [
                                'voice_name' => 'Aoede' // Friendly female voice
                            ]
                        ]
                    ]
                ],
                'system_instruction' => [
                    'parts' => [
                        ['text' => "You are a professional IELTS Speaking Examiner and Pronunciation Coach. 
                        Your goal is to conduct a natural, interactive speaking interview.
                        1. ALWAYS respond with voice (AUDIO).
                        2. If the user mispronounces a word or makes a grammar mistake, provide a very brief correction in TEXT modality alongside your audio response.
                        3. Keep the conversation flowing like a real Part 1 or Part 2 interview.
                        4. Be encouraging but maintain high academic standards."]
                    ]
                ]
            ]
        ];

        $client->send(json_encode($setup));

        // Wait for setup completion
        try {
            $setupResp = $client->receive();
        $this->info("Listening for audio frames on Redis voice_stream_queue...");
        $clients = [];

        while (true) {
            // 1. Process Frontend -> Gemini
            $item = Redis::lpop('voice_stream_queue');
            if ($item) {
                $payload = json_decode($item, true);
                $sessId = $payload['session_id'] ?? 'global';
                
                if (!isset($clients[$sessId])) {
                    $this->info("Starting new Gemini session for: $sessId");
                    $clients[$sessId] = new Client($url, ['timeout' => 0.1]);
                    $clients[$sessId]->send(json_encode($setup));
                    try { $clients[$sessId]->receive(); } catch (\Exception $e) {}
                }

                if (isset($payload['audio']) && $payload['audio']) {
                    $realtimeInput = [
                        'realtime_input' => [
                            'media_chunks' => [['mime_type' => 'audio/pcm', 'data' => $payload['audio']]]
                        ]
                    ];
                    $clients[$sessId]->send(json_encode($realtimeInput));
                }
            }

            // 2. Process All active Gemini sessions -> Frontend
            foreach ($clients as $sid => $client) {
                try {
                    $response = $client->receive();
                    if ($response && str_contains($response, 'serverContent')) {
                        $data = json_decode($response, true);
                        if (isset($data['serverContent']['modelTurn']['parts'])) {
                            foreach ($data['serverContent']['modelTurn']['parts'] as $part) {
                                if (isset($part['inlineData']['data'])) {
                                    broadcast(new \App\Events\VoiceResponseEvent($sid, null, $part['inlineData']['data']))->toOthers();
                                }
                                if (isset($part['text'])) {
                                    broadcast(new \App\Events\VoiceResponseEvent($sid, $part['text'], null))->toOthers();
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Timeout is expected
                }
            }

            usleep(10000); // 10ms
        }
    }
}
