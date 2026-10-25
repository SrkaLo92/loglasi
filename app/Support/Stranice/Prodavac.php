<?php

namespace App\Support\Stranice;

use App\Support\Phone;
use App\Support\Settings;

/**
 * Seller identity for the legal pages and the withdrawal form, read only from admin settings
 * (payment_recipient, payment_pib, payment_mb, payment_account, contact_*). Nothing is made up:
 * an empty setting is left out.
 */
class Prodavac
{
    /**
     * @return list<array{label: string, value: string}>
     */
    public static function rows(): array
    {
        $recipient = array_values(array_filter(
            array_map('trim', preg_split('/\R/', trim((string) Settings::get('payment_recipient', ''))) ?: []),
            'strlen',
        ));
        $name = $recipient[0] ?? '';
        $address = count($recipient) > 1
            ? implode(', ', array_slice($recipient, 1))
            : trim((string) Settings::get('contact_address'));
        $phone = trim((string) Settings::get('contact_phone'));

        $rows = [
            'Naziv' => $name,
            'Adresa' => $address,
            'PIB' => trim((string) Settings::get('payment_pib', '')),
            'Matični broj' => trim((string) Settings::get('payment_mb', '')),
            'Tekući račun' => trim((string) Settings::get('payment_account', '')),
            'E-pošta' => trim((string) Settings::get('contact_email')),
            'Telefon' => $phone !== '' ? Phone::display($phone) : '',
        ];

        $out = [];
        foreach ($rows as $label => $value) {
            if ($value !== '') {
                $out[] = ['label' => $label, 'value' => $value];
            }
        }

        return $out;
    }

    /** Markdown list for the {prodavac} placeholder in page texts. */
    public static function markdown(): string
    {
        $rows = self::rows();
        if ($rows === []) {
            return 'Podatke o prodavcu šaljemo na zahtev preko [kontakt forme](/kontakt/).';
        }

        return implode("\n", array_map(
            fn (array $row) => '- **'.$row['label'].':** '.self::escape($row['value']),
            $rows,
        ));
    }

    /** One line for the {prodavac_kratko} placeholder: "Name, address" (or the site name until it is entered). */
    public static function short(): string
    {
        $rows = collect(self::rows())->keyBy('label');
        $parts = array_filter([$rows['Naziv']['value'] ?? '', $rows['Adresa']['value'] ?? '']);

        return $parts === [] ? 'loglasi.rs' : self::escape(implode(', ', $parts));
    }

    /** Admin-entered values must not turn into Markdown formatting or links. */
    public static function escape(string $text): string
    {
        return addcslashes($text, '\\*_[]`#<>');
    }
}
