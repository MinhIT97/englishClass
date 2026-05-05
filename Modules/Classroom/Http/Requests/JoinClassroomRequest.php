<?php

namespace Modules\Classroom\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JoinClassroomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'invite_code' => ['required', 'string', 'max:20'],
        ];
    }
}
