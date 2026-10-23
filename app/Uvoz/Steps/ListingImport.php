<?php

namespace App\Uvoz\Steps;

use App\Enums\Currency;
use App\Enums\FieldType;
use App\Enums\ListingStatus;
use App\Support\Price;
use App\Uvoz\Importer;
use App\Uvoz\Phone;
use App\Uvoz\Text;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Oglasi (listivo_listing) sa istim ID-jem i slugom, cena i valuta, polja i slike.
 */
class ListingImport extends Step
{
    /** Statusi koji se ne uvoze uopšte. */
    private const IGNORED = ['auto-draft', 'inherit'];

    private array $categoryParents = [];

    private array $locationParents = [];

    /** @var array<string, object> wp_key → polje */
    private array $fields = [];

    /** @var string[] */
    private array $fieldTaxonomies = [];

    /** @var array<int, string> ID termina valute → EUR | RSD */
    private array $currencyCache = [];

    private bool $warnedNoUploads = false;

    public function run(): void
    {
        $taxCat = config('uvoz.tax_category');
        $taxLoc = config('uvoz.tax_location');

        $this->categoryParents = array_map(fn ($t) => $t->parent, $this->wp->terms($taxCat));
        $this->locationParents = array_map(fn ($t) => $t->parent, $this->wp->terms($taxLoc));

        $this->fields = DB::table('fields')->whereNotNull('wp_key')->get()->keyBy('wp_key')->all();
        $taxonomies = $this->wp->table('term_taxonomy')->distinct()->pluck('taxonomy')->all();
        $this->fieldTaxonomies = array_values(array_intersect(array_keys($this->fields), $taxonomies));

        $query = $this->wp->table('posts')
            ->where('post_type', config('uvoz.post_type'))
            ->whereNotIn('post_status', self::IGNORED)
            ->orderBy('ID');
        if ($since = $this->ctx->sinceUtc()) {
            $query->where(fn ($q) => $q->where('post_modified_gmt', '>=', $since)->orWhere('post_date_gmt', '>=', $since));
        }

        $query->chunk(200, function ($posts) use ($taxCat, $taxLoc) {
            $ids = $posts->pluck('ID')->map(fn ($id) => (int) $id)->all();
            $meta = $this->wp->postMeta($ids);
            $terms = $this->wp->objectTerms($ids, array_merge([$taxCat, $taxLoc], $this->fieldTaxonomies));

            $attachmentIds = [];
            foreach ($ids as $id) {
                $attachmentIds = array_merge($attachmentIds, $this->imageIds($meta[$id] ?? []));
            }
            $attachments = $this->ctx->attachments($attachmentIds);

            $users = $this->ctx->map('users');
            $categories = $this->ctx->map('categories');
            $locations = $this->ctx->map('locations');

            foreach ($posts as $post) {
                $id = (int) $post->ID;
                DB::transaction(fn () => $this->import($post, $meta[$id] ?? [], $terms[$id] ?? [], $attachments, $users, $categories, $locations));
            }
        });

        if ($this->ctx->since !== null) {
            $this->reportMissing();
        }

        $this->ctx->forget('listings');
    }

