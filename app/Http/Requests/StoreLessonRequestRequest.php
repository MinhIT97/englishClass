<?php

namespace App\Http\Requests;

use App\Models\LessonRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLessonRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Any authenticated non-admin user may submit. Admins don't need
        // to request because they already have unlimited quota.
        return $this->user() !== null && ! $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'lesson_type' => ['required', Rule::in([
                LessonRequest::TYPE_COURSE,
                LessonRequest::TYPE_CLASSROOM,
                LessonRequest::TYPE_DAILY_LESSON,
            ])],
            'requested_extra' => ['required', 'integer', 'min:1', 'max:50'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}