<?php

namespace Modules\Classroom\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Classroom\Models\ClassroomPost;

class StoreClassroomCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ClassroomPost $post */
        $post = $this->route('post');

        return $this->user() !== null && $post !== null && $this->user()->can('comment', $post);
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:1000'],
        ];
    }
}
