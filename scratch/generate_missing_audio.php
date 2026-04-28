<?php

use Modules\Question\Models\Question;
use App\Services\AI\GeminiService;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$geminiService = app(GeminiService::class);
$questions = Question::where('skill', 'listening')->get();

echo "Found " . $questions->count() . " listening questions.\n";

foreach ($questions as $question) {
    if (!isset($question->content['audio_path'])) {
        echo "Generating audio for Question ID: " . $question->id . "... ";
        
        $text = $question->content['text'] ?? $question->content['question'] ?? '';
        $answer = $question->content['answer'] ?? '';
        
        // Clean text: remove hints and fill gaps
        $cleanText = preg_replace('/\s*\(Audio transcript hint:.*?\)\s*/i', '', $text);
        $cleanText = str_replace(['[____]', '[___]', '[__]', '[blank]'], $answer, $cleanText);
        
        $audioPath = $geminiService->generateVoice($cleanText);
        
        if ($audioPath) {
            $content = $question->content;
            $content['audio_path'] = $audioPath;
            $question->content = $content;
            $question->save();
            echo "Success: $audioPath\n";
        } else {
            echo "Failed.\n";
        }
    } else {
        echo "Question ID: " . $question->id . " already has audio.\n";
    }
}

echo "Maintenance complete.\n";
