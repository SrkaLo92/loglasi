<?php

namespace App\Http\Requests\Stranice;

use App\Support\Stranice\PageText;
use App\Support\Stranice\Telefon;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KontaktRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // forma je javna; zaštita je u kontroleru (zamka, vreme, limit)
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ime' => ['required', 'string', 'max:80'],
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            'telefon' => ['nullable', 'string', 'max:30', function (string $attribute, mixed $value, Closure $fail) {
                if (is_string($value) && trim($value) !== '' && Telefon::normalize($value) === '') {
                    $fail('Unesite ispravan broj telefona, npr. 65 123 4567.');
                }
            }],
            'tema' => ['required', 'string', Rule::in(PageText::list('kontakt.teme'))],
            'poruka' => ['required', 'string', 'min:10', 'max:2000'],
            'saglasnost' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.email' => 'Unesite ispravnu adresu e-pošte.',
            'tema.in' => 'Izaberite temu sa liste.',
            'saglasnost.accepted' => 'Potvrdite da se slažete da se podaci koriste za odgovor na poruku.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'ime' => 'ime i prezime',
            'email' => 'e-pošta',
            'telefon' => 'telefon',
            'tema' => 'tema',
            'poruka' => 'poruka',
            'saglasnost' => 'saglasnost',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ime' => is_string($this->ime) ? trim($this->ime) : $this->ime,
            'email' => is_string($this->email) ? trim($this->email) : $this->email,
        ]);
    }
}
