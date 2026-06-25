<?php

namespace Modules\Speaking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChatSpeakingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'session_id' => ['required', 'string', 'exists:conversations,session_id'],
            'message' => ['nullable', 'string', 'max:2000'],
            // SECURITY (SEC-043): audio is a Base64-encoded string in the
            // JSON body (not a file upload, so Laravel's mimes:* rule
            // does not apply). Cap the raw string length so a 5 MB
            // (~6.6 MB Base64) payload can't OOM the request pipeline.
            // The service layer additionally decodes and rejects payloads
            // whose decoded bytes contain PHP open tags / null bytes.
            'audio' => ['nullable', 'string', 'max:7168000'], // ~5 MB decoded
        ];
    }

    public function messages(): array
    {
        return [
            'audio.max' => 'Audio quá dài (tối đa ~5MB sau khi giải mã).',
        ];
    }
}
