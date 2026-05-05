<?php

namespace Modules\Speaking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChatSpeakingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'session_id' => ['required', 'string', 'exists:conversations,session_id'],
            'message' => ['nullable', 'string', 'max:2000'],
            'audio' => ['nullable', 'string'],
        ];
    }
}
