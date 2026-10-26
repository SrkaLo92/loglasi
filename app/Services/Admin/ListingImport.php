<?php

namespace App\Services\Admin;

use App\Enums\Currency;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use App\Services\Catalog;
use App\Services\ListingImages;
use App\Services\Posting;
use App\Services\PostingRefused;
use App\Support\Import\SpreadsheetReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Import of a firm's listings from a CSV or Excel table: one listing per row.
 * Columns are recognized by their Serbian or English name, the category fields by key or label.
 * Every row goes through the same checks as the posting form, so nothing can enter the
 * site that the form would refuse; rows with an error are skipped and reported.
 */
class ListingImport
{
    /** Images downloaded per row at most (the form's limit still applies). */
    public const MAX_IMAGES = 10;

    public const MAX_IMAGE_BYTES = 12 * 1024 * 1024;

    /** Fixed columns: canonical key => accepted header names (lowercase, no diacritics). */
    private const COLUMNS = [
        'title' => ['naslov', 'title', 'naziv', 'ime oglasa'],
        'category' => ['kategorija', 'category', 'kategorije'],
        'price' => ['cena', 'price', 'cijena'],
        'currency' => ['valuta', 'currency'],
        'description' => ['opis', 'description', 'tekst'],
        'location' => ['mesto', 'lokacija', 'location', 'grad'],
        'images' => ['slike', 'images', 'fotografije', 'slika', 'url slika', 'image urls'],
    ];

    public function __construct(
        private readonly Catalog $catalog,
        private readonly Posting $posting,
        private readonly ListingImages $images,
    ) {}

    /** The columns a table may hold, for the help text on the import page. */
    public function columnHelp(): array
    {
        $fields = $this->catalog->categories()
            ->flatMap(fn (Category $c) => $this->catalog->fieldsFor($c))
            ->unique('id')
            ->sortBy('label')
            ->pluck('label', 'key')
            ->all();

        return [
            'required' => ['naslov', 'kategorija', 'opis'],
            'optional' => ['cena', 'valuta', 'mesto', 'slike'],
            'fields' => $fields,
        ];
    }

    /**
     * Reads the table and checks every row without saving anything.
     *
     * @return array{rows: list<array{line: int, data: array<string, mixed>, title: string, category: ?string, errors: list<string>}>, unknown: list<string>}
     */
    public function preview(string $path, ?string $name, User $owner): array
    {
        ['header' => $header, 'rows' => $rows] = SpreadsheetReader::read($path, $name);
        [$map, $unknown] = $this->mapHeader($header);

        $out = [];
        foreach ($rows as $index => $row) {
            $data = [];
            foreach ($map as $column => $key) {
                $data[$key] = $row[$column] ?? '';
            }
            $out[] = $this->check($data, $owner) + ['line' => $index + 2];
        }

        return ['rows' => $out, 'unknown' => $unknown];
    }

    /**
     * Imports the rows that have no error. Returns a report.
     *
     * @return array{created: int, failed: int, rows: list<array{line: int, title: string, errors: list<string>, listing_id?: int}>}
     */
    public function import(string $path, ?string $name, User $owner, bool $withImages = true): array
    {
        $preview = $this->preview($path, $name, $owner);
        $created = 0;
        $failed = 0;
        $rows = [];

        foreach ($preview['rows'] as $row) {
            if ($row['errors'] !== []) {
                $failed++;
                $rows[] = ['line' => $row['line'], 'title' => $row['title'], 'errors' => $row['errors']];

                continue;
            }

            try {
                $listing = $this->create($row['data'], $owner, $withImages);
                $created++;
                $rows[] = ['line' => $row['line'], 'title' => $listing->title, 'errors' => [], 'listing_id' => $listing->id];
            } catch (PostingRefused $e) {
                $failed++;
                $rows[] = ['line' => $row['line'], 'title' => $row['title'], 'errors' => [$e->getMessage()]];
            } catch (ValidationException $e) {
                $failed++;
                $rows[] = ['line' => $row['line'], 'title' => $row['title'], 'errors' => array_values(array_map('strval', $e->validator->errors()->all()))];
            } catch (Throwable $e) {
                $failed++;
                $rows[] = ['line' => $row['line'], 'title' => $row['title'], 'errors' => ['Greška pri upisu: '.$e->getMessage()]];
            }
        }

        return ['created' => $created, 'failed' => $failed, 'rows' => $rows];
    }

