<?php

namespace Tests\Feature\Info;

use App\Models\InfoBusiness;
use Tests\TestCase;

class ImportBusinessesTest extends TestCase
{
    public function test_imports_csv_once(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'firme');
        file_put_contents($file, "\u{FEFF}Naziv,Kategorija,Adresa,Telefon,Web,Izvor\n"
            ."Pekara Klas,Pekare,\"Glavna 1, Loznica\",015 123 456,https://klas.rs,Google\n"
            ."Servis Auto,Servisi,,064 111 222,klas.rs,FB\n");

        $this->artisan('info:import-businesses', ['file' => $file, '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, InfoBusiness::query()->count());

        $this->artisan('info:import-businesses', ['file' => $file])->assertSuccessful();
        $this->artisan('info:import-businesses', ['file' => $file])->assertSuccessful();

        $this->assertSame(2, InfoBusiness::query()->count());
        $klas = InfoBusiness::query()->where('name', 'Pekara Klas')->sole();
        $this->assertSame('Loznica', $klas->place);
        $this->assertSame('listed', $klas->status);
        $this->assertNull(InfoBusiness::query()->where('name', 'Servis Auto')->sole()->website);

        unlink($file);
    }

    public function test_municipality_sets_city_and_filters_the_directory(): void
    {
        config(['info.enabled' => true]);
        $file = tempnam(sys_get_temp_dir(), 'firme');
        file_put_contents($file, "Naziv,Kategorija,Adresa,Mesto,Opština,Izvor\n"
            ."Kafe Drina,Ugostiteljstvo,Glavna 2,Mali Zvornik,Mali Zvornik,APR\n"
            ."Pekara Sava,Pekare,Masarikova 3,Šabac,Šabac,APR\n");
        $this->artisan('info:import-businesses', ['file' => $file])->assertSuccessful();
        unlink($file);

        $this->assertSame('loznica', InfoBusiness::query()->where('name', 'Kafe Drina')->sole()->city);
        $this->assertSame('sabac', InfoBusiness::query()->where('name', 'Pekara Sava')->sole()->city);

        $this->get('http://'.config('info.domain').'/firme/?opstina='.rawurlencode('Šabac'))
            ->assertOk()->assertSee('Pekara Sava')->assertDontSee('Kafe Drina')->assertSee('Sve opštine');
    }
}