    private function import(object $post, array $meta, array $terms, array $attachments, array $users, array $categories, array $locations): void
    {
        $wpId = (int) $post->ID;
        $this->report->wp('listings');
        $label = "#{$wpId} {$post->post_name}";
        $author = (int) $post->post_author;

        if ($this->ctx->isSpam($author)) {
            $this->report->skipped('listings', "{$label}: oglas spam naloga #{$author}");

            return;
        }

        $existing = DB::table('listings')->where('id', $wpId)->first();
        if ($existing && (int) $existing->wp_id !== $wpId) {
            $this->report->error('listings', "{$label}: ID {$wpId} već zauzima oglas koji nije iz WordPress-a");

            return;
        }

        if ($post->post_status === 'trash') {
            if ($existing && $existing->deleted_at === null) {
                DB::table('listings')->where('id', $wpId)->update(['deleted_at' => now()]);
                $this->report->note('listings', "{$label}: obrisan u WordPress-u, obrisan i ovde");
            }
            $this->report->skipped('listings', "{$label}: u otpadu (URL ide u 410)");

            return;
        }

        $userId = $users[$author] ?? null;
        if ($userId === null) {
            $this->report->error('listings', "{$label}: autor #{$author} nije uvezen");

            return;
        }

        $categoryTerm = $this->deepest($terms[config('uvoz.tax_category')] ?? [], $this->categoryParents);
        $categoryId = $categoryTerm !== null ? ($categories[$categoryTerm] ?? null) : null;
        if ($categoryId === null) {
            $this->report->error('listings', "{$label}: nema kategoriju");

            return;
        }

        $locationTerm = $this->deepest($terms[config('uvoz.tax_location')] ?? [], $this->locationParents);
        $locationId = $locationTerm !== null ? ($locations[$locationTerm] ?? null) : null;

        $slug = trim(rawurldecode((string) $post->post_name));
        if ($slug === '') {
            $slug = Str::limit(Str::slug(Text::title((string) $post->post_title)), 180, '') ?: 'oglas';
            $slug .= '-'.$wpId;
        }
        if (DB::table('listings')->where('slug', $slug)->where('id', '!=', $wpId)->exists()) {
            $this->report->error('listings', "{$label}: slug „{$slug}” već ima drugi oglas");

            return;
        }

        $status = $this->status((string) $post->post_status);
        if ($status === null) {
            $status = ListingStatus::Draft;
            $this->report->note('listings', "{$label}: nepoznat status „{$post->post_status}”, uvezen kao nacrt");
        }

        $created = $this->ctx->date($post->post_date_gmt, $post->post_date) ?? now();
        $modified = $this->ctx->date($post->post_modified_gmt, $post->post_modified) ?? $created;
        [$price, $currency] = $this->price($meta);

        $published = in_array($status, [ListingStatus::Published, ListingStatus::Expired], true) ? $created : null;
        $phone = config('uvoz.meta.listing_phone') ? Phone::normalize((string) ($meta[config('uvoz.meta.listing_phone')][0] ?? '')) : '';

        $values = [
            'user_id' => $userId,
            'category_id' => $categoryId,
            'location_id' => $locationId,
            'title' => Str::limit(Text::title((string) $post->post_title) ?: 'Oglas '.$wpId, 250, ''),
            'slug' => $slug,
            'description' => Text::plain((string) $post->post_content) ?: null,
            'price' => $price,
            'currency' => $currency->value,
            'price_eur' => $price === null ? null : Price::toEur($price, $currency),
            'status' => $status->value,
            'contact_phone' => $phone !== '' ? $phone : null,
            'lat' => $this->coordinate($meta, 'lat'),
            'lng' => $this->coordinate($meta, 'lng'),
            'featured_until' => $this->featuredUntil($meta, $existing),
            'published_at' => $published,
            'bumped_at' => $existing->bumped_at ?? $published,
            'expires_at' => $this->expiresAt($meta, $status, $modified, $existing),
            'views' => max((int) ($existing->views ?? 0), (int) ($meta[config('uvoz.meta.views')][0] ?? 0)),
            'created_at' => $created,
            'updated_at' => $modified,
            'deleted_at' => null,
        ];

        if ($existing) {
            DB::table('listings')->where('id', $wpId)->update($values);
        } else {
            DB::table('listings')->insert($values + ['id' => $wpId, 'wp_id' => $wpId]);
        }
        $this->report->imported('listings');

        $this->fieldValues($wpId, $categoryId, $meta, $terms);
        $this->images($wpId, $userId, $meta, $attachments, $label);
    }

    private function status(string $wp): ?ListingStatus
    {
        return match (true) {
            $wp === 'publish' => ListingStatus::Published,
            in_array($wp, ['pending', 'future'], true) => ListingStatus::Pending,
            in_array($wp, ['draft', 'private'], true) => ListingStatus::Draft,
            str_contains($wp, 'expire') => ListingStatus::Expired,
            default => null,
        };
    }

