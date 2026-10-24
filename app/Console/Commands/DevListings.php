<?php

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Enums\Currency;
use App\Enums\FieldType;
use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\Field;
use App\Models\Location;
use App\Models\User;
use App\Services\Catalog;
use App\Services\SearchIndex;
use App\Support\Home\HomeSections;
use App\Support\Price;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Realistic local data for development and performance tests: Serbian titles,
 * EUR and RSD prices, "Dogovor", featured listings, firms, field values.
 * Never runs in production. Needs categories and places (KatalogSeeder).
 */
class DevListings extends Command
{
    protected $signature = 'dev:listings {count=2000 : number of listings} {--fresh : delete listings made by this command first}';

    protected $description = 'Create realistic test listings for local development';

    private const EMAIL_DOMAIN = 'primer.loglasi.test';

    /** Title parts per root category slug: [things, details]. */
    private const TITLES = [
        'automobili' => [['Golf 7 1.6 TDI', 'Opel Astra J 1.7 CDTI', 'Škoda Octavia 2.0 TDI', 'Fiat Punto 1.2', 'Audi A4 2.0 TDI', 'BMW 320d', 'Renault Clio 1.5 dCi', 'Peugeot 308 1.6 HDi', 'Zastava 101', 'Mercedes C 220 CDI', 'Toyota Yaris 1.0', 'Dacia Duster 1.5 dCi'], ['registrovan', 'prvi vlasnik', 'servisna knjiga', 'odličan', 'uvezen iz Nemačke', 'hitno', 'garažiran', 'nove gume']],
        'auto-delovi-i-oprema' => [['Zimske gume 16"', 'Alu felne 15"', 'Akumulator 74Ah', 'Krovni nosač', 'Far za Golf 5', 'Menjač za Pasat'], ['kao novo', 'polovno', 'povoljno', 'malo korišćeno']],
        'motocikli' => [['Skuter 50 ccm', 'Yamaha XT 600', 'Honda CBF 500', 'Tomos APN 6', 'Piaggio Beverly 300'], ['registrovan', 'odlično stanje', 'hitno']],
        'kamioni' => [['Iveco Daily kiper', 'MAN TGL 12.180', 'Mercedes Sprinter kombi', 'FAP 1314'], ['registrovan', 'u voznom stanju']],
        'bicikli-i-delovi' => [['Bicikl Capriolo 26"', 'Električni bicikl', 'Dečiji bicikl 20"', 'Brdski bicikl Scott', 'Polovni bicikl za grad'], ['malo vožen', 'kao nov', 'povoljno', 'sa opremom']],
        'nekretnine' => [['Dvosoban stan, centar', 'Kuća sa placem', 'Plac 10 ari', 'Garsonjera kod suda', 'Vikendica na Drini', 'Lokal u pešačkoj zoni', 'Trosoban stan, Lagator'], ['uknjiženo', 'hitno', 'odmah useljivo', 'renovirano', 'blizu škole']],
        'mobilni-telefoni' => [['iPhone 13 128 GB', 'Samsung Galaxy S21', 'Xiaomi Redmi Note 12', 'Huawei P30', 'Nokia 3310'], ['kao nov', 'sa kutijom', 'bez ogrebotina', 'garancija']],
        'elektronika' => [['Laptop Lenovo ThinkPad', 'TV Samsung 55"', 'PlayStation 5', 'Fotoaparat Canon 2000D', 'Zvučnici JBL'], ['ispravno', 'kao novo', 'povoljno']],
        'kucni-aparati' => [['Veš mašina Gorenje', 'Frižider Beko', 'Šporet na drva', 'Bojler 80 l', 'Klima uređaj 12000 BTU'], ['ispravno', 'malo korišćeno', 'sa garancijom']],
        'namestaj' => [['Ugaona garnitura', 'Trpezarijski sto sa stolicama', 'Ormar dvokrilni', 'Krevet 160x200', 'Kuhinja po meri'], ['kao nova', 'očuvano', 'hitno']],
        'kuca-i-basta' => [['Kosačica za travu', 'Baštenska garnitura', 'Sadnice šljive', 'Cveće za dvorište'], ['povoljno', 'novo']],
        'gradjevinarstvo' => [['Crep Kanal', 'Cigla 1000 komada', 'Građevinska skela', 'Pesak i šljunak'], ['dostava', 'povoljno']],
        'alati-i-masine' => [['Motorna testera Stihl', 'Bušilica Bosch', 'Agregat 5 kW', 'Aparat za varenje'], ['ispravno', 'kao novo']],
        'poljoprivreda' => [['Traktor IMT 539', 'Prasići', 'Kukuruz u zrnu', 'Seno u balama', 'Motokultivator', 'Drva za ogrev, bukva'], ['povoljno', 'dostava', 'domaće']],
        'moda' => [['Zimska jakna', 'Ženske čizme 38', 'Muško odelo', 'Venčanica', 'Ručni sat Casio'], ['nošeno jednom', 'novo sa etiketom']],
        'za-decu' => [['Kolica za bebe', 'Autosedište', 'Krevetac', 'Igračke Lego'], ['očuvano', 'kao novo']],
        'sport-i-rekreacija' => [['Sobni bicikl', 'Tegovi set', 'Pecaroška oprema', 'Skije sa pancericama'], ['malo korišćeno', 'povoljno']],
        'muzicki-instrumenti' => [['Harmonika Weltmeister', 'Gitara Yamaha', 'Klavijatura Casio', 'Bubnjevi'], ['odlično stanje', 'sa torbom']],
        'kucni-ljubimci' => [['Štenci nemačkog ovčara', 'Mačići', 'Akvarijum 100 l', 'Kavez za papagaja'], ['vakcinisani', 'poklanjam']],
        'knjige' => [['Udžbenici za 5. razred', 'Kolekcija romana', 'Enciklopedija', 'Stripovi Alan Ford'], ['očuvano']],
        'usluge' => [['Moler i farbar', 'Vodoinstalater', 'Prevoz robe kombijem', 'Časovi matematike', 'Električar', 'Majstor za klime'], ['povoljno', 'brzo i kvalitetno', 'cela opština']],
        'poslovi' => [['Potreban konobar', 'Traži se vozač C kategorije', 'Posao u prodavnici', 'Potreban građevinski radnik'], ['odmah', 'prijava i dobra plata']],
        'besplatne-stvari' => [['Poklanjam stari nameštaj', 'Poklanjam garderobu', 'Poklanjam knjige'], ['preuzimanje lično']],
        'ostalo' => [['Razno', 'Starine', 'Kolekcija maraka'], ['povoljno']],
    ];

