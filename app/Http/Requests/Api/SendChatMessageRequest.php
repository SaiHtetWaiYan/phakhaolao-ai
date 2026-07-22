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
            // A photo on its own is a valid request: it asks the assistant to
            // identify the species.
            'message' => ['required_without:image', 'nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
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
            'message.required_without' => 'Please enter a message or attach a photo.',
            'message.max' => 'The message must not exceed 5000 characters.',
            'image.image' => 'The file must be an image.',
            'image.mimes' => 'The image must be a JPG, PNG, WebP, or GIF.',
            'image.max' => 'The image must not exceed 10MB.',
            'conversation_id.uuid' => 'The conversation id must be a valid UUID.',
            'response_language.in' => 'The response language must be "en" or "lo".',
        ];
    }
}
