<?php

namespace Modules\Speaking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTranscriptRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'session_id' => 'required|exists:speaking_sessions,id',
            'content' => 'required|string',
            'feedback' => 'nullable|array',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
