<?php

namespace Tests\Feature\Posting;

use App\Enums\Currency;
use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Services\Admin\ListingImport;
use Illuminate\Support\Facades\Http;

class ListingImportTest extends PostingTestCase
{
    private function table(string $content, string $name = 'oglasi.csv'): array
    {
        $path = sys_get_temp_dir().'/'.uniqid('import', true).'-'.$name;
        file_put_contents($path, $content);

        return [$path, $name];
    }

    public function test_imports_rows_and_reports_the_bad_ones(): void
    {
        $user = $this->user();
        [$path, $name] = $this->table(
            "naslov;kategorija;cena;valuta;opis;mesto;godiste;stanje\n"
            ."Golf 7 1.6 TDI;Automobili;8.900;EUR;Očuvan, prvi vlasnik, servisna knjiga.;Loznica;2015;polovno\n"
            ."Krečenje stanova;usluge;;;Krečim stanove i kuće, povoljno.;Loznica;;\n"
            ."X;Automobili;8900;EUR;Prekratak naslov i nema godište.;Loznica;;\n"
            ."Pasat 2.0 TDI;Nepostojeca;5000;EUR;Opis koji je dovoljno dug za oglas.;Loznica;2012;\n"
        );

        $report = app(ListingImport::class)->import($path, $name, $user, withImages: false);

        $this->assertSame(2, $report['created'], json_encode($report['rows'], JSON_UNESCAPED_UNICODE));
        $this->assertSame(2, $report['failed']);

        $golf = Listing::query()->where('title', 'Golf 7 1.6 TDI')->firstOrFail();
        $this->assertSame(ListingStatus::Published, $golf->status);
        $this->assertSame($this->cars->id, $golf->category_id);
        $this->assertSame($this->loznica->id, $golf->location_id);
        $this->assertSame(8900.0, (float) $golf->price);
        $this->assertSame(Currency::EUR, $golf->currency);
        $this->assertEqualsCanonicalizing(['2015', 'polovno'], $golf->fieldValues()->pluck('value')->all());

        $this->assertNull(Listing::query()->where('title', 'Krečenje stanova')->firstOrFail()->price);

        $errors = collect($report['rows'])->where('line', 4)->first()['errors'];
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Naslov', implode(' ', $errors));
        $this->assertStringContainsString('Kategorija', implode(' ', collect($report['rows'])->where('line', 5)->first()['errors']));
    }

    public function test_preview_changes_nothing_and_names_unknown_columns(): void
    {
        $user = $this->user();
        [$path, $name] = $this->table(
            "naslov,kategorija,opis,mesto,godiste,sifra artikla\n"
            ."Golf 7,Automobili,Očuvan automobil u dobrom stanju.,Loznica,2015,A-1\n"
        );

        $preview = app(ListingImport::class)->preview($path, $name, $user);

        $this->assertSame(0, Listing::count());
        $this->assertSame(['sifra artikla'], $preview['unknown']);
        $this->assertSame([], $preview['rows'][0]['errors']);
        $this->assertSame('Automobili', $preview['rows'][0]['category']);
    }

    public function test_photos_come_from_the_urls_in_the_table(): void
    {
        $user = $this->user();
        Http::fake([
            '*a.jpg' => Http::response(file_get_contents(base_path('tests/Fixtures/images/stripes.jpg'))),
            '*' => Http::response(file_get_contents(base_path('tests/Fixtures/images/photo.webp'))),
        ]);
        [$path, $name] = $this->table(
            "naslov;kategorija;opis;mesto;slike\n"
            ."Krečenje stanova;Usluge;Krečim stanove i kuće, povoljno.;Loznica;https://primer.rs/a.jpg https://primer.rs/b.jpg\n"
        );

        app(ListingImport::class)->import($path, $name, $user);

        $this->assertSame(2, Listing::query()->firstOrFail()->images()->count());
    }

    public function test_reads_an_excel_table(): void
    {
        $user = $this->user();
        $path = sys_get_temp_dir().'/'.uniqid('import', true).'.xlsx';
        $this->writeXlsx($path, [
            ['Naslov', 'Kategorija', 'Opis', 'Mesto'],
            ['Krečenje stanova', 'Usluge', 'Krečim stanove i kuće, povoljno.', 'Loznica'],
        ]);

        $report = app(ListingImport::class)->import($path, 'oglasi.xlsx', $user, withImages: false);

        $this->assertSame(1, $report['created']);
        $this->assertSame('Krečenje stanova', Listing::query()->firstOrFail()->title);
    }

    /** Minimal XLSX with inline strings, enough to test the reader. */
    private function writeXlsx(string $path, array $rows): void
    {
        $xml = '';
        foreach ($rows as $r => $row) {
            $cells = '';
            foreach ($row as $c => $value) {
                $cells .= '<c r="'.chr(65 + $c).($r + 1).'" t="inlineStr"><is><t>'.htmlspecialchars((string) $value, ENT_XML1).'</t></is></c>';
            }
            $xml .= '<row r="'.($r + 1).'">'.$cells.'</row>';
        }

        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="List1" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$xml.'</sheetData></worksheet>');
        $zip->close();
    }
}
