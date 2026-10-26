<?php

namespace App\Http\Requests\Pages;

use App\Support\Pages\PageText;
use App\Support\Phone;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Contact form with the fields of the old "Pomoć" form: name, e-mail or phone, topic, message. */
class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public form; bot protection lives in the controller (honeypot, timing, rate limit)
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ime' => ['required', 'string', 'max:80'],
            'kontakt' => ['required', 'string', 'max:120', function (string $attribute, mixed $value, Closure $fail) {
                if (is_string($value) && self::email($value) === null && Phone::normalize($value) === '') {
                    $fail(PageText::get('kontakt.poruka.kontakt'));
                }
            }],
            'tema' => ['required', 'string', Rule::in(PageText::list('kontakt.teme'))],
            'poruka' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => PageText::get('kontakt.poruka.obavezno'),
            'max' => PageText::get('kontakt.poruka.predugacak'),
            'min' => PageText::get('kontakt.poruka.prekratak'),
            'tema.in' => PageText::get('kontakt.poruka.tema'),
        ];
    }

    /** The address when the contact field holds a valid e-mail, otherwise null. */
    public static function email(string $value): ?string
    {
        $value = trim($value);

        return str_contains($value, '@') && filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? $value : null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ime' => is_string($this->ime) ? trim($this->ime) : $this->ime,
            'kontakt' => is_string($this->kontakt) ? trim($this->kontakt) : $this->kontakt,
        ]);
    }
}
