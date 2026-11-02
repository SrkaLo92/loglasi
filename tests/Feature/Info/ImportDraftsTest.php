<?php

namespace Tests\Feature\Info;

use App\Models\InfoPharmacyDuty;
use App\Models\InfoPost;
use App\Models\InfoServiceNotice;
use Tests\TestCase;

class ImportDraftsTest extends TestCase
{
    public function test_imports_posts_notices_and_pharmacy_and_skips_duplicates(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'drafts');
        file_put_contents($file, json_encode([
            ['title' => 'Počinje sezona klizanja', 'summary' => 'Klizalište radi od petka.', 'topic' => 'sport', 'source_name' => 'Grad Loznica', 'source_url' => 'https://loznica.rs/vest/1'],
            ['title' => 'Isti link', 'summary' => 'Duplikat.', 'source_name' => 'Grad Loznica', 'source_url' => 'https://loznica.rs/vest/1/'],
            ['kind' => 'water', 'place' => 'Klupci', 'summary' => 'Bez vode zbog radova.', 'starts_at' => now()->addDay()->toDateTimeString(), 'source_name' => 'Vodovod', 'source_url' => 'https://vodovod.example/1'],
            ['kind' => 'pharmacy', 'title' => 'Apoteka Centar', 'date' => today()->toDateString(), 'address' => 'Glavna 1', 'source_name' => 'Apoteka Loznica', 'source_url' => 'https://apoteka.example/'],
            ['title' => 'Bez izvora', 'summary' => 'Nevažeće.'],
        ]));

        $this->artisan('info:import-drafts', ['file' => $file, '--publish' => true])
            ->expectsOutput('Added 3, duplicates 1, invalid 1.')->assertSuccessful();

        $this->assertSame('published', InfoPost::query()->sole()->status);
        $this->assertTrue(InfoServiceNotice::query()->sole()->published);
        $this->assertSame('Apoteka Centar', InfoPharmacyDuty::today()?->name);
        unlink($file);
    }
}