    /**
     * Header to column keys. Unknown headers are reported so a typo does not pass silently.
     *
     * @param  list<string>  $header
     * @return array{0: array<int, string>, 1: list<string>}
     */
    private function mapHeader(array $header): array
    {
        $fieldKeys = $this->fieldKeys();
        $map = [];
        $unknown = [];

        foreach ($header as $index => $name) {
            $plain = self::plain($name);
            if ($plain === '') {
                continue;
            }
            $key = null;
            foreach (self::COLUMNS as $column => $names) {
                if (in_array($plain, $names, true)) {
                    $key = $column;
                    break;
                }
            }
            $key ??= $fieldKeys[$plain] ?? null;

            if ($key === null) {
                $unknown[] = $name;

                continue;
            }
            $map[$index] = $key;
        }

        return [$map, $unknown];
    }

    /** Category-field columns: plain label or key => "field:<key>". */
    private function fieldKeys(): array
    {
        $keys = [];
        foreach ($this->catalog->categories() as $category) {
            foreach ($this->catalog->fieldsFor($category) as $field) {
                $keys[self::plain($field->key)] = 'field:'.$field->key;
                $keys[self::plain((string) $field->label)] = 'field:'.$field->key;
            }
        }

        return $keys;
    }

    /**
     * One row: resolves the category, place and price, and reports everything that is wrong.
     *
     * @param  array<string, string>  $data
     * @return array{data: array<string, mixed>, title: string, category: ?string, errors: list<string>}
     */
    private function check(array $data, User $owner): array
    {
        $errors = [];
        $title = Posting::cleanLine((string) ($data['title'] ?? ''));
        $description = Posting::cleanText((string) ($data['description'] ?? ''));

        if (mb_strlen($title) < Posting::TITLE_MIN) {
            $errors[] = 'Naslov je prazan ili prekratak (najmanje '.Posting::TITLE_MIN.' znaka).';
        } elseif (mb_strlen($title) > Posting::TITLE_MAX) {
            $errors[] = 'Naslov je duži od '.Posting::TITLE_MAX.' znakova.';
        }
        if (mb_strlen($description) < Posting::DESCRIPTION_MIN) {
            $errors[] = 'Opis je prazan ili prekratak (najmanje '.Posting::DESCRIPTION_MIN.' znakova).';
        }

        $category = $this->category((string) ($data['category'] ?? ''));
        if ($category === null) {
            $errors[] = ($data['category'] ?? '') === ''
                ? 'Kategorija nije upisana.'
                : 'Kategorija „'.$data['category'].'” ne postoji ili u njoj ne može da se oglašava (izaberite poslednji nivo).';
        }

        $price = Posting::parsePrice($data['price'] ?? null);
        if ($price === false) {
            $errors[] = 'Cena „'.$data['price'].'” nije broj. Ostavite prazno za „Dogovor”.';
            $price = null;
        }

        $currency = $this->currency((string) ($data['currency'] ?? ''));
        $location = $this->location((string) ($data['location'] ?? ''), $owner);
        if ($location === null) {
            $errors[] = ($data['location'] ?? '') === ''
                ? 'Mesto nije upisano.'
                : 'Mesto „'.$data['location'].'” nije na spisku.';
        }

        $fields = [];
        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'field:') && trim((string) $value) !== '') {
                $fields[substr($key, 6)] = trim((string) $value);
            }
        }

        if ($category !== null) {
            try {
                $this->posting->validateFieldsFor($category, $fields);
            } catch (ValidationException $e) {
                foreach ($e->validator->errors()->all() as $message) {
                    $errors[] = (string) $message;
                }
            }
        }

        $images = $this->imageUrls((string) ($data['images'] ?? ''));
        if (count($images) > self::MAX_IMAGES) {
            $images = array_slice($images, 0, self::MAX_IMAGES);
        }

        return [
            'title' => $title,
            'category' => $category?->name,
            'errors' => $errors,
            'data' => [
                'title' => $title,
                'description' => $description,
                'category_id' => $category?->id,
                'location_id' => $location?->id,
                'price' => $price,
                'currency' => $currency,
                'fields' => $fields,
                'images' => $images,
            ],
        ];
    }

    /** @param array<string, mixed> $data */
    private function create(array $data, User $owner, bool $withImages): Listing
    {
        $category = $this->catalog->category((int) $data['category_id']);
        $listing = DB::transaction(function () use ($data, $owner, $category) {
            $listing = $this->posting->createDraft($owner, $category, limitDrafts: false);
            $this->posting->fillBasics($listing, [
                'title' => $data['title'],
                'description' => $data['description'],
                'location_id' => $data['location_id'],
                'currency' => $data['currency']->value,
            ], $data['price']);
            $listing->save();
            $this->posting->saveFields($listing, $data['fields'], $owner);

            return $listing;
        });

        if ($withImages) {
            $this->addImages($listing, $owner, $data['images']);
        }

        return $this->posting->submit($listing->refresh(), $owner);
    }

    /** @param list<string> $urls */
    private function addImages(Listing $listing, User $owner, array $urls): void
    {
        foreach ($urls as $url) {
            $file = tempnam(sys_get_temp_dir(), 'uvoz');
            try {
                $response = Http::timeout(20)->withOptions(['allow_redirects' => true, 'sink' => $file])->get($url);
                if (! $response->successful() || filesize($file) <= 0 || filesize($file) > self::MAX_IMAGE_BYTES) {
                    continue;
                }
                $this->images->upload(
                    new UploadedFile($file, Str::limit(basename(parse_url($url, PHP_URL_PATH) ?: 'slika.jpg'), 60, ''), null, null, true),
                    $owner,
                    $listing,
                );
            } catch (Throwable) {
                // A photo that cannot be fetched does not stop the listing.
            } finally {
                @unlink($file);
            }
        }
    }

    private function category(string $raw): ?Category
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $category = $this->catalog->categoryByPath(Str::slug(str_replace(['>', '|'], '/', $raw), '-', 'rs'))
            ?? $this->catalog->categoryBySlug(Str::slug($raw, '-', 'rs'))
            ?? $this->catalog->categories()->first(fn (Category $c) => self::plain((string) $c->name) === self::plain($raw));

        return $category !== null && $this->posting->isPostableCategory($category) ? $category : null;
    }

    private function location(string $raw, User $owner)
    {
        $raw = trim($raw);
        if ($raw === '') {
            return $owner->location_id ? $this->catalog->location((int) $owner->location_id) : null;
        }

        return $this->catalog->locationBySlug(Str::slug($raw, '-', 'rs'))
            ?? $this->catalog->locations()->first(fn ($l) => self::plain((string) $l->name) === self::plain($raw));
    }

    private function currency(string $raw): Currency
    {
        return match (self::plain($raw)) {
            'rsd', 'din', 'dinar', 'dinara' => Currency::RSD,
            default => Currency::EUR,
        };
    }

    /** @return list<string> */
    private function imageUrls(string $raw): array
    {
        $parts = preg_split('/[\s,;|]+/u', trim($raw)) ?: [];

        return array_values(array_filter($parts, fn ($url) => preg_match('#^https?://#i', $url) === 1));
    }

    /** Lowercase, without diacritics and extra spaces: "Godište " → "godiste". */
    private static function plain(string $value): string
    {
        $value = str_replace(['č', 'ć', 'ž', 'š', 'đ', 'Č', 'Ć', 'Ž', 'Š', 'Đ'], ['c', 'c', 'z', 's', 'dj', 'c', 'c', 'z', 's', 'dj'], $value);

        return trim((string) preg_replace('/\s+/u', ' ', mb_strtolower($value)));
    }
}
