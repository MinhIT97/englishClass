<?php

namespace Modules\Question\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Question\Services\QuestionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class QuestionController extends Controller
{
    protected $service;

    public function __construct(QuestionService $service)
    {
        $this->service = $service;
    }

    /**
     * List filtered questions.
     */
    public function index(Request $request)
    {
        $filters = $request->only(['skill', 'type', 'topic']);
        $questions = $this->service->paginate($filters, $request->get('limit', 15));

        return view('question::admin.index', compact('questions'));
    }

    public function create()
    {
        return view('question::admin.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'skill' => 'required|string',
            'type' => 'required|string',
            'topic' => 'required|string',
            'difficulty' => 'required|string',
            'content' => 'required|array',
            'audio_file' => 'nullable|file|mimes:mp3,wav|max:5120',
        ]);

        if ($request->hasFile('audio_file')) {
            $path = $request->file('audio_file')->store('listening_audio', 'public');
            $data['content']['audio_path'] = '/storage/' . $path;
        } elseif ($request->filled('ai_voice_path')) {
            $data['content']['audio_path'] = $request->ai_voice_path;
        }

        $this->service->create($data);

        return redirect()->route('question.index')->with('success', 'Question created successfully!');
    }

    public function delete($id)
    {
        $this->service->delete($id);
        return back()->with('success', 'Question deleted!');
    }

    public function generateVoice(Request $request)
    {
        $request->validate(['text' => 'required|string|max:500']);
        
        $path = $this->service->generateVoice($request->text);
        
        if ($path) {
            return response()->json(['path' => $path]);
        }
        
        return response()->json(['error' => 'TTS failed'], 500);
    }
}
