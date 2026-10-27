<?php

namespace App\Support\Firms;

use App\Models\Firm;
use App\Rules\PhoneNumber;
use App\Rules\PublicName;
use App\Support\Phone;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validation and cleaning of the firm profile fields, shared by /moj-nalog/firma/ and PATCH /me/firm.
 * Text is stored without HTML (output is escaped anyway). Admin-only fields (package, its dates,
 * free_program, founding_free, verified_at, user_id) are never taken from input.
 */
class FirmProfileUpdater
{
    public const NAME_MAX = 120;

    public const ACTIVITY_MAX = 60;

    public const DESCRIPTION_MAX = 600;

    public const ADDRESS_MAX = 160;

    /** @return array<string, mixed> rules for the web form (every field present) */
    public static function webRules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:'.self::NAME_MAX, new PublicName],
            'activity' => ['nullable', 'string', 'max:'.self::ACTIVITY_MAX],
            'description' => ['nullable', 'string', 'max:'.self::DESCRIPTION_MAX],
            'address' => ['nullable', 'string', 'max:'.self::ADDRESS_MAX],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
            'coordinates' => ['nullable', 'string', 'max:60'],
            'phone' => ['nullable', 'string', 'max:40', new PhoneNumber],
            'whatsapp' => ['nullable', 'boolean'],
            'viber' => ['nullable', 'boolean'],
            'website' => ['nullable', 'string', 'max:255'],
            'facebook' => ['nullable', 'string', 'max:255'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'pib' => ['nullable', 'string', 'regex:/^\d{9}$/'],
            'mb' => ['nullable', 'string', 'regex:/^\d{8}$/'],
            'hours' => ['nullable', 'array'],
            'hours.*' => ['nullable', 'string', 'max:'.WorkingHoursInput::MAX_LENGTH],
            'logo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=100,min_height=100,max_width=8000,max_height=8000'],
            'cover' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:min_width=800,min_height=250,max_width=10000,max_height=10000'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_cover' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'pib.regex' => 'PIB ima 9 cifara.',
            'mb.regex' => 'Matični broj ima 8 cifara.',
            'logo.dimensions' => 'Logo mora biti najmanje 100 × 100 piksela.',
            'logo.max' => 'Logo može imati najviše 5 MB.',
            'cover.dimensions' => 'Naslovna slika mora biti najmanje 800 × 250 piksela.',
            'cover.max' => 'Naslovna slika može imati najviše 8 MB.',
        ];
    }

    /**
     * Fills the firm from validated web input (does not save, images are handled by the controller).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fillFromWeb(Firm $firm, array $data): void
    {
        $errors = [];

        $firm->name = static::text($data['name']);
        $firm->forceFill(['activity' => static::nullableText($data['activity'] ?? null)]);
        $firm->description = static::multiline($data['description'] ?? null);
        $firm->address = static::nullableText($data['address'] ?? null);
        $firm->location_id = $data['location_id'] ?? null;

        $coordinates = static::coordinates($data['coordinates'] ?? null);
        if ($coordinates === false) {
            $errors['coordinates'] = 'Unesite koordinate kao „44.5331, 19.2258” (geografska širina, pa dužina).';
        } else {
            $firm->lat = $coordinates[0] ?? null;
            $firm->lng = $coordinates[1] ?? null;
        }

        $phone = Phone::normalize($data['phone'] ?? null);
        $firm->forceFill([
            'phone' => $phone !== '' ? $phone : null,
            'whatsapp' => (bool) ($data['whatsapp'] ?? false),
            'viber' => (bool) ($data['viber'] ?? false),
        ]);

        foreach (['website' => FirmLinks::website($data['website'] ?? null)] + [
            'facebook' => FirmLinks::social('facebook', $data['facebook'] ?? null),
            'instagram' => FirmLinks::social('instagram', $data['instagram'] ?? null),
        ] as $field => $value) {
            if ($value === null) {
                $errors[$field] = match ($field) {
                    'website' => 'Unesite adresu sajta, npr. vulkanizer.rs.',
                    'facebook' => 'Unesite link Facebook stranice ili @ime.',
                    default => 'Unesite link Instagram profila ili @ime.',
                };
            } else {
                $firm->{$field} = $value !== '' ? $value : null;
            }
        }

        $firm->pib = ($data['pib'] ?? null) ?: null;
        $firm->mb = ($data['mb'] ?? null) ?: null;
        $firm->working_hours = WorkingHoursInput::fromInput((array) ($data['hours'] ?? []));

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @return array<string, mixed> rules for PATCH /me/firm (only sent fields) */
    public static function apiRules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:'.self::NAME_MAX, new PublicName],
            'about' => ['sometimes', 'nullable', 'string', 'max:'.self::DESCRIPTION_MAX],
            'address' => ['sometimes', 'nullable', 'string', 'max:'.self::ADDRESS_MAX],
            'website' => ['sometimes', 'nullable', 'string', 'max:255'],
            'working_hours' => ['sometimes', 'nullable', 'string', 'max:800'],
            'logo_media_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function fillFromApi(Firm $firm, array $data): void
    {
        $errors = [];

        if (array_key_exists('name', $data)) {
            $firm->name = static::text($data['name']);
        }
        if (array_key_exists('about', $data)) {
            $firm->description = static::multiline($data['about']);
        }
        if (array_key_exists('address', $data)) {
            $firm->address = static::nullableText($data['address']);
        }
        if (array_key_exists('website', $data)) {
            $website = FirmLinks::website($data['website']);
            if ($website === null) {
                $errors['website'] = 'Unesite adresu sajta, npr. vulkanizer.rs.';
            } else {
                $firm->website = $website !== '' ? $website : null;
            }
        }
        if (array_key_exists('working_hours', $data)) {
            $hours = WorkingHoursInput::fromText((string) $data['working_hours']);
            if ($hours === false) {
                $errors['working_hours'] = 'Radno vreme upišite po danima, npr. „Pon–Pet: 08–16” i „Sub: 08–13”.';
            } else {
                $firm->working_hours = $hours;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * "44.5331, 19.2258" or "44.5331 19.2258"; [] when empty, false when invalid.
     *
     * @return array{0: float, 1: float}|array{}|false
     */
    public static function coordinates(?string $value): array|false
    {
        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }
        if (! preg_match('/^(-?\d{1,2}(?:\.\d+)?)\s*[,;\s]\s*(-?\d{1,3}(?:\.\d+)?)$/', $value, $m)) {
            return false;
        }
        [$lat, $lng] = [(float) $m[1], (float) $m[2]];
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return false;
        }

        return [round($lat, 7), round($lng, 7)];
    }

    private static function text(mixed $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $value)));
    }

    private static function nullableText(mixed $value): ?string
    {
        $text = static::text($value);

        return $text !== '' ? $text : null;
    }

    /** Keeps line breaks (at most one empty line), removes HTML. */
    private static function multiline(mixed $value): ?string
    {
        $text = str_replace(["\r\n", "\r"], "\n", strip_tags((string) $value));
        $text = (string) preg_replace('/[ \t]+/u', ' ', $text);
        $text = trim((string) preg_replace("/\n{3,}/", "\n\n", $text));

        return $text !== '' ? $text : null;
    }
}