    private const DESCRIPTIONS = [
        'Prodajem zbog selidbe. Stvar je očuvana i spremna za upotrebu.',
        'Za sve informacije pozovite ili pošaljite poruku. Moguće je pogledati uživo u Loznici.',
        'Cena je fiksna, ozbiljnim kupcima moguć mali popust.',
        'Nalazi se u Banji Koviljači, može i dostava na teritoriji opštine Loznica.',
        'Sve ispravno, bez ulaganja. Zamena nije moguća.',
        'Hitno, zbog odlaska u inostranstvo. Javite se ćirilicom ili latinicom, svejedno.',
    ];

    public function handle(Catalog $catalog, SearchIndex $index): int
    {
        if (app()->isProduction()) {
            $this->error('This command does not run in production.');

            return self::FAILURE;
        }

        $count = max(1, (int) $this->argument('count'));
        $categories = Category::query()->where('is_active', true)->get();
        $locations = Location::query()->pluck('id')->all();

        if ($categories->isEmpty() || $locations === []) {
            $this->error('No categories or places. Run: php artisan db:seed --class=KatalogSeeder');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->fresh();
        }

        [$privateIds, $firmIds] = $this->sellers();
        $roots = $categories->keyBy('id');
        $rate = Price::rate();
        $now = now();
        $made = 0;
        $bar = $this->output->createProgressBar($count);

        while ($made < $count) {
            $batch = min(500, $count - $made);
            $rows = [];
            $fieldRows = [];

            for ($i = 0; $i < $batch; $i++) {
                $category = $categories->random();
                $root = $this->root($category, $roots);
                [$things, $details] = self::TITLES[$root->slug] ?? self::TITLES['ostalo'];
                $title = $things[array_rand($things)].', '.$details[array_rand($details)];
                $description = self::DESCRIPTIONS[array_rand(self::DESCRIPTIONS)].' '.self::DESCRIPTIONS[array_rand(self::DESCRIPTIONS)];

                $isFirm = $firmIds !== [] && mt_rand(1, 100) <= 18;
                $userId = $isFirm ? $firmIds[array_rand($firmIds)] : $privateIds[array_rand($privateIds)];

                [$price, $currency] = $this->price($root->slug);
                $priceEur = $price === null ? null : round($currency === Currency::RSD ? $price / $rate : $price, 2);

                $published = $now->copy()->subMinutes(mt_rand(5, 60 * 24 * 29));
                $bumped = mt_rand(1, 100) <= 10 ? $published->copy()->addMinutes(mt_rand(1, max(1, (int) $published->diffInMinutes($now)))) : $published;
                $status = mt_rand(1, 100) <= 92 ? ListingStatus::Published : (mt_rand(0, 1) ? ListingStatus::Pending : ListingStatus::Expired);

                $rows[] = [
                    'user_id' => $userId,
                    'category_id' => $category->id,
                    'location_id' => $this->location($locations),
                    'title' => $title,
                    'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
                    'description' => $description,
                    'price' => $price,
                    'currency' => $currency->value,
                    'price_eur' => $priceEur,
                    'status' => $status->value,
                    'featured_until' => mt_rand(1, 100) <= 4 ? $now->copy()->addDays(mt_rand(1, 30)) : null,
                    'bumped_at' => $bumped,
                    'published_at' => $published,
                    'expires_at' => $status === ListingStatus::Expired ? $now->copy()->subDays(mt_rand(1, 20)) : $published->copy()->addDays(30),
                    'views' => mt_rand(0, 900),
                    'created_at' => $published,
                    'updated_at' => $bumped,
                ];
            }

            DB::transaction(function () use ($rows, $catalog, &$fieldRows) {
                DB::table('listings')->insert($rows);
                $lastId = (int) DB::table('listings')->max('id');
                $firstId = $lastId - count($rows) + 1;
                foreach ($rows as $n => $row) {
                    foreach ($this->fieldValues($catalog, (int) $row['category_id']) as $value) {
                        $fieldRows[] = $value + ['listing_id' => $firstId + $n];
                    }
                }
                foreach (array_chunk($fieldRows, 1000) as $chunk) {
                    DB::table('listing_field_values')->insert($chunk);
                }
            });

            $made += $batch;
            $bar->advance($batch);
        }

        $bar->finish();
        $this->newLine();

        $this->line('Building the search index...');
        $indexed = $index->rebuild();
        $catalog->flushCounts();
        HomeSections::flush();
        $this->line("Search index: {$indexed} active listings.");
        $this->info("Listings created: {$made}.");

        return self::SUCCESS;
    }

