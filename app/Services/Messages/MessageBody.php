<?php

namespace App\Services\Messages;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Message text: stored as plain text (HTML tags removed), shown escaped with
 * line breaks and nofollow links.
 */
class MessageBody
{
    /** Plain text for storage: no tags, no control characters, tidy whitespace. */
    public static function clean(?string $text): string
    {
        $text = (string) $text;
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        // Drop script/style blocks with their content, then any real tag. "<3" and "a < b" stay.
        $text = (string) preg_replace('#<(script|style)\b[^>]*>.*?</\1\s*>#is', '', $text);
        $text = (string) preg_replace('#<!--.*?-->#s', '', $text);
        $text = (string) preg_replace('#</?[a-z][a-z0-9-]*(\s[^<>]*)?/?>#i', '', $text);

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = (string) preg_replace('/[^\P{C}\n\t\x{200D}]/u', '', $text); // control and invisible format characters
        $text = (string) preg_replace('/[ \t]+$/m', '', $text);
        $text = (string) preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

    /** Escaped HTML with <br> and links (rel="nofollow ugc noopener"). */
    public static function html(string $text): HtmlString
    {
        $pattern = '~\b(?:https?://|www\.)[^\s<>"\']+~iu';
        $out = '';
        $offset = 0;

        if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as [$url, $pos]) {
                // Trailing punctuation belongs to the sentence, not the link.
                $trimmed = rtrim($url, '.,;:!?)]}');
                $out .= e(substr($text, $offset, $pos - $offset));
                $href = Str::startsWith(Str::lower($trimmed), 'www.') ? 'https://'.$trimmed : $trimmed;
                $out .= '<a href="'.e($href).'" rel="nofollow ugc noopener" target="_blank">'.e($trimmed).'</a>';
                $offset = $pos + strlen($trimmed);
            }
        }
        $out .= e(substr($text, $offset));

        return new HtmlString(nl2br($out, false));
    }

    /** One line preview for lists. */
    public static function excerpt(?string $text, int $length = 90): string
    {
        return Str::limit(trim((string) preg_replace('/\s+/u', ' ', (string) $text)), $length, '…');
    }
}
