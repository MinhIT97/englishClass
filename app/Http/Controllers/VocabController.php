<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Modules\TelegramBot\Models\VocabularyEntry;

class VocabController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = VocabularyEntry::query()
            ->where('user_id', $user->id)
            ->with('topic')
            ->orderByDesc('id');

        if ($topicId = $request->query('topic')) {
            $query->where('topic_id', (int) $topicId);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('word', 'like', "%{$search}%")
                  ->orWhere('meaning_vi', 'like', "%{$search}%");
            });
        }

        $words = $query->paginate(20)->withQueryString();

        $topics = \Modules\TelegramBot\Models\Topic::query()
            ->whereIn('id', VocabularyEntry::query()
                ->where('user_id', $user->id)
                ->distinct('topic_id')
                ->pluck('topic_id'))
            ->orderBy('name_vi')
            ->get();

        return view('flashcards.vocab-list', compact('words', 'topics'));
    }
}
