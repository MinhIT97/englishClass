<?php

namespace Modules\Classroom\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Classroom\Models\ClassroomPost;

class StoreClassroomFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ClassroomPost $post */
        $post = $this->route('post');

        return $this->user() !== null && $post !== null && $this->user()->can('giveFeedback', $post);
    }

    public function rules(): array
    {
        return [
            'feedback_content' => ['required', 'string'],
            'grade' => ['nullable', 'string', 'max:50'],
        ];
    }
}
