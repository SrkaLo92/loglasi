<?php

namespace App\Http\Requests\Messages;

use App\Services\Messages\MessageBody;
use Illuminate\Foundation\Http\FormRequest;

/** Message body for the site and the API: HTML removed, then required and at most `messages.max_length` characters. */
class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // routes require login; conversation access is checked by ConversationPolicy
    }

    protected function prepareForValidation(): void
    {
        $raw = $this->input('body');
        if (is_string($raw)) {
            $this->merge(['body' => MessageBody::clean($raw)]);
        }
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.(int) config('messages.max_length')],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'Napišite poruku.',
            'body.string' => 'Napišite poruku.',
            'body.max' => 'Poruka može da ima najviše :max znakova.',
        ];
    }

    public function body(): string
    {
        return (string) $this->validated('body');
    }
}
