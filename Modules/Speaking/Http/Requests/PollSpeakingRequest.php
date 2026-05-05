<?php

namespace Modules\Speaking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PollSpeakingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'session_id' => ['required', 'string', 'exists:conversations,session_id'],
            'after' => ['nullable', 'integer'],
        ];
    }
}
