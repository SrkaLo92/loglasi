<?php

namespace App\Console\Commands\Info;

use App\Models\InfoBusiness;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Adds businesses to the info directory from a CSV with a header row. Recognised columns (any order):
 * Naziv/name, Kategorija/category, Adresa/address, Mesto/place, Opština/municipality, Telefon/phone, Email, Web/website, Izvor/source.
 * A business with the same name and address is skipped, so the import can be run again.
 */
class ImportBusinesses extends Command
{
    protected $signature = 'info:import-businesses {file : CSV file} {--dry-run : only count what would be added}';

    protected $description = 'Info portal: add businesses to the directory from a CSV file';

    private const COLUMNS = [
        'name' => ['naziv', 'name', 'firma'],
        'category' => ['kategorija', 'category', 'delatnost'],
        'address' => ['adresa', 'address', 'adresa / mesto'],
        'place' => ['mesto', 'place', 'grad'],
        'municipality' => ['opština', 'opstina', 'municipality'],
        'phone' => ['telefon', 'phone', 'telefon (javni, poslovni)'],
        'email' => ['email', 'e-mail', 'mejl'],
        'website' => ['web', 'website', 'sajt', 'web / profil'],
        'source' => ['izvor', 'source'],
    ];

    public function handle(): int
    {
        $path = (string) $this->argument('file');
        $handle = is_readable($path) ? fopen($path, 'r') : false;
        if ($handle === false) {
            $this->error('File not readable: '.$path);

            return self::FAILURE;
        }

        $header = fgetcsv($handle, escape: '\\') ?: [];
        $index = [];
        foreach ($header as $i => $title) {
            $title = mb_strtolower(trim((string) preg_replace('/^\x{FEFF}/u', '', (string) $title)));
            foreach (self::COLUMNS as $field => $names) {
                if (in_array($title, $names, true) && ! isset($index[$field])) {
                    $index[$field] = $i;
                }
            }
        }
        if (! isset($index['name'])) {
            $this->error('No name column (Naziv) in the header.');

            return self::FAILURE;
        }

        $added = $skipped = 0;
        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            $data = [];
            foreach ($index as $field => $i) {
                $value = trim((string) ($row[$i] ?? ''));
                $data[$field] = $value === '' ? null : $value;
            }
            if (! $data['name']) {
                continue;
            }
            if (isset($data['website']) && ! preg_match('#^https?://#i', $data['website'])) {
                $data['website'] = null;
            }
            if (isset($data['email']) && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $data['email'] = null;
            }
            $data['place'] ??= $this->placeFrom($data['address'] ?? null);
            $data['municipality'] ??= $data['place'];
            if ($data['municipality']) {
                $data['city'] = self::cityFor($data['municipality']);
            }

            $exists = InfoBusiness::query()->where('name', $data['name'])
                ->where(fn ($q) => isset($data['address']) ? $q->where('address', $data['address']) : $q->whereNull('address'))
                ->exists();
            if ($exists) {
                $skipped++;

                continue;
            }
            if (! $this->option('dry-run')) {
                InfoBusiness::query()->create($data + ['status' => 'listed']);
            }
            $added++;
        }
        fclose($handle);

        $this->info(sprintf('%s %d, skipped (already listed) %d.', $this->option('dry-run') ? 'Would add' : 'Added', $added, $skipped));

        return self::SUCCESS;
    }

    /** "Joševa bb, Loznica" -> "Loznica" */
    private function placeFrom(?string $address): ?string
    {
        if (! $address || ! str_contains($address, ',')) {
            return null;
        }
        $last = trim((string) preg_replace('/\(.*\)/', '', (string) last(explode(',', $address))));

        return $last !== '' && mb_strlen($last) <= 40 ? $last : null;
    }

    /** City key for a later city switch: the configured city that covers the municipality, else its slug. */
    public static function cityFor(string $municipality): string
    {
        foreach ((array) config('info.cities') as $key => $city) {
            foreach ((array) ($city['municipalities'] ?? []) as $name) {
                if (Str::lower($name) === Str::lower($municipality)) {
                    return $key;
                }
            }
        }

        return Str::slug($municipality);
    }
}