    /**
     * Najdublji termin (Automobili pre Vozila, naselje pre opštine).
     *
     * @param  int[]  $ids
     * @param  array<int, int>  $parents
     */
    private function deepest(array $ids, array $parents): ?int
    {
        $best = null;
        $bestDepth = -1;
        foreach ($ids as $id) {
            if (! array_key_exists($id, $parents)) {
                continue;
            }
            $depth = 0;
            $cur = $id;
            while (($parents[$cur] ?? 0) > 0 && $depth < 20) {
                $cur = $parents[$cur];
                $depth++;
            }
            if ($depth > $bestDepth) {
                $best = $id;
                $bestDepth = $depth;
            }
        }

        return $best;
    }

    /**
     * Cena iz ključa listivo_{polje}_listivo_{valuta}; EUR ima prednost ako postoje oba.
     *
     * @return array{0: ?float, 1: Currency}
     */
    private function price(array $meta): array
    {
        $pattern = '/^listivo_'.(int) config('uvoz.price_field_id').'_listivo_(\d+)$/';
        $found = [];
        foreach ($meta as $key => $values) {
            if (! preg_match($pattern, (string) $key, $m)) {
                continue;
            }
            $amount = Text::amount($values[0] ?? '');
            if ($amount === null) {
                continue;
            }
            $code = $this->currencyForTerm((int) $m[1]);
            $found[$code] ??= $amount;
        }

        foreach (['EUR', 'RSD'] as $code) {
            if (isset($found[$code])) {
                return [$found[$code], Currency::from($code)];
            }
        }

        return [null, Currency::EUR];
    }

    /** Isti redosled kao Loglasi_Cena::currency_for_term(). */
    private function currencyForTerm(int $termId): string
    {
        if (isset($this->currencyCache[$termId])) {
            return $this->currencyCache[$termId];
        }

        $eur = (int) config('uvoz.currency_term_eur');
        $rsd = (int) config('uvoz.currency_term_rsd');
        if ($termId === $eur) {
            return $this->currencyCache[$termId] = 'EUR';
        }
        if ($rsd > 0 && $termId === $rsd) {
            return $this->currencyCache[$termId] = 'RSD';
        }

        $term = $this->wp->table('terms')->where('term_id', $termId)->first(['name', 'slug']);
        $text = $term ? [$term->name, $term->slug] : [];
        foreach ($this->wp->table('termmeta')->where('term_id', $termId)->pluck('meta_value') as $value) {
            if (is_string($value) && strlen($value) <= 20) {
                $text[] = $value;
            }
        }
        $text = mb_strtolower(implode(' ', $text));

        $code = match (true) {
            (bool) preg_match('/\b(rsd|din|dinar\w*)\b|дин/u', $text) => 'RSD',
            (bool) preg_match('/€|\beur\w*\b|\bevr\w*\b/u', $text) => 'EUR',
            $rsd > 0 => 'EUR',
            default => 'RSD',
        };

        return $this->currencyCache[$termId] = $code;
    }

    private function coordinate(array $meta, string $name): ?float
    {
        $key = (string) config('uvoz.meta.'.$name);
        $raw = $key !== '' ? ($meta[$key][0] ?? null) : null;

        return is_numeric($raw) ? (float) $raw : null;
    }

    /** Istaknut: datum iz mete ako ga ima, inače važi featured_days od prvog uvoza. */
    private function featuredUntil(array $meta, ?object $existing): ?Carbon
    {
        $raw = $meta[config('uvoz.meta.featured')][0] ?? null;
        if (in_array($raw, [null, '', '0', 'false'], true)) {
            return null;
        }

        if (is_numeric($raw) && (int) $raw > 1_000_000_000) {
            return Carbon::createFromTimestamp((int) $raw);
        }
        if (! is_numeric($raw) && strtotime((string) $raw) !== false) {
            return Carbon::parse((string) $raw);
        }

        return $existing && $existing->featured_until
            ? Carbon::parse($existing->featured_until)
            : now()->addDays((int) config('uvoz.featured_days'))->startOfMinute();
    }

