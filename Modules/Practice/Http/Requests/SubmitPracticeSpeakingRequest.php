<?php

namespace Modules\Practice\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitPracticeSpeakingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'question_id' => ['required', 'exists:questions,id'],
            'audio' => ['required', 'string'],
        ];
    }
}
