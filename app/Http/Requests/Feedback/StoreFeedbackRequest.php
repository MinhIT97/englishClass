<?php

namespace App\Http\Requests\Feedback;

use Illuminate\Foundation\Http\FormRequest;

class StoreFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'feedback_type' => 'required|string',
            'rating' => 'required|integer|min:1|max:5',
            'message' => 'required|string',
            'email' => 'nullable|email',
        ];
    }
}
