<?php

namespace Modules\Classroom\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Classroom\Enums\ClassroomPostType;
use Modules\Classroom\Models\Classroom;

class StoreClassroomPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Classroom $classroom */
        $classroom = $this->route('classroom');

        return $this->user() !== null && $classroom !== null && $this->user()->can('createPost', $classroom);
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string'],
            'type' => ['required', Rule::enum(ClassroomPostType::class)],
            'attachment' => ['nullable', 'file', 'max:51200'],
        ];
    }
}
