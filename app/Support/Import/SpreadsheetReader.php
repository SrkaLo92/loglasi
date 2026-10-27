<?php

namespace App\Support\Import;

use SimpleXMLElement;
use ZipArchive;

/**
 * Reads a table from CSV, TSV or XLSX into rows of strings, without an extra package.
 * The first row is the header. XLSX reads the first sheet, shared strings included.
 */
class SpreadsheetReader
{
    /** Rows read at most, so one upload cannot exhaust the memory. */
    public const MAX_ROWS = 5000;

    private const MAX_UNPACKED_BYTES = 40 * 1024 * 1024;

    /**
     * @return array{header: list<string>, rows: list<list<string>>}
     */
    public static function read(string $path, ?string $name = null): array
    {
        $extension = strtolower(pathinfo($name ?? $path, PATHINFO_EXTENSION));
        $rows = match ($extension) {
            'xlsx', 'xlsm' => self::xlsx($path),
            'xls' => throw new ImportFailed('Stari .xls format ne možemo da pročitamo. Sačuvajte tabelu kao .xlsx ili .csv.'),
            default => self::separated($path),
        };

        $rows = array_values(array_filter($rows, fn (array $row) => implode('', array_map('trim', $row)) !== ''));
        if ($rows === []) {
            throw new ImportFailed('Tabela je prazna.');
        }

        $header = array_map(fn ($value) => trim((string) $value), array_shift($rows));
        $width = count($header);
        $rows = array_map(fn (array $row) => array_slice(array_pad($row, $width, ''), 0, $width), $rows);

        return ['header' => $header, 'rows' => array_slice($rows, 0, self::MAX_ROWS)];
    }

    /** @return list<list<string>> */
    private static function separated(string $path): array
    {
        $text = (string) file_get_contents($path);
        $text = preg_replace('/^\x{FEFF}/u', '', $text) ?? $text;
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = (string) mb_convert_encoding($text, 'UTF-8', ['Windows-1250', 'ISO-8859-2', 'Windows-1251']);
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $text);
        rewind($handle);

        $separator = self::separator($text);
        $rows = [];
        while (($row = fgetcsv($handle, 0, $separator, '"', '')) !== false) {
            $rows[] = array_map(fn ($value) => trim((string) $value), $row);
        }
        fclose($handle);

        return $rows;
    }

    /** The separator of the first line: semicolon (Excel in Serbian), comma or tab. */
    private static function separator(string $text): string
    {
        $line = strtok($text, "\n") ?: '';
        $counts = [';' => substr_count($line, ';'), ',' => substr_count($line, ','), "\t" => substr_count($line, "\t")];
        arsort($counts);

        return (string) array_key_first($counts);
    }

    /** @return list<list<string>> */
    private static function xlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new ImportFailed('Excel fajl ne može da se otvori. Sačuvajte ga ponovo kao .xlsx ili .csv.');
        }

        $strings = [];
        if (($sharedXml = self::entry($zip, 'xl/sharedStrings.xml')) !== false) {
            foreach (self::xml($sharedXml)->si as $item) {
                $strings[] = trim(implode('', self::texts($item)));
            }
        }

        $sheet = self::entry($zip, self::firstSheetPath($zip));
        $zip->close();
        if ($sheet === false) {
            throw new ImportFailed('U Excel fajlu nema nijednog lista sa podacima.');
        }

        $rows = [];
        foreach (self::xml($sheet)->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $cell) {
                $index = self::columnIndex((string) $cell['r']);
                $value = match ((string) $cell['t']) {
                    's' => $strings[(int) $cell->v] ?? '',
                    'inlineStr' => trim(implode('', self::texts($cell->is))),
                    default => trim((string) $cell->v),
                };
                $cells[$index >= 0 ? $index : count($cells)] = $value;
            }
            if ($cells === []) {
                $rows[] = [];

                continue;
            }
            $rows[] = array_map(fn ($value) => (string) $value, array_replace(array_fill(0, max(array_keys($cells)) + 1, ''), $cells));
        }

        return $rows;
    }

    /** A file inside the .xlsx, refused when it unpacks too large (a "zip bomb" would exhaust memory). */
    private static function entry(ZipArchive $zip, string $name): string|false
    {
        $stat = $zip->statName($name);
        if ($stat === false) {
            return false;
        }
        if ($stat['size'] > self::MAX_UNPACKED_BYTES) {
            throw new ImportFailed('Excel fajl je prevelik. Podelite ga na manje tabele ili ga sačuvajte kao .csv.');
        }

        return $zip->getFromName($name);
    }

    private static function firstSheetPath(ZipArchive $zip): string
    {
        $workbook = self::entry($zip, 'xl/workbook.xml');
        $relations = self::entry($zip, 'xl/_rels/workbook.xml.rels');
        if ($workbook !== false && $relations !== false) {
            $id = (string) (self::xml($workbook)->sheets->sheet[0]->attributes('r', true)['id'] ?? '');
            foreach (self::xml($relations)->Relationship as $relation) {
                if ((string) $relation['Id'] === $id && $id !== '') {
                    return 'xl/'.ltrim((string) $relation['Target'], '/');
                }
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /** "C7" → 2 (0-based column), "" → -1. */
    private static function columnIndex(string $reference): int
    {
        if (! preg_match('/^([A-Z]+)/', strtoupper($reference), $m)) {
            return -1;
        }
        $index = 0;
        foreach (str_split($m[1]) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }

    /** @return list<string> every <t> inside a shared or inline string (with its runs) */
    private static function texts(?SimpleXMLElement $node): array
    {
        if ($node === null) {
            return [];
        }
        $out = [];
        foreach ($node->xpath('.//*[local-name()="t"]') ?: [] as $text) {
            $out[] = (string) $text;
        }

        return $out;
    }

    private static function xml(string $content): SimpleXMLElement
    {
        $xml = @simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
        if (! $xml instanceof SimpleXMLElement) {
            throw new ImportFailed('Excel fajl je oštećen.');
        }

        return $xml;
    }
}
