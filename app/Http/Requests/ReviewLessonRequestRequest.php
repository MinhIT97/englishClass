<?php

namespace App\Http\Requests;

use App\Models\LessonRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewLessonRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'approved_extra' => ['nullable', 'integer', 'min:1', 'max:50', 'required_if:decision,approve'],
            'grant_unlimited' => ['nullable', 'boolean'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}