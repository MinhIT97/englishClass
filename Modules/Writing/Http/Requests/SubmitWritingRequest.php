<?php

namespace Modules\Writing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitWritingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'essay_content' => ['required', 'string', 'min:50'],
            'task_type' => ['required', Rule::in(['task_1', 'task_2'])],
        ];
    }
}
