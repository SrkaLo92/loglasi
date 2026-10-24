<?php

namespace App\Support\Payments;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * NBS IPS QR code ("IPS skeniraj") for a bank transfer order (K:PR).
 *
 * Tags in the required order, separated by "|", no trailing separator:
 * K (PR), V (01), C (1 = UTF-8), R (18-digit account), N (recipient, max 70 chars, up to
 * 3 lines joined with CR LF), I ("RSD" + amount with a decimal comma), SF (payment code),
 * S (purpose, max 35), RO (model + reference, max 25).
 */
final class IpsQr
{
    public const MAX_RECIPIENT = 70;

    public const MAX_PURPOSE = 35;

    public const MAX_REFERENCE = 25;

    public static function payload(string $account, string $recipient, int $amount, string $purpose, string $model, string $reference): string
    {
        $tags = [
            'K' => 'PR',
            'V' => '01',
            'C' => '1',
            'R' => $account,
            'N' => self::recipient($recipient),
            'I' => 'RSD'.number_format($amount, 2, ',', ''),
            'SF' => (string) config('payments.ips_payment_code', '289'),
        ];

        $purpose = self::clean($purpose, self::MAX_PURPOSE);
        if ($purpose !== '') {
            $tags['S'] = $purpose;
        }

        $ro = preg_replace('/\D/', '', $model.$reference);
        if ($ro !== '' && strlen($ro) <= self::MAX_REFERENCE) {
            $tags['RO'] = $ro;
        }

        $parts = [];
        foreach ($tags as $tag => $value) {
            $parts[] = $tag.':'.$value;
        }

        return implode('|', $parts);
    }

    /** SVG markup (no XML header) to inline in a page. */
    public static function svg(string $payload): string
    {
        return (new QRCode(self::options(QROutputInterface::MARKUP_SVG, [
            'svgAddXmlHeader' => false,
            'drawLightModules' => false,
            'connectPaths' => true,
            'cssClass' => 'lgpay-qr__svg',
        ])))->render($payload);
    }

    /** PNG bytes, for e-mail clients that do not show SVG. */
    public static function png(string $payload): string
    {
        return (new QRCode(self::options(QROutputInterface::GDIMAGE_PNG, ['scale' => 6])))->render($payload);
    }

    /** Up to three lines, each trimmed, joined with CR LF, at most 70 characters in total. */
    private static function recipient(string $value): string
    {
        $lines = preg_split('/\R/u', $value) ?: [];
        $lines = array_values(array_filter(array_map(fn ($line) => self::clean($line, self::MAX_RECIPIENT), $lines), fn ($line) => $line !== ''));
        $text = implode("\r\n", array_slice($lines, 0, 3));

        return rtrim(mb_substr($text, 0, self::MAX_RECIPIENT));
    }

    /** One line without the "|" separator, collapsed spaces, cut to $max characters. */
    private static function clean(string $value, int $max): string
    {
        $value = str_replace('|', ' ', $value);
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        return rtrim(mb_substr($value, 0, $max));
    }

    /** @param  array<string, mixed>  $extra */
    private static function options(string $type, array $extra = []): QROptions
    {
        return new QROptions([
            'outputType' => $type,
            'outputBase64' => false,
            'eccLevel' => EccLevel::M,
            'addQuietzone' => true,
            'quietzoneSize' => 4,
            ...$extra,
        ]);
    }
}
