<?php

namespace Tests\Feature\Firms;

use App\Enums\AccountType;
use App\Models\Category;
use App\Models\Firm;
use App\Models\Listing;
use App\Models\Location;
use App\Models\User;
use App\Services\Catalog;
use App\Support\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Category::create(['name' => 'Usluge', 'slug' => 'usluge']);
        Location::create(['name' => 'Loznica', 'slug' => 'loznica']);
        app(Catalog::class)->flush();
        Settings::set('moderation', 'none');
    }

    private function firmUser(): User
    {
        $user = User::factory()->phoneVerified()->create(['account_type' => AccountType::Firm, 'created_at' => now()->subMonth()]);
        Firm::factory()->package('pro')->for($user)->create();

        return $user->load('firm');
    }

    private function table(): UploadedFile
    {
        $path = sys_get_temp_dir().'/'.uniqid('uvoz', true).'.csv';
        file_put_contents($path, "naslov;kategorija;cena;opis;mesto\n"
            ."Krečenje stanova;Usluge;3000;Krečim stanove i kuće, povoljno.;Loznica\n"
            ."K;Usluge;;Prekratak naslov.;Loznica\n");

        return new UploadedFile($path, 'oglasi.csv', 'text/csv', null, true);
    }

    public function test_guest_and_private_user_cannot_open_the_import(): void
    {
        $this->get('/moj-nalog/firma/uvoz-oglasa/')->assertRedirect(url_slash('login-registracija'));

        $this->actingAs(User::factory()->create())->get('/moj-nalog/firma/uvoz-oglasa/')->assertForbidden();
    }

    public function test_preview_shows_the_rows_without_saving(): void
    {
        $user = $this->firmUser();

        $this->actingAs($user)
            ->post('/moj-nalog/firma/uvoz-oglasa/pregled/', ['tabela' => $this->table()])
            ->assertOk()
            ->assertSee('Pregled pre uvoza')
            ->assertSee('Krečenje stanova')
            ->assertSee('Spremno');

        $this->assertSame(0, Listing::count());
    }

    public function test_import_creates_the_listings_and_reports_the_rest(): void
    {
        $user = $this->firmUser();

        $this->actingAs($user)
            ->post('/moj-nalog/firma/uvoz-oglasa/', ['tabela' => $this->table(), 'images' => '0'])
            ->assertOk()
            ->assertSee('Uvezeno oglasa:')
            ->assertSee('Naslov je prazan ili prekratak', false);

        $this->assertSame(1, Listing::count());
        $this->assertSame($user->id, Listing::query()->firstOrFail()->user_id);
    }

    public function test_a_file_that_is_not_a_table_is_refused(): void
    {
        $user = $this->firmUser();

        $this->actingAs($user)
            ->post('/moj-nalog/firma/uvoz-oglasa/pregled/', ['tabela' => UploadedFile::fake()->create('slika.exe', 10)])
            ->assertSessionHasErrors('tabela');
    }
}
