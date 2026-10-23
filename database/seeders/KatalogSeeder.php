<?php

namespace Database\Seeders;

use App\Enums\FieldFilter;
use App\Enums\FieldType;
use App\Models\Category;
use App\Models\Field;
use App\Models\Location;
use App\Services\Catalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Kategorije, mesta i dodatna polja za lokalni razvoj. Pravi podaci dolaze uvozom
 * iz WordPress-a; slugovi glavnih kategorija su isti kao na sajtu (automobili,
 * nekretnine, mobilni-telefoni, poslovi, usluge, besplatne-stvari). Može da se pokrene više puta.
 */
class KatalogSeeder extends Seeder
{
    /** Naziv => potkategorije. */
    private const CATEGORIES = [
        'Automobili' => ['Hečbek', 'Karavan', 'Limuzina', 'Džip / SUV', 'Kombi'],
        'Auto delovi i oprema' => [],
        'Motocikli' => [],
        'Kamioni' => [],
        'Bicikli i delovi' => [],
        'Nekretnine' => ['Stanovi', 'Kuće', 'Placevi', 'Poslovni prostor', 'Vikendice'],
        'Mobilni telefoni' => [],
        'Elektronika' => ['Računari i laptopovi', 'TV i audio', 'Konzole i igrice', 'Foto oprema'],
        'Kućni aparati' => [],
        'Nameštaj' => [],
        'Kuća i bašta' => [],
        'Građevinarstvo' => [],
        'Alati i mašine' => [],
        'Poljoprivreda' => ['Mehanizacija', 'Stoka', 'Voće i povrće', 'Ogrev'],
        'Moda' => ['Ženska odeća', 'Muška odeća', 'Obuća', 'Satovi i nakit'],
        'Za decu' => [],
        'Sport i rekreacija' => [],
        'Muzički instrumenti' => [],
        'Kućni ljubimci' => [],
        'Knjige' => [],
        'Usluge' => [],
        'Poslovi' => [],
        'Besplatne stvari' => [],
        'Ostalo' => [],
    ];

    /** Loznica i okolina. */
    private const LOCATIONS = [
        'Loznica', 'Banja Koviljača', 'Lešnica', 'Jadranska Lešnica', 'Tršić', 'Klupci', 'Lipnica',
        'Lozničko Polje', 'Runjani', 'Zajača', 'Voćnjak', 'Brezjak', 'Tekeriš', 'Korenita',
        'Jarebice', 'Draginac', 'Mali Zvornik', 'Krupanj', 'Ljubovija',
    ];

    public function run(): void
    {
        $sort = 0;
        foreach (self::CATEGORIES as $name => $children) {
            $parent = $this->category($name, null, ++$sort);
            foreach ($children as $i => $child) {
                $this->category($child, $parent->id, $i + 1);
            }
        }

        foreach (self::LOCATIONS as $name) {
            Location::updateOrCreate(['slug' => $this->slug($name)], ['name' => $name]);
        }

        $this->fields();

        app(Catalog::class)->flush();
    }

