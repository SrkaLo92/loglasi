<?php

namespace Tests\Feature\Uvoz;

use App\Enums\AccountType;
use App\Enums\Currency;
use App\Enums\ListingStatus;
use App\Enums\Role;
use App\Models\Category;
use App\Models\Field;
use App\Models\Firm;
use App\Models\Listing;
use App\Models\Product;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\ProductSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class UvozWordpressTest extends UvozTestCase
{
    public function test_imports_everything_with_wordpress_ids_and_slugs(): void
    {
        $this->import()->assertSuccessful();

        // 9 naloga u WP-u, 3 spam se ne prenose
        $this->assertSame(6, User::count());
        foreach ([1 => 'srdjan', 2 => 'marko-petrovic', 3 => 'jelena', 5 => 'pera', 7 => 'novak-novi', 117 => 'auto-centar-drina'] as $id => $slug) {
            $user = User::find($id);
            $this->assertNotNull($user, "korisnik #{$id}");
            $this->assertSame($id, (int) $user->wp_id);
            $this->assertSame($slug, $user->slug);
        }

        // Oglasi: isti ID i slug; nacrt bez sluga dobija slug sa ID-jem
        $this->assertEqualsCanonicalizing([5001, 5002, 5003, 5004, 5005, 5009, 5010], Listing::pluck('id')->all());
        $this->assertSame('audi-a4-2-0-tdi', Listing::find(5001)->slug);
        $this->assertSame(5001, (int) Listing::find(5001)->wp_id);
        $this->assertSame('nacrt-bez-sluga-5004', Listing::find(5004)->slug);

        // Kategorije i mesta: slug i stablo
        $this->assertSame(7, Category::count());
        $automobili = Category::where('slug', 'automobili')->first();
        $this->assertSame('vozila', $automobili->parent->slug);
        $this->assertSame(102, (int) $automobili->wp_term_id);
        $this->assertSame(1, Category::where('slug', 'vozila')->value('sort'));
        $this->assertSame('Automobili, motori i delovi', Category::where('slug', 'vozila')->value('description'));
        $this->assertSame(['banja-koviljaca', 'klupci', 'lesnica', 'loznica'], DB::table('locations')->orderBy('slug')->pluck('slug')->all());
    }

    public function test_users_keep_password_hash_role_and_phone(): void
    {
        $this->import()->assertSuccessful();

        $admin = User::find(1);
        $this->assertSame('$P$BZlPX7NIx8MYpXokBW2AGsN7i.aUOt0', DB::table('users')->where('id', 1)->value('password'));
        $this->assertSame(Role::Admin, $admin->role);
        $this->assertSame('+381655415128', $admin->phone); // pozivni broj odvojeno

        $marko = User::find(2);
        $this->assertStringStartsWith('$wp$2y$10$', DB::table('users')->where('id', 2)->value('password'));
        $this->assertSame(Role::User, $marko->role);
        $this->assertSame('+381651234567', $marko->phone);
        $this->assertSame('Marko Petrović', $marko->name);

        $jelena = User::find(3);
        $this->assertSame('+381641112222', $jelena->phone); // +3810… bez nule
        $this->assertNotNull($jelena->phone_verified_at);
        $this->assertSame('avatari/3/6006-jelena.jpg', $jelena->avatar_path);
        Storage::disk('public')->assertExists('avatari/3/6006-jelena.jpg');

        $this->assertNull(User::find(5)->phone); // neispravan broj se ne prenosi
        $this->assertNull(User::find(2)->phone_verified_at);
        $this->assertNotNull(User::find(2)->email_verified_at);
        $this->assertSame('2024-05-10 14:30:00', User::find(2)->created_at->format('Y-m-d H:i:s')); // UTC → Beograd
    }

    public function test_business_user_becomes_firm_with_profile(): void
    {
        $this->import()->assertSuccessful();

        $user = User::find(117);
        $this->assertSame(AccountType::Firm, $user->account_type);
        $this->assertSame('+38115123456', $user->phone);

        $firm = Firm::where('user_id', 117)->first();
        $this->assertSame('Auto centar Drina', $firm->name);
        $this->assertSame('Polovni automobili sa garancijom, otkup i zamena.', $firm->description);
        $this->assertSame('Kneza Miloša 12', $firm->address);
        $this->assertSame('loznica', $firm->location->slug);
        $this->assertSame('08–16', $firm->working_hours['1']);
        $this->assertSame('Zatvoreno', $firm->working_hours['7']);
        $this->assertNotNull($firm->verified_at);
        $this->assertTrue($firm->founding_free);
        $this->assertSame('firme/117/6004-drina-logo.png', $firm->logo_path);
        Storage::disk('public')->assertExists('firme/117/6004-drina-logo.png');
        $this->assertSame(1, Firm::count());
        $this->assertSame(AccountType::Private, User::find(2)->account_type);
    }

    public function test_listing_status_price_currency_and_meta(): void
    {
        $this->import()->assertSuccessful();

        $audi = Listing::find(5001);
        $this->assertSame(ListingStatus::Published, $audi->status);
        $this->assertSame('Audi A4 2.0 TDI & navigacija', $audi->title);
        $this->assertSame("Odlično stanje, redovno servisiran.\n\nPrvi vlasnik – Loznica.\nMoguća zamena.", $audi->description);
        $this->assertSame('8900.00', $audi->price);
        $this->assertSame(Currency::EUR, $audi->currency);
        $this->assertSame('8900.00', $audi->price_eur);
        $this->assertSame('8.900 €', $audi->priceLabel());
        $this->assertSame('automobili', $audi->category->slug); // najdublja kategorija
        $this->assertSame('loznica', $audi->location->slug);
        $this->assertSame(2, $audi->user_id);
        $this->assertTrue($audi->isFeatured());
        $this->assertSame(120, $audi->views);
        $this->assertSame('2026-09-05 14:00:00', $audi->published_at->format('Y-m-d H:i:s'));
        $this->assertTrue($audi->expires_at->isFuture());

        $stan = Listing::find(5002);
        $this->assertSame(Currency::RSD, $stan->currency);
        $this->assertSame('12.500 din', $stan->priceLabel());
        $this->assertEqualsWithDelta(12500 / 117.5, (float) $stan->price_eur, 0.01); // kurs iz loglasi_kurs_eur
        $this->assertSame('klupci', $stan->location->slug); // naselje pre opštine
        $this->assertFalse($stan->isFeatured());

        $this->assertSame('11500.00', Listing::find(5009)->price); // „11 500”
        $this->assertFalse(Listing::find(5009)->isFeatured()); // listivo_featured = 0

        $iphone = Listing::find(5003);
        $this->assertSame(ListingStatus::Pending, $iphone->status);
        $this->assertNull($iphone->price); // 0 = Dogovor
        $this->assertSame('Dogovor', $iphone->priceLabel());

        $this->assertSame(ListingStatus::Draft, Listing::find(5004)->status);
        $this->assertSame(ListingStatus::Expired, Listing::find(5005)->status);
        $this->assertSame(ListingStatus::Draft, Listing::find(5010)->status); // private
        $this->assertNull(Listing::withTrashed()->find(5006)); // otpad
        $this->assertNull(Listing::withTrashed()->find(5011)); // auto-draft
        $this->assertNull(Listing::withTrashed()->find(5012)); // autor ne postoji
        $this->assertNull(Listing::withTrashed()->find(5013)); // bez kategorije
    }

    public function test_fields_and_values(): void
    {
        $this->import()->assertSuccessful();

        $marka = Field::where('wp_key', 'listivo_1910')->first();
        $this->assertSame('marka_automobila', $marka->key);
        $this->assertSame('Marka automobila', $marka->label);
        $this->assertSame('select', $marka->type->value);
        $this->assertContains(['value' => 'audi', 'label' => 'Audi'], $marka->options);

        $this->assertSame('multiselect', Field::where('wp_key', 'listivo_945')->first()->type->value);
        $km = Field::where('wp_key', 'listivo_1912')->first();
        $this->assertSame('number', $km->type->value);
        $this->assertSame('range', $km->filter->value);
        $this->assertSame('text', Field::where('wp_key', 'listivo_4661')->first()->type->value);
        $this->assertNull(Field::where('wp_key', 'listivo_946')->first()); // nekorišćeno
        $this->assertNull(Field::where('wp_key', 'listivo_434')->first()); // valuta nije polje

        $values = fn (int $listing, string $wpKey) => DB::table('listing_field_values as v')
            ->join('fields as f', 'f.id', '=', 'v.field_id')
            ->where('v.listing_id', $listing)->where('f.wp_key', $wpKey)
            ->orderBy('v.value')->get(['v.value', 'v.value_num']);

        $this->assertSame(['audi'], $values(5001, 'listivo_1910')->pluck('value')->all());
        $this->assertSame(['klima', 'navigacija'], $values(5001, 'listivo_945')->pluck('value')->all());
        $this->assertSame('185000.00', $values(5001, 'listivo_1912')->first()->value_num);
        $this->assertSame('54.5', $values(5002, 'listivo_4657')->first()->value);
        $this->assertSame('54.50', $values(5002, 'listivo_4657')->first()->value_num);
        $this->assertSame('Uknjižen, prvi sprat', $values(5002, 'listivo_4661')->first()->value);

        // Privremena polja loglasi-core: naziv opcije → vrednost
        $this->assertSame(['prodajem'], $values(5003, 'loglasi_vrsta_oglasa')->pluck('value')->all());
        $this->assertSame(['kao-novo'], $values(5003, 'loglasi_stanje')->pluck('value')->all());
        $this->assertSame(['dostava', 'licno-preuzimanje'], $values(5003, 'loglasi_preuzimanje')->pluck('value')->all());
        $this->assertSame(['da'], $values(5001, 'loglasi_moguca_zamena')->pluck('value')->all());

        // Polja su vezana za kategorije u kojima se koriste
        $automobili = Category::where('slug', 'automobili')->first();
        $this->assertEqualsCanonicalizing(['listivo_1910', 'listivo_1911', 'listivo_945', 'listivo_1912', 'loglasi_moguca_zamena'], $automobili->fields->pluck('wp_key')->all());
    }

    public function test_empty_numbers_saved_as_zero_are_not_imported(): void
    {
        // Listivo keeps every empty number field as 0, also fields of other categories
        $this->wp()->table('postmeta')->insert([
            ['post_id' => 5002, 'meta_key' => 'listivo_1912', 'meta_value' => '0'],
            ['post_id' => 5003, 'meta_key' => 'listivo_130_listivo_435', 'meta_value' => '0'],
        ]);

        $this->import()->assertSuccessful();

        $km = Field::where('wp_key', 'listivo_1912')->first();
        $this->assertFalse(DB::table('listing_field_values')->where('listing_id', 5002)->where('field_id', $km->id)->exists());
        $stan = Listing::find(5002);
        $this->assertNotContains('listivo_1912', $stan->category->fields->pluck('wp_key')->all());
        $this->assertNull(Listing::find(5003)->price);
    }

    public function test_clean_fields_command_repairs_an_older_import(): void
    {
        $this->import()->assertSuccessful();
        $km = Field::where('wp_key', 'listivo_1912')->first();
        $stan = Listing::find(5002);
        DB::table('listing_field_values')->insert(['listing_id' => 5002, 'field_id' => $km->id, 'value' => '0', 'value_num' => 0]);
        DB::table('category_field')->insert(['category_id' => $stan->category_id, 'field_id' => $km->id, 'required' => false, 'sort' => 0]);

        $this->artisan('uvoz:ocisti-polja', ['--dry-run' => true])->expectsOutputToContain('Would remove 1 empty field values (1 listings) and 1 unused')->assertSuccessful();
        $this->artisan('uvoz:ocisti-polja')->expectsOutputToContain('Removed 1 empty field values (1 listings) and 1 unused')->assertSuccessful();

        $this->assertFalse(DB::table('listing_field_values')->where('listing_id', 5002)->where('field_id', $km->id)->exists());
        $this->assertFalse(DB::table('category_field')->where('category_id', $stan->category_id)->where('field_id', $km->id)->exists());
        $automobili = Category::where('slug', 'automobili')->first();
        $this->assertContains('listivo_1912', $automobili->fields()->pluck('wp_key')->all());
        $this->assertSame('185000.00', DB::table('listing_field_values')->where('listing_id', 5001)->where('field_id', $km->id)->value('value_num'));
    }

    public function test_listed_live_paths_become_gone_unless_they_exist(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'gone');
        file_put_contents($file, "# sa živog sajta\nhttps://loglasi.rs/oglas/obrisan-oglas/\n/oglas/%D0%BF%D1%80%D0%BE%D0%B4%D0%B0%D1%98%D0%B5%D0%BC\n/oglas/dvosoban-stan-klupci/ # postoji\n\n");
        config(['uvoz.gone_file' => $file]);

        $this->import()->assertSuccessful();
        unlink($file);

        $this->assertTrue(DB::table('gone_urls')->where('path', '/oglas/obrisan-oglas/')->exists());
        $this->assertTrue(DB::table('gone_urls')->where('path', '/oglas/продајем/')->exists());
        $this->assertFalse(DB::table('gone_urls')->where('path', '/oglas/dvosoban-stan-klupci/')->exists());
        $this->get('/oglas/obrisan-oglas/')->assertStatus(410);
    }

    public function test_category_seo_texts_are_filled_after_import(): void
    {
        $this->import()->assertSuccessful();

        $automobili = Category::where('slug', 'automobili')->first();
        $this->assertSame('Automobili Loznica – polovni automobili i auto delovi | loglasi.rs', $automobili->seo_title);
        $this->assertNotEmpty($automobili->seo_faq);
    }

    public function test_field_definitions_can_be_overridden_in_config(): void
    {
        config(['uvoz.fields' => ['listivo_1912' => ['key' => 'km', 'unit' => 'km']]]);

        $this->import()->assertSuccessful();

        $field = Field::where('wp_key', 'listivo_1912')->first();
        $this->assertSame('km', $field->key);
        $this->assertSame('km', $field->unit);
        $this->assertSame('Kilometraža', $field->label);
    }

    public function test_images_are_copied_in_gallery_order(): void
    {
        $this->import()->assertSuccessful();

        $images = Listing::find(5001)->images;
        $this->assertSame([6001, 6002], $images->pluck('wp_attachment_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame('oglasi/5001/6001-audi-1.jpg', $images[0]->path);
        $this->assertSame('Audi A4 spreda', $images[0]->alt);
        $this->assertSame(1600, $images[0]->width);
        $this->assertNull($images[0]->variants);
        Storage::disk('public')->assertExists('oglasi/5001/6001-audi-1.jpg');
        Storage::disk('public')->assertExists('oglasi/5001/6002-audi-2.jpg');

        // Smanjena slika (-scaled): kopira se original
        $stan = Listing::find(5002)->images->first();
        $this->assertSame('oglasi/5002/6003-stan.jpg', $stan->path);
        Storage::disk('public')->assertExists('oglasi/5002/6003-stan.jpg');

        // Slike spam oglasa se ne kopiraju
        Storage::disk('public')->assertMissing('oglasi/5007/6005-kredit.jpg');
        $this->assertSame(3, DB::table('listing_images')->count());
    }

    public function test_without_uploads_images_are_recorded_but_not_copied(): void
    {
        $this->artisan('uvoz:wordpress')->assertSuccessful();

        $this->assertSame(3, DB::table('listing_images')->count());
        Storage::disk('public')->assertMissing('oglasi/5001/6001-audi-1.jpg');
    }

    public function test_spam_accounts_are_skipped_and_their_urls_are_gone(): void
    {
        $this->import()->assertSuccessful();

        foreach ([80, 106, 109] as $id) {
            $this->assertNull(User::withTrashed()->where('wp_id', $id)->first());
        }
        $this->assertNull(Listing::withTrashed()->find(5007));
        $this->assertNull(Listing::withTrashed()->find(5008));

        $gone = DB::table('gone_urls')->pluck('path')->all();
        foreach (['/korisnik/brzi-krediti/', '/korisnik/casino-bonus/', '/korisnik/pills-shop/', '/oglas/brzi-krediti-bez-papira/', '/oglas/casino-bonus-500/', '/oglas/obrisan-oglas/'] as $path) {
            $this->assertContains($path, $gone);
        }

        // Ništa sa 410 liste ne postoji u novoj bazi
        foreach ($gone as $path) {
            if (preg_match('~^/oglas/([^/]+)/$~', $path, $m)) {
                $this->assertFalse(Listing::where('slug', $m[1])->exists(), $path);
            }
        }

        // Spam poruka i omiljeni spam oglas nisu preneti
        $this->assertSame(0, DB::table('messages')->where('body', 'like', '%lekove%')->count());
        $this->assertFalse(DB::table('favorites')->where('listing_id', 5007)->exists());
    }

    public function test_favorites_and_messages(): void
    {
        $this->import()->assertSuccessful();

        $this->assertEqualsCanonicalizing([5001, 5002], DB::table('favorites')->where('user_id', 3)->pluck('listing_id')->map(fn ($id) => (int) $id)->all());

        $conversation = DB::table('conversations')->where('listing_id', 5001)->first();
        $this->assertSame(3, (int) $conversation->buyer_id);
        $this->assertSame(2, (int) $conversation->seller_id);
        $this->assertSame('2026-09-07 10:30:00', $conversation->last_message_at);
        $this->assertSame(['Dobar dan, da li je auto još dostupan?', 'Jeste, možete da dođete u subotu.'],
            DB::table('messages')->where('conversation_id', $conversation->id)->orderBy('id')->pluck('body')->all());

        $direct = DB::table('conversations')->whereNull('listing_id')->first();
        $this->assertSame(7, (int) $direct->buyer_id);
        $this->assertSame(117, (int) $direct->seller_id);
        $this->assertSame(3, DB::table('conversations')->count());
        $this->assertSame(4, DB::table('messages')->count());
    }

    public function test_settings_and_prices_are_imported(): void
    {
        $this->seed(ProductSeeder::class);

        $this->import()->assertSuccessful();

        Settings::flush();
        $this->assertSame(117.5, (float) Settings::get('eur_rate'));
        $this->assertSame(5, (int) Settings::get('private_active_limit')); // the old monthly limit is not imported
        $this->assertSame(60, Product::where('code', Product::BUMP)->value('price_rsd'));
        $this->assertFalse((bool) Product::where('code', Product::FEATURED_30)->value('is_active'));
        $this->assertSame(18, Product::where('display_only', false)->count()); // 4 old products + 14 sold by payments
    }

    public function test_running_twice_gives_the_same_result(): void
    {
        $this->import()->assertSuccessful();
        $first = $this->counts();
        $snapshot = DB::table('listings')->orderBy('id')->get(['id', 'slug', 'price', 'currency', 'featured_until', 'expires_at', 'bumped_at', 'status'])->toArray();
        $users = DB::table('users')->orderBy('id')->get(['id', 'slug', 'email', 'password', 'phone'])->toArray();

        $this->travel(2)->hours();
        $this->import()->assertSuccessful();

        $this->assertSame($first, $this->counts());
        $this->assertEquals($snapshot, DB::table('listings')->orderBy('id')->get(['id', 'slug', 'price', 'currency', 'featured_until', 'expires_at', 'bumped_at', 'status'])->toArray());
        $this->assertEquals($users, DB::table('users')->orderBy('id')->get(['id', 'slug', 'email', 'password', 'phone'])->toArray());
        $this->assertSame(['users' => 6, 'firms' => 1, 'categories' => 7, 'locations' => 4, 'fields' => 10, 'category_field' => 10, 'listings' => 7, 'listing_field_values' => 15, 'listing_images' => 3, 'favorites' => 2, 'conversations' => 3, 'messages' => 4, 'gone_urls' => 9], $first);
    }

    public function test_since_imports_only_the_delta(): void
    {
        $this->import(['--since' => '2026-10-20'])->assertSuccessful();

        // Novi nalog #7 i firma #117 (učesnik nove poruke); samo oglas izmenjen posle preseka
        $this->assertEqualsCanonicalizing([7, 117], User::pluck('id')->all());
        $this->assertSame([5009], Listing::pluck('id')->all());
        $this->assertSame(1, DB::table('messages')->count());
        $this->assertSame(7, Category::count()); // katalog se uvek uvozi ceo
    }

    public function test_since_updates_changed_listings_after_full_import(): void
    {
        $this->import()->assertSuccessful();

        $this->wp()->table('posts')->where('ID', 5001)->update([
            'post_title' => 'Audi A4 - snižena cena',
            'post_modified' => '2026-11-01 10:00:00',
            'post_modified_gmt' => '2026-11-01 09:00:00',
        ]);
        $this->wp()->table('postmeta')->where('post_id', 5001)->where('meta_key', 'listivo_130_listivo_435')->update(['meta_value' => '8500']);
        $this->wp()->table('posts')->where('ID', 5009)->update(['post_status' => 'trash', 'post_modified_gmt' => '2026-11-01 09:00:00']);

        $this->import(['--since' => '2026-11-01'])->assertSuccessful();

        $audi = Listing::find(5001);
        $this->assertSame('Audi A4 - snižena cena', $audi->title);
        $this->assertSame('8500.00', $audi->price);
        $this->assertSame(2, $audi->images()->count());
        $this->assertSoftDeleted('listings', ['id' => 5009]);
        $this->assertContains('/oglas/volkswagen-golf-7/', DB::table('gone_urls')->pluck('path')->all());
        $this->assertSame(6, User::count());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->import(['--dry-run' => true])
            ->expectsOutputToContain('Proba uvoza')
            ->assertSuccessful();

        $this->assertSame(0, User::count());
        $this->assertSame(0, Listing::withTrashed()->count());
        $this->assertSame(0, DB::table('gone_urls')->count());
        Storage::disk('public')->assertMissing('oglasi/5001/6001-audi-1.jpg');
    }

    public function test_only_option_limits_the_import(): void
    {
        $this->import(['--only' => 'categories,locations'])->assertSuccessful();

        $this->assertSame(7, Category::count());
        $this->assertSame(4, DB::table('locations')->count());
        $this->assertSame(0, User::count());
        $this->assertSame(0, Listing::count());

        $this->import(['--only' => 'users,fields,listings'])->assertSuccessful();
        $this->assertSame(7, Listing::count());
    }

    public function test_invalid_options_are_rejected(): void
    {
        $this->artisan('uvoz:wordpress', ['--only' => 'oglasi'])
            ->expectsOutputToContain('Nepoznat deo za --only')
            ->assertExitCode(2);

        $this->artisan('uvoz:wordpress', ['--uploads' => '/nema/ovog/foldera'])
            ->expectsOutputToContain('Folder sa slikama ne postoji')
            ->assertExitCode(2);

        $this->artisan('uvoz:wordpress', ['--since' => 'nije-datum'])
            ->expectsOutputToContain('Neispravan datum')
            ->assertExitCode(2);
    }

    public function test_report_file_is_written(): void
    {
        $this->import()->expectsOutputToContain('Izveštaj:')->assertSuccessful();
        $this->import(['--dry-run' => true])->assertSuccessful();

        $files = File::files($this->reportDir);
        $this->assertCount(2, $files);
        $this->assertMatchesRegularExpression('/^izvestaj-\d{4}-\d{2}-\d{2}-\d{6}(-2)?\.txt$/', $files[0]->getFilename());

        $text = File::get($files[0]->getPathname());
        $this->assertStringContainsString('Oglasi', $text);
        $this->assertStringContainsString('#5012 oglas-obrisanog-korisnika: autor #999 nije uvezen', $text);
        $this->assertStringContainsString('#5013 oglas-bez-kategorije: nema kategoriju', $text);
        $this->assertStringContainsString('#80 kredit: spam nalog', $text);
        $this->assertStringContainsString('#5 pera: neispravan telefon „12345”', $text);
        $this->assertStringContainsString('listivo_1910 → marka_automobila', $text);
        $this->assertStringContainsString('WooCommerce narudžbine: 1', $text);
    }

    public function test_existing_non_wordpress_user_keeps_its_id(): void
    {
        DB::table('users')->insert(['id' => 1, 'name' => 'Admin', 'email' => 'admin@loglasi.rs', 'password' => 'x', 'slug' => 'admin', 'created_at' => now(), 'updated_at' => now()]);

        $this->import()->assertSuccessful();

        $srdjan = User::where('wp_id', 1)->first();
        $this->assertNotSame(1, $srdjan->id);
        $this->assertSame('srdjan', $srdjan->slug);
        $this->assertSame('admin', User::find(1)->slug);
        $this->assertSame(User::where('wp_id', 2)->value('id'), Listing::find(5001)->user_id);
    }
}
