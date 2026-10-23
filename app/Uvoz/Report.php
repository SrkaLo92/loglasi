<?php

namespace App\Uvoz;

use Illuminate\Support\Facades\File;

/** Brojači po vrsti podatka i detaljan izveštaj uvoza. */
class Report
{
    public const LABELS = [
        'settings' => 'Podešavanja',
        'users' => 'Korisnici',
        'firms' => 'Firme',
        'categories' => 'Kategorije',
        'locations' => 'Mesta',
        'fields' => 'Polja',
        'listings' => 'Oglasi',
        'field_values' => 'Vrednosti polja',
        'images' => 'Slike',
        'favorites' => 'Omiljeni',
        'conversations' => 'Razgovori',
        'messages' => 'Poruke',
        'gone' => 'Uklonjeni URL-ovi (410)',
        'orders' => 'Narudžbine',
    ];

    /** @var array<string, array{wp:int, imported:int, skipped:int, errors:int}> */
    private array $counts = [];

    /** @var array<string, string[]> */
    private array $lines = [];

    public function add(string $entity, string $column, int $n = 1): void
    {
        $this->counts[$entity] ??= ['wp' => 0, 'imported' => 0, 'skipped' => 0, 'errors' => 0];
        $this->counts[$entity][$column] += $n;
    }

    public function wp(string $entity, int $n = 1): void
    {
        $this->add($entity, 'wp', $n);
    }

    public function imported(string $entity, int $n = 1): void
    {
        $this->add($entity, 'imported', $n);
    }

    public function skipped(string $entity, string $why = ''): void
    {
        $this->add($entity, 'skipped');
        if ($why !== '') {
            $this->note($entity, 'preskočeno: '.$why);
        }
    }

    public function error(string $entity, string $why): void
    {
        $this->add($entity, 'errors');
        $this->note($entity, 'GREŠKA: '.$why);
    }

    public function note(string $entity, string $line): void
    {
        $this->counts[$entity] ??= ['wp' => 0, 'imported' => 0, 'skipped' => 0, 'errors' => 0];
        $this->lines[$entity][] = $line;
    }

    /** @return array{wp:int, imported:int, skipped:int, errors:int} */
    public function counts(string $entity): array
    {
        return $this->counts[$entity] ?? ['wp' => 0, 'imported' => 0, 'skipped' => 0, 'errors' => 0];
    }

    public function totalErrors(): int
    {
        return array_sum(array_column($this->counts, 'errors'));
    }

    /** @return array<int, array<int, string|int>> */
    public function rows(): array
    {
        $rows = [];
        foreach (self::LABELS as $entity => $label) {
            if (! isset($this->counts[$entity])) {
                continue;
            }
            $c = $this->counts[$entity];
            $rows[] = [$label, $c['wp'], $c['imported'], $c['skipped'], $c['errors']];
        }

        return $rows;
    }

    /** @param  array<string, string>  $header */
    public function write(string $path, array $header): void
    {
        $out = ['Uvoz iz WordPress-a, loglasi.rs', str_repeat('=', 40)];
        foreach ($header as $key => $value) {
            $out[] = $key.': '.$value;
        }
        $out[] = '';
        $out[] = mb_str_pad('Šta', 26).' '.implode(' ', array_map(fn ($h) => mb_str_pad($h, 10, ' ', STR_PAD_LEFT), ['u WP', 'uvezeno', 'preskočeno', 'greške']));
        foreach ($this->rows() as [$label, $wp, $imp, $skip, $err]) {
            $out[] = mb_str_pad((string) $label, 26).' '.implode(' ', array_map(fn ($n) => mb_str_pad((string) $n, 10, ' ', STR_PAD_LEFT), [$wp, $imp, $skip, $err]));
        }

        foreach (self::LABELS as $entity => $label) {
            if (empty($this->lines[$entity])) {
                continue;
            }
            $out[] = '';
            $out[] = $label;
            $out[] = str_repeat('-', mb_strlen($label));
            foreach ($this->lines[$entity] as $line) {
                $out[] = '  '.$line;
            }
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, implode("\n", $out)."\n");
    }
}
