<?php

namespace Modules\Flashcard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Flashcard\Database\Factories\PersonalVocabularyFactory;

class PersonalVocabulary extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['user_id', 'word', 'meaning', 'example', 'skill'];

    // protected static function newFactory(): PersonalVocabularyFactory
    // {
    //     // return PersonalVocabularyFactory::new();
    // }
}