    /** Test users: 150 private persons and 20 firms (made once, reused). */
    private function sellers(): array
    {
        $existing = User::query()->where('email', 'like', '%@'.self::EMAIL_DOMAIN)->get(['id', 'account_type']);
        if ($existing->isNotEmpty()) {
            return [
                $existing->where('account_type', AccountType::Private)->pluck('id')->values()->all(),
                $existing->where('account_type', AccountType::Firm)->pluck('id')->values()->all(),
            ];
        }

        $first = ['Marko', 'Jelena', 'Nikola', 'Milica', 'Stefan', 'Ana', 'Đorđe', 'Jovana', 'Miloš', 'Ivana', 'Dušan', 'Snežana', 'Žarko', 'Tijana', 'Vuk'];
        $last = ['Petrović', 'Jovanović', 'Nikolić', 'Marković', 'Đurić', 'Stanković', 'Ilić', 'Pavlović', 'Simić', 'Lukić', 'Tomić', 'Šarić'];
        $firms = ['Auto centar Drina', 'Stolarija Jadar', 'Agro Lešnica', 'Elektro servis Banja', 'Nekretnine Loznica', 'Moto plac Podrinje', 'Građevinar Tršić', 'Pekara Kod Mileta', 'Klima servis Lipnica', 'Optika Vid', 'Cvećara Ruža', 'Vulkanizer Brzi', 'Prevoz Klupci', 'Kompjuter centar', 'Salon nameštaja Dom', 'Farma Korenita', 'Auto delovi Zvornik', 'Gume plus', 'Bela tehnika Servis', 'Moler Jovanović'];
        $password = Hash::make(Str::random(32));
        $private = [];
        $firm = [];

        for ($i = 1; $i <= 150; $i++) {
            $name = $first[array_rand($first)].' '.$last[array_rand($last)];
            $private[] = User::query()->insertGetId([
                'name' => $name, 'email' => 'korisnik'.$i.'@'.self::EMAIL_DOMAIN, 'password' => $password,
                'slug' => Str::slug($name).'-'.$i, 'account_type' => AccountType::Private->value,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        foreach ($firms as $i => $name) {
            $id = User::query()->insertGetId([
                'name' => $name, 'email' => 'firma'.($i + 1).'@'.self::EMAIL_DOMAIN, 'password' => $password,
                'slug' => Str::slug($name), 'account_type' => AccountType::Firm->value,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('firms')->insert([
                'user_id' => $id, 'name' => $name, 'address' => 'Loznica',
                'package' => $i % 4 === 3 || $i < 12 ? null : ['start', 'pro', 'max'][$i % 3], 'package_period' => $i % 4 === 3 || $i < 12 ? null : 'monthly',
                'package_ends_at' => $i % 4 === 3 ? null : now()->addMonth(), 'free_program' => $i < 12 && $i % 4 !== 3, 'founding_free' => $i < 12,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $firm[] = $id;
        }

        return [$private, $firm];
    }

    private function fresh(): void
    {
        $userIds = User::query()->where('email', 'like', '%@'.self::EMAIL_DOMAIN)->pluck('id');
        $deleted = 0;
        foreach ($userIds->chunk(100) as $chunk) {
            $deleted += DB::table('listings')->whereIn('user_id', $chunk)->delete();
        }
        $this->line("Listings deleted: {$deleted}.");
    }

    private function root(Category $category, $byId): Category
    {
        $guard = 0;
        while ($category->parent_id !== null && $byId->has($category->parent_id) && $guard++ < 10) {
            $category = $byId->get($category->parent_id);
        }

        return $category;
    }

    /** Loznica is the most common place. */
    private function location(array $ids): int
    {
        static $loznica = null;
        $loznica ??= Location::query()->where('slug', 'loznica')->value('id') ?? $ids[0];

        return mt_rand(1, 100) <= 45 ? $loznica : $ids[array_rand($ids)];
    }

    /** @return array{0: ?int, 1: Currency} */
    private function price(string $root): array
    {
        if (mt_rand(1, 100) <= 8 || in_array($root, ['poslovi', 'besplatne-stvari'], true)) {
            return [null, Currency::EUR];
        }

        [$min, $max] = match ($root) {
            'automobili', 'kamioni' => [800, 25000],
            'nekretnine' => [5000, 150000],
            'motocikli' => [300, 8000],
            'usluge', 'knjige', 'moda', 'za-decu' => [5, 300],
            default => [10, 2500],
        };
        $eur = mt_rand($min, $max);

        // About a third of prices are in dinars, rounded the way people write them.
        if (mt_rand(1, 100) <= 33) {
            $rsd = (int) (round($eur * Price::rate() / 100) * 100);

            return [max(100, $rsd), Currency::RSD];
        }

        return [$eur >= 1000 ? (int) (round($eur / 50) * 50) : $eur, Currency::EUR];
    }

    /** Random values for the fields of a category, stored like ListingFields::save(). */
    private function fieldValues(Catalog $catalog, int $categoryId): array
    {
        $rows = [];
        foreach ($catalog->fieldsFor($categoryId) as $field) {
            /** @var Field $field */
            if (! $field->required && mt_rand(1, 100) > 70) {
                continue;
            }
            $options = collect((array) $field->options)->pluck('value')->all();
            $rows = array_merge($rows, match ($field->type) {
                FieldType::Select => $options ? [['field_id' => $field->id, 'value' => (string) $options[array_rand($options)], 'value_num' => null]] : [],
                FieldType::Multiselect => $options ? [['field_id' => $field->id, 'value' => (string) $options[array_rand($options)], 'value_num' => null]] : [],
                FieldType::Checkbox => mt_rand(0, 1) ? [['field_id' => $field->id, 'value' => '1', 'value_num' => 1.0]] : [],
                FieldType::Year => [$this->number($field->id, mt_rand(1995, (int) date('Y')))],
                FieldType::Number => [$this->number($field->id, match ($field->key) {
                    'kilometraza' => mt_rand(5, 400) * 1000,
                    'kvadratura' => mt_rand(20, 250),
                    'kubikaza' => mt_rand(9, 30) * 100,
                    default => mt_rand(1, 1000),
                })],
                default => $field->key === 'model' ? [['field_id' => $field->id, 'value' => 'Model '.mt_rand(1, 9), 'value_num' => null]] : [],
            });
        }

        return $rows;
    }

    private function number(int $fieldId, int $value): array
    {
        return ['field_id' => $fieldId, 'value' => (string) $value, 'value_num' => (float) $value];
    }
}
