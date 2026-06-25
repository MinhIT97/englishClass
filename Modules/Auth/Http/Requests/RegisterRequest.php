<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => [
                'required',
                'string',
                'confirmed',
                // SECURITY (SEC-046): require mixed case + at least one
                // digit + at least one symbol. Length stays at 8 (NIST
                // SP 800-63B no longer treats short passwords as
                // inherently weak) but complexity rules raise the cost
                // of credential-stuffing and dictionary attacks against
                // accounts where users reuse simple passwords.
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
            'target_band' => 'nullable|numeric|between:1,9',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