    /** Istek: iz Listivo mete ako postoji; inače objavljen oglas dobija listing_days od prvog uvoza. */
    private function expiresAt(array $meta, ListingStatus $status, Carbon $modified, ?object $existing): ?Carbon
    {
        $key = (string) config('uvoz.meta.expires');
        $raw = $key !== '' ? ($meta[$key][0] ?? null) : null;
        if (is_numeric($raw) && (int) $raw > 0) {
            return Carbon::createFromTimestamp((int) $raw);
        }
        if (is_string($raw) && $raw !== '' && strtotime($raw) !== false) {
            return Carbon::parse($raw);
        }

        if ($status === ListingStatus::Expired) {
            return $existing && $existing->expires_at && Carbon::parse($existing->expires_at)->isPast() ? Carbon::parse($existing->expires_at) : $modified;
        }
        if ($status !== ListingStatus::Published) {
            return null;
        }

        if ($existing && $existing->expires_at && Carbon::parse($existing->expires_at)->isFuture()) {
            return Carbon::parse($existing->expires_at);
        }

        return now()->addDays((int) config('uvoz.listing_days'))->startOfMinute();
    }

    /** Vrednosti polja se za oglas brišu i upisuju ponovo (samo polja iz uvoza). */
    private function fieldValues(int $listingId, int $categoryId, array $meta, array $terms): void
    {
        if (! $this->fields) {
            return;
        }

        $fieldIds = array_map(fn ($f) => (int) $f->id, $this->fields);
        DB::table('listing_field_values')->where('listing_id', $listingId)->whereIn('field_id', $fieldIds)->delete();

        $rows = [];
        foreach ($this->fields as $wpKey => $field) {
            $type = FieldType::tryFrom($field->type) ?? FieldType::Text;

            if (in_array($wpKey, $this->fieldTaxonomies, true)) {
                $values = $this->termSlugs($terms[$wpKey] ?? []);
            } else {
                $values = Text::flatten($meta[$wpKey] ?? []);
                $values = $this->matchOptions($values, $field);
            }
            if (! $values) {
                continue;
            }

            if ($type === FieldType::Number) {
                $num = Text::number($values[0]);
                if ($num === null) {
                    $this->report->note('field_values', "#{$listingId} {$field->key}: „{$values[0]}” nije broj");

                    continue;
                }
                $rows[] = ['listing_id' => $listingId, 'field_id' => $field->id, 'value' => rtrim(rtrim(number_format($num, 2, '.', ''), '0'), '.'), 'value_num' => $num];
            } elseif (in_array($type, [FieldType::Multiselect], true)) {
                foreach (array_unique($values) as $value) {
                    $rows[] = ['listing_id' => $listingId, 'field_id' => $field->id, 'value' => Str::limit($value, 250, ''), 'value_num' => null];
                }
            } else {
                $rows[] = ['listing_id' => $listingId, 'field_id' => $field->id, 'value' => Str::limit($values[0], 250, ''), 'value_num' => null];
            }

            DB::table('category_field')->insertOrIgnore(['category_id' => $categoryId, 'field_id' => $field->id, 'required' => false, 'sort' => 0]);
        }

        if ($rows) {
            DB::table('listing_field_values')->insert($rows);
        }
        $this->report->wp('field_values', count($rows));
        $this->report->imported('field_values', count($rows));
    }

    /** @var array<int, string> */
    private array $slugCache = [];

    /** @param  int[]  $termIds */
    private function termSlugs(array $termIds): array
    {
        $missing = array_diff($termIds, array_keys($this->slugCache));
        if ($missing) {
            foreach ($this->wp->table('terms')->whereIn('term_id', $missing)->pluck('slug', 'term_id') as $id => $slug) {
                $this->slugCache[(int) $id] = rawurldecode((string) $slug);
            }
        }

        return array_values(array_filter(array_map(fn ($id) => $this->slugCache[$id] ?? null, $termIds)));
    }

