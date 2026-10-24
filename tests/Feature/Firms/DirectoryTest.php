<?php

namespace Tests\Feature\Firms;

use App\Enums\AccountType;
use App\Enums\UserStatus;
use App\Models\Firm;
use App\Models\Listing;
use App\Models\Location;
use App\Models\User;
use App\Support\Firms\FirmDirectory;
use Tests\TestCase;

class DirectoryTest extends TestCase
{
    private function firm(array $firm = [], array $user = []): User
    {
        $account = User::factory()->create(['account_type' => AccountType::Firm] + $user);
        Firm::factory()->package('pro')->for($account)->create($firm);

        return $account->load('firm');
    }

    private function listings(User $user, int $count): void
    {
        Listing::factory()->count($count)->for($user)->create();
    }

    public function test_lists_only_firms_with_active_biznis_and_active_account(): void
    {
        $listed = $this->firm(['name' => 'Auto servis Drina']);
        $expired = $this->firm(['name' => 'Istekla firma', 'package_ends_at' => now()->subDay()]);
        $none = $this->firm(['name' => 'Bez paketa', 'package_ends_at' => null]);
        $blocked = $this->firm(['name' => 'Blokirana firma'], ['status' => UserStatus::Blocked]);
        $deleted = $this->firm(['name' => 'Obrisana firma']);
        $deleted->delete();
        $private = User::factory()->create();
        Firm::factory()->package('pro')->for($private)->create(['name' => 'Privatni sa redom']);

        $this->get('/firme/')
            ->assertOk()
            ->assertSee('Auto servis Drina')
            ->assertSee(url_slash('korisnik/'.$listed->slug), false)
            ->assertDontSee('Istekla firma')
            ->assertDontSee('Bez paketa')
            ->assertDontSee('Blokirana firma')
            ->assertDontSee('Obrisana firma')
            ->assertDontSee('Privatni sa redom');
    }

    public function test_first_30_firms_first_then_by_active_listings(): void
    {
        $few = $this->firm(['name' => 'Firma Dva']);
        $many = $this->firm(['name' => 'Firma Pet']);
        $free = User::factory()->create(['account_type' => AccountType::Firm]);
        Firm::factory()->freeYear()->for($free)->create(['name' => 'Firma Prva']);
        $this->listings($few, 2);
        $this->listings($many, 5);
        Listing::factory()->expired()->count(4)->for($few)->create(); // not active, not counted

        $names = array_column(FirmDirectory::all(), 'name');
        $this->assertSame(['Firma Prva', 'Firma Pet', 'Firma Dva'], $names);

        $this->get('/firme/')->assertOk()
            ->assertSeeInOrder(['Firma Prva', 'Firma Pet', '5 aktivnih oglasa', 'Firma Dva', '2 aktivna oglasa'])
            ->assertDontSee('Founder')->assertDontSee('Osnivačka');
    }

    public function test_filter_by_place_and_text_ignores_case_and_diacritics(): void
    {
        $banja = Location::factory()->create(['name' => 'Banja Koviljača', 'slug' => 'banja-koviljaca']);
        $loznica = Location::factory()->create(['name' => 'Loznica', 'slug' => 'loznica']);
        $this->firm(['name' => 'Vulkanizer Čeda', 'activity' => 'Vulkanizer', 'location_id' => $banja->id]);
        $this->firm(['name' => 'Pekara Žito', 'activity' => 'Pekara', 'location_id' => $loznica->id]);

        $this->get('/firme/?mesto=banja-koviljaca')->assertOk()
            ->assertSee('Vulkanizer Čeda')->assertDontSee('Pekara Žito')
            ->assertSee('u mestu Banja Koviljača')
            ->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->assertSee('<link rel="canonical" href="https://loglasi.rs/firme/">', false);

        $this->get('/firme/?keyword=ceda')->assertOk()->assertSee('Vulkanizer Čeda')->assertDontSee('Pekara Žito');
        $this->get('/firme/?keyword=ZITO')->assertOk()->assertSee('Pekara Žito')->assertDontSee('Vulkanizer Čeda');
        $this->get('/firme/?keyword=pekara&mesto=banja-koviljaca')->assertOk()->assertSee('Nema firmi za ovu pretragu.');
        $this->get('/firme/?keyword=<script>alert(1)</script>')->assertOk()->assertDontSee('<script>alert(1)', false);
    }

    public function test_seo_title_h1_canonical_and_item_list(): void
    {
        $user = $this->firm(['name' => 'Auto servis Drina', 'activity' => 'Auto servis']);

        $html = $this->get('/firme/')->assertOk()
            ->assertSee('<title>Firme iz Loznice – lokalne firme i usluge | loglasi.rs</title>', false)
            ->assertSee('<link rel="canonical" href="https://loglasi.rs/firme/">', false)
            ->assertSee('index, follow', false)
            ->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('>Firme iz Loznice</h1>', $html);
        $this->assertStringContainsString('"@type":"ItemList"', $html);
        $this->assertStringContainsString('"@type":"LocalBusiness"', $html);
        $this->assertStringContainsString('"url":"https://loglasi.rs/korisnik/'.$user->slug.'/"', $html);
        $this->assertStringContainsString('1 firma iz Loznice', $html);
    }

    public function test_empty_directory_is_noindex(): void
    {
        $this->get('/firme/')->assertOk()
            ->assertSee('Imenik firmi se tek puni.')
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_pagination_and_out_of_range_pages(): void
    {
        config(['firms.directory.per_page' => 2]);
        foreach (['A', 'B', 'C'] as $letter) {
            $this->firm(['name' => 'Firma '.$letter]);
        }

        $this->get('/firme/')->assertOk()->assertSee('Firma A')->assertSee('Firma B')->assertDontSee('Firma C')
            ->assertSee('firme/?page=2', false);
        $this->get('/firme/?page=2')->assertOk()->assertSee('Firma C')
            ->assertSee('<link rel="canonical" href="https://loglasi.rs/firme/?page=2">', false);
        $this->get('/firme/?page=3')->assertNotFound();
        $this->get('/firme/?page=abc')->assertNotFound();
    }

    public function test_cache_is_cleared_when_a_firm_or_account_changes(): void
    {
        $user = $this->firm(['name' => 'Stari naziv']);
        $this->get('/firme/')->assertSee('Stari naziv');

        $user->firm->update(['name' => 'Novi naziv']);
        $this->get('/firme/')->assertSee('Novi naziv')->assertDontSee('Stari naziv');

        $user->forceFill(['status' => UserStatus::Blocked])->save();
        $this->get('/firme/')->assertDontSee('Novi naziv');
    }

    public function test_without_trailing_slash_redirects(): void
    {
        $this->get('/firme')->assertMovedPermanently();
    }

    public function test_home_links_to_the_directory_with_its_count(): void
    {
        $this->firm(['name' => 'Firma sa paketom']);
        $this->firm(['name' => 'Firma bez paketa', 'package' => null, 'package_ends_at' => null]);

        $this->get('/')->assertOk()
            ->assertSee(url_slash('firme'), false)
            ->assertSee('Sve firme (1)');
    }
}
