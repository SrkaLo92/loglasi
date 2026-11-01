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
}