    /** Meta može da sadrži vrednost ili naziv opcije; vraća vrednost opcije. */
    private function matchOptions(array $values, object $field): array
    {
        $options = json_decode((string) $field->options, true) ?: [];
        if (! $options) {
            return $values;
        }

        return array_map(function ($value) use ($options) {
            foreach ($options as $option) {
                if (strcasecmp($option['value'], $value) === 0 || strcasecmp($option['label'], $value) === 0) {
                    return $option['value'];
                }
            }

            return $value;
        }, $values);
    }

    /**
     * Istaknuta slika prvo, pa galerija, bez duplikata (kao Loglasi_API_Formatter::image_ids).
     *
     * @return int[]
     */
    private function imageIds(array $meta): array
    {
        $ids = [];
        $thumb = (int) ($meta['_thumbnail_id'][0] ?? 0);
        if ($thumb > 0) {
            $ids[] = $thumb;
        }
        $gallery = (string) config('uvoz.meta.gallery');
        if ($gallery !== '' && isset($meta[$gallery][0])) {
            $ids = array_merge($ids, Text::idList($meta[$gallery][0]));
        }

        return array_values(array_unique($ids));
    }

    private function images(int $listingId, int $userId, array $meta, array $attachments, string $label): void
    {
        $keep = [];
        $sort = 0;
        foreach ($this->imageIds($meta) as $attachmentId) {
            $this->report->wp('images');
            $att = $attachments[$attachmentId] ?? null;
            if ($att === null) {
                $this->report->error('images', "{$label}: prilog #{$attachmentId} ne postoji");

                continue;
            }

            $path = 'oglasi/'.$listingId.'/'.$attachmentId.'-'.Importer::fileName($att['file']);
            $copy = $this->ctx->copyUpload($att['file'], $path);
            if ($copy === 'missing') {
                $this->report->error('images', "{$label}: nema fajla {$att['file']}");
            } elseif ($copy === 'skipped' && ! $this->ctx->dryRun && ! $this->warnedNoUploads) {
                $this->warnedNoUploads = true;
                $this->report->note('images', 'bez --uploads: zapisi slika su upisani, fajlovi nisu kopirani');
            }

            [$width, $height] = [$att['width'], $att['height']];
            if (($width === null || $height === null) && $this->ctx->uploads && is_file($this->ctx->uploads.'/'.$att['file'])) {
                $size = @getimagesize($this->ctx->uploads.'/'.$att['file']);
                [$width, $height] = $size ? [$size[0], $size[1]] : [null, null];
            }

            $values = [
                'user_id' => $userId,
                'path' => $path,
                'width' => $width,
                'height' => $height,
                'sort' => $sort++,
                'alt' => $att['alt'] !== null ? Str::limit($att['alt'], 250, '') : null,
                'updated_at' => now(),
            ];

            $existing = DB::table('listing_images')->where('listing_id', $listingId)->where('wp_attachment_id', $attachmentId)->value('id');
            if ($existing) {
                DB::table('listing_images')->where('id', $existing)->update($values);
            } else {
                DB::table('listing_images')->insert($values + ['listing_id' => $listingId, 'wp_attachment_id' => $attachmentId, 'created_at' => now()]);
            }
            $keep[] = $attachmentId;
            $this->report->imported('images');
        }

        // Slike uklonjene iz galerije u WordPress-u
        DB::table('listing_images')->where('listing_id', $listingId)->whereNotNull('wp_attachment_id')
            ->whereNotIn('wp_attachment_id', $keep ?: [0])->delete();
    }

    /** Za delta uvoz: oglasi koji su ovde, a u WordPress-u više ne postoje. */
    private function reportMissing(): void
    {
        $wpIds = $this->wp->table('posts')->where('post_type', config('uvoz.post_type'))->pluck('ID')->map(fn ($id) => (int) $id)->flip();
        foreach (DB::table('listings')->whereNotNull('wp_id')->whereNull('deleted_at')->pluck('wp_id') as $wpId) {
            if (! isset($wpIds[(int) $wpId])) {
                $this->report->note('listings', "#{$wpId}: postoji ovde, a nema ga u WordPress-u (proveriti ručno)");
            }
        }
    }
}
