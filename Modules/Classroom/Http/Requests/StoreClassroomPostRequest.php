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

            // SECURITY: Restrict uploaded files to safe MIME types.
            // Without this, a teacher could upload a .php or .phtml
            // file that — depending on the storage disk — could be
            // executed by the web server if the storage path is
            // ever served as static assets. 50 MB hard cap to avoid
            // disk-fill DoS.
            'attachment' => [
                'nullable',
                'file',
                'max:51200',
                'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,mp3,mp4',
            ],
        ];
    }
}
