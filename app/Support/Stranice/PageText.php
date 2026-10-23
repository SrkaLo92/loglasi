<?php

namespace App\Support\Stranice;

use App\Support\Settings;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Tekstovi sadržajnih strana: vrednost iz podešavanja (page_text.<ključ>) ili
 * podrazumevani tekst iz resources/stranice/tekstovi.php. Zamene {…} se popunjavaju iz baze.
 */
class PageText
{
    public const PREFIX = 'page_text.';

    /** @var array<string, mixed>|null */
    private static ?array $defaults = null;

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return self::$defaults ??= require resource_path('stranice/tekstovi.php');
    }

    /** Sirova vrednost (string ili niz), bez zamena. */
    public static function raw(string $key): mixed
    {
        $value = Settings::get(self::PREFIX.$key, false);

        if ($value === false || $value === null || $value === '' || $value === []) {
            return self::defaults()[$key] ?? '';
        }

        return $value;
    }

    /** Kratak tekst sa zamenama, za {{ }} u Blade-u (escapuje Blade). */
    public static function get(string $key): string
    {
        $value = self::raw($key);

        return is_string($value) ? self::replace($value) : '';
    }

    /** Markdown → HTML (bez sirovog HTML-a, bez nebezbednih linkova). */
    public static function html(string $key): HtmlString
    {
        $value = self::raw($key);

        return self::markdown(is_string($value) ? $value : '');
    }

    /** Jedan red Markdown-a bez <p> omotača (za odgovore i korake sa linkovima). */
    public static function inline(string $text): HtmlString
    {
        return new HtmlString(trim(Str::inlineMarkdown(self::replace($text), self::options())));
    }

    public static function markdown(string $text): HtmlString
    {
        return new HtmlString(Str::markdown(self::replace($text), self::options()));
    }

    /** @return array<int, mixed> */
    public static function list(string $key): array
    {
        $value = self::raw($key);

        return is_array($value) ? array_values($value) : [];
    }

    public static function replace(string $text): string
    {
        if (! str_contains($text, '{')) {
            return $text;
        }

        return strtr($text, self::vars());
    }

    /** @return array<string, string> */
    public static function vars(): array
    {
        return once(fn () => self::buildVars());
    }

    /** @return array<string, string> */
    private static function buildVars(): array
    {
        $email = (string) Settings::get('contact_email');
        $emailMd = $email !== '' ? '['.$email.'](mailto:'.$email.')' : 'našu adresu e-pošte';

        $vars = [
            '{besplatni_oglasi}' => Cenovnik::freeListingsText(),
            '{trajanje_oglasa}' => Cenovnik::durationText(),
            '{besplatne_firme}' => (string) Cenovnik::foundingTotal(),
            '{ponuda_firme}' => Cenovnik::offerText(),
            '{email}' => $emailMd,
        ];

        foreach (Cenovnik::PLACEHOLDERS as $placeholder => $code) {
            $product = Cenovnik::product($code);
            $vars['{'.$placeholder.'}'] = $product ? Cenovnik::price($product->price_rsd) : '';
        }

        return $vars;
    }

    /** @return array<string, mixed> */
    private static function options(): array
    {
        return ['html_input' => 'escape', 'allow_unsafe_links' => false];
    }
}
