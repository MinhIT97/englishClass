<?php

namespace Modules\Course\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class CourseRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|in:active,inactive',
        ];
    }

    /**
     * Only teachers and admins may create or modify courses. Students
     * are rejected at the FormRequest layer so the policy is enforced
     * even if a controller forgets to call $this->authorize().
     *
     * Defense-in-depth: CourseController also checks role before the
     * quota service, so the user gets a 403 here AND a clear error.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return in_array($user->role, [UserRole::Admin->value, UserRole::Teacher->value], true)
            && $user->status === 'active';
    }
}
