<?php

namespace Modules\TelegramBot\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrammarEntry extends Model
{
    protected $table = 'tgb_grammar_entries';

    protected $fillable = [
        'user_id',
        'topic_id',
        'structure',
        'explanation_vi',
        'explanation_en',
        'example_en',
        'example_vi',
        'difficulty',
    ];

    protected $casts = [
        'difficulty' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class, 'topic_id');
    }
}