    private function fields(): void
    {
        $f = fn (string $key, string $label, FieldType $type, FieldFilter $filter, array $options = [], ?string $unit = null, int $sort = 0) => Field::updateOrCreate(['key' => $key], [
            'label' => $label,
            'type' => $type,
            'filter' => $filter,
            'unit' => $unit,
            'options' => $options ? $this->options($options) : null,
            'sort' => $sort,
        ]);

        $stanje = $f('stanje', 'Stanje', FieldType::Select, FieldFilter::Multi, ['novo' => 'Novo', 'polovno' => 'Polovno', 'osteceno' => 'Oštećeno'], null, 90);
        $vrsta = $f('vrsta_oglasa', 'Vrsta oglasa', FieldType::Select, FieldFilter::Select, ['prodajem' => 'Prodajem', 'kupujem' => 'Kupujem', 'izdajem' => 'Izdajem'], null, 91);
        $zamena = $f('moguca_zamena', 'Moguća zamena', FieldType::Checkbox, FieldFilter::Select, [], null, 92);
        $dostava = $f('dostava', 'Dostava', FieldType::Checkbox, FieldFilter::Select, [], null, 93);

        $marka = $f('marka', 'Marka', FieldType::Select, FieldFilter::Multi, [
            'Alfa Romeo', 'Audi', 'BMW', 'Chevrolet', 'Citroën', 'Dacia', 'Fiat', 'Ford', 'Honda', 'Hyundai',
            'Kia', 'Lada', 'Mazda', 'Mercedes-Benz', 'Mitsubishi', 'Nissan', 'Opel', 'Peugeot', 'Renault',
            'Seat', 'Škoda', 'Suzuki', 'Toyota', 'Volkswagen', 'Volvo', 'Zastava', 'Ostalo',
        ], null, 1);
        $model = $f('model', 'Model', FieldType::Text, FieldFilter::None, [], null, 2);
        $godiste = $f('godiste', 'Godište', FieldType::Year, FieldFilter::Range, [], null, 3);
        $km = $f('kilometraza', 'Kilometraža', FieldType::Number, FieldFilter::Range, [], 'km', 4);
        $gorivo = $f('gorivo', 'Gorivo', FieldType::Select, FieldFilter::Multi, [
            'benzin' => 'Benzin', 'dizel' => 'Dizel', 'benzin-gas' => 'Benzin + gas (TNG)',
            'metan' => 'Metan (CNG)', 'hibrid' => 'Hibrid', 'elektricni' => 'Električni',
        ], null, 5);
        $menjac = $f('menjac', 'Menjač', FieldType::Select, FieldFilter::Select, ['manuelni' => 'Manuelni', 'automatski' => 'Automatski'], null, 6);
        $kubikaza = $f('kubikaza', 'Kubikaža', FieldType::Number, FieldFilter::Range, [], 'cm³', 7);

        $kvadratura = $f('kvadratura', 'Kvadratura', FieldType::Number, FieldFilter::Range, [], 'm²', 1);
        $sobe = $f('sobe', 'Broj soba', FieldType::Select, FieldFilter::Multi, [
            'garsonjera' => 'Garsonjera', '1' => 'Jednosoban', '1.5' => 'Jednoiposoban', '2' => 'Dvosoban',
            '2.5' => 'Dvoiposoban', '3' => 'Trosoban', '3.5' => 'Troiposoban', '4+' => 'Četvorosoban i veći',
        ], null, 2);
        $grejanje = $f('grejanje', 'Grejanje', FieldType::Select, FieldFilter::Multi, [
            'centralno' => 'Centralno', 'etazno' => 'Etažno', 'struja' => 'Struja', 'drva' => 'Drva', 'gas' => 'Gas', 'ta-pec' => 'TA peć',
        ], null, 3);
        $sprat = $f('sprat', 'Sprat', FieldType::Text, FieldFilter::None, [], null, 4);

        $markaTelefona = $f('marka_telefona', 'Marka telefona', FieldType::Select, FieldFilter::Multi, ['Apple', 'Samsung', 'Xiaomi', 'Huawei', 'Motorola', 'Nokia', 'Ostalo'], null, 1);
        $memorija = $f('memorija', 'Memorija', FieldType::Select, FieldFilter::Multi, ['32' => '32 GB', '64' => '64 GB', '128' => '128 GB', '256' => '256 GB', '512' => '512 GB', '1024' => '1 TB'], null, 2);

        $radnoVreme = $f('radno_vreme', 'Radno vreme', FieldType::Select, FieldFilter::Select, ['puno' => 'Puno radno vreme', 'skraceno' => 'Skraćeno', 'sezonski' => 'Sezonski posao'], null, 1);

        $roba = [$stanje, $vrsta, $zamena, $dostava];
        $bezStanja = ['Nekretnine', 'Usluge', 'Poslovi', 'Besplatne stvari', 'Kućni ljubimci'];

        foreach (array_keys(self::CATEGORIES) as $name) {
            if (! in_array($name, $bezStanja, true)) {
                $this->attach($name, $roba, [], 90);
            }
        }

        $this->attach('Automobili', [$marka, $model, $godiste, $km, $gorivo, $menjac, $kubikaza], ['marka', 'godiste']);
        $this->attach('Motocikli', [$godiste, $km, $kubikaza]);
        $this->attach('Kamioni', [$godiste, $km, $gorivo]);
        $this->attach('Nekretnine', [$kvadratura, $grejanje, $vrsta], ['kvadratura']);
        $this->attach('Stanovi', [$sobe, $sprat]);
        $this->attach('Kuće', [$sobe]);
        $this->attach('Mobilni telefoni', [$markaTelefona, $memorija]);
        $this->attach('Poslovi', [$radnoVreme]);
    }

    /**
     * @param  list<Field>  $fields
     * @param  list<string>  $required  ključevi obaveznih polja
     * @param  int  $sortFrom  zajednička polja (stanje, vrsta...) idu posle posebnih
     */
    private function attach(string $categoryName, array $fields, array $required = [], int $sortFrom = 0): void
    {
        $category = Category::where('slug', $this->slug($categoryName))->firstOrFail();

        $sync = [];
        foreach ($fields as $i => $field) {
            $sync[$field->id] = ['sort' => $sortFrom + $i + 1, 'required' => in_array($field->key, $required, true)];
        }

        $category->fields()->syncWithoutDetaching($sync);
    }

    private function category(string $name, ?int $parentId, int $sort): Category
    {
        return Category::updateOrCreate(
            ['slug' => $this->slug($name)],
            ['name' => $name, 'parent_id' => $parentId, 'sort' => $sort, 'is_active' => true],
        );
    }

    /**
     * ["Novo", ...] ili ["novo" => "Novo", ...] u [{value, label}].
     *
     * @return list<array{value: string, label: string}>
     */
    private function options(array $options): array
    {
        $list = array_is_list($options);
        $out = [];
        foreach ($options as $value => $label) {
            $out[] = ['value' => $list ? $this->slug($label) : (string) $value, 'label' => $label];
        }

        return $out;
    }

    /** Slug bez dijakritika: "Građevinarstvo" => "gradjevinarstvo", "Kuće" => "kuce". */
    private function slug(string $text): string
    {
        return Str::slug(strtr($text, ['đ' => 'dj', 'Đ' => 'Dj']));
    }
}
