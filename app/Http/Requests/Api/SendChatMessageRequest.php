<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SendChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:5000'],
            'conversation_id' => ['nullable', 'uuid'],
            'response_language' => ['nullable', 'in:en,lo'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.required' => 'Please enter a message.',
            'message.max' => 'The message must not exceed 5000 characters.',
            'conversation_id.uuid' => 'The conversation id must be a valid UUID.',
            'response_language.in' => 'The response language must be "en" or "lo".',
        ];
    }
}
