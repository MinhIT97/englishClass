<?php

namespace Modules\Flashcard\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FlashcardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('flashcard::index', [
            'myVocab' => \Modules\Flashcard\Models\PersonalVocabulary::where('user_id', auth()->id())->get()
        ]);
    }

    public function saveToPersonal(Request $request)
    {
        $request->validate([
            'word' => 'required|string',
            'meaning' => 'required|string',
            'example' => 'nullable|string',
            'skill' => 'nullable|string',
        ]);

        $record = \Modules\Flashcard\Models\PersonalVocabulary::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'word' => $request->word
            ],
            [
                'meaning' => $request->meaning,
                'example' => $request->example,
                'skill' => $request->skill,
            ]
        );

        return response()->json([
            'message' => 'Saved to your notebook!',
            'was_created' => $record->wasRecentlyCreated
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('flashcard::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {}

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('flashcard::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('flashcard::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id) {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id) {}
}
