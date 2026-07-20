<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Modules\TelegramBot\Models\GrammarEntry;

class GrammarController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $entries = GrammarEntry::query()
            ->where('user_id', $user->id)
            ->with('topic')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('flashcards.grammar-list', compact('entries'));
    }
}
