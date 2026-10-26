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
 * Categories, places and extra fields for local development. The real data comes from the
 * WordPress import; the main category slugs are the same as on the site (automobili,
 * nekretnine, mobilni-telefoni, poslovi, usluge, besplatne-stvari). Can be run again.
 */
class CatalogSeeder extends Seeder
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

    /** Loznica and surroundings. */
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

        $condition = $f('stanje', 'Stanje', FieldType::Select, FieldFilter::Multi, ['novo' => 'Novo', 'polovno' => 'Polovno', 'osteceno' => 'Oštećeno'], null, 90);
        $kind = $f('vrsta_oglasa', 'Vrsta oglasa', FieldType::Select, FieldFilter::Select, ['prodajem' => 'Prodajem', 'kupujem' => 'Kupujem', 'izdajem' => 'Izdajem'], null, 91);
        $swap = $f('moguca_zamena', 'Moguća zamena', FieldType::Checkbox, FieldFilter::Select, [], null, 92);
        $delivery = $f('dostava', 'Dostava', FieldType::Checkbox, FieldFilter::Select, [], null, 93);

        $make = $f('marka', 'Marka', FieldType::Select, FieldFilter::Multi, [
            'Alfa Romeo', 'Audi', 'BMW', 'Chevrolet', 'Citroën', 'Dacia', 'Fiat', 'Ford', 'Honda', 'Hyundai',
            'Kia', 'Lada', 'Mazda', 'Mercedes-Benz', 'Mitsubishi', 'Nissan', 'Opel', 'Peugeot', 'Renault',
            'Seat', 'Škoda', 'Suzuki', 'Toyota', 'Volkswagen', 'Volvo', 'Zastava', 'Ostalo',
        ], null, 1);
        $model = $f('model', 'Model', FieldType::Text, FieldFilter::None, [], null, 2);
        $year = $f('godiste', 'Godište', FieldType::Year, FieldFilter::Range, [], null, 3);
        $km = $f('kilometraza', 'Kilometraža', FieldType::Number, FieldFilter::Range, [], 'km', 4);
        $fuel = $f('gorivo', 'Gorivo', FieldType::Select, FieldFilter::Multi, [
            'benzin' => 'Benzin', 'dizel' => 'Dizel', 'benzin-gas' => 'Benzin + gas (TNG)',
            'metan' => 'Metan (CNG)', 'hibrid' => 'Hibrid', 'elektricni' => 'Električni',
        ], null, 5);
        $gearbox = $f('menjac', 'Menjač', FieldType::Select, FieldFilter::Select, ['manuelni' => 'Manuelni', 'automatski' => 'Automatski'], null, 6);
        $engineSize = $f('kubikaza', 'Kubikaža', FieldType::Number, FieldFilter::Range, [], 'cm³', 7);

        $area = $f('kvadratura', 'Kvadratura', FieldType::Number, FieldFilter::Range, [], 'm²', 1);
        $rooms = $f('sobe', 'Broj soba', FieldType::Select, FieldFilter::Multi, [
            'garsonjera' => 'Garsonjera', '1' => 'Jednosoban', '1.5' => 'Jednoiposoban', '2' => 'Dvosoban',
            '2.5' => 'Dvoiposoban', '3' => 'Trosoban', '3.5' => 'Troiposoban', '4+' => 'Četvorosoban i veći',
        ], null, 2);
        $heating = $f('grejanje', 'Grejanje', FieldType::Select, FieldFilter::Multi, [
            'centralno' => 'Centralno', 'etazno' => 'Etažno', 'struja' => 'Struja', 'drva' => 'Drva', 'gas' => 'Gas', 'ta-pec' => 'TA peć',
        ], null, 3);
        $floor = $f('sprat', 'Sprat', FieldType::Text, FieldFilter::None, [], null, 4);

        $phoneMake = $f('marka_telefona', 'Marka telefona', FieldType::Select, FieldFilter::Multi, ['Apple', 'Samsung', 'Xiaomi', 'Huawei', 'Motorola', 'Nokia', 'Ostalo'], null, 1);
        $memory = $f('memorija', 'Memorija', FieldType::Select, FieldFilter::Multi, ['32' => '32 GB', '64' => '64 GB', '128' => '128 GB', '256' => '256 GB', '512' => '512 GB', '1024' => '1 TB'], null, 2);

        $workingHours = $f('radno_vreme', 'Radno vreme', FieldType::Select, FieldFilter::Select, ['puno' => 'Puno radno vreme', 'skraceno' => 'Skraćeno', 'sezonski' => 'Sezonski posao'], null, 1);

        $goods = [$condition, $kind, $swap, $delivery];
        $withoutCondition = ['Nekretnine', 'Usluge', 'Poslovi', 'Besplatne stvari', 'Kućni ljubimci'];

        foreach (array_keys(self::CATEGORIES) as $name) {
            if (! in_array($name, $withoutCondition, true)) {
                $this->attach($name, $goods, [], 90);
            }
        }

        $this->attach('Automobili', [$make, $model, $year, $km, $fuel, $gearbox, $engineSize], ['marka', 'godiste']);
        $this->attach('Motocikli', [$year, $km, $engineSize]);
        $this->attach('Kamioni', [$year, $km, $fuel]);
        $this->attach('Nekretnine', [$area, $heating, $kind], ['kvadratura']);
        $this->attach('Stanovi', [$rooms, $floor]);
        $this->attach('Kuće', [$rooms]);
        $this->attach('Mobilni telefoni', [$phoneMake, $memory]);
        $this->attach('Poslovi', [$workingHours]);
    }

    /**
     * @param  list<Field>  $fields
     * @param  list<string>  $required  keys of the required fields
     * @param  int  $sortFrom  shared fields (stanje, vrsta...) go after the specific ones
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
     * ["Novo", ...] or ["novo" => "Novo", ...] to [{value, label}].
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

    /** Slug without diacritics: "Građevinarstvo" => "gradjevinarstvo", "Kuće" => "kuce". */
    private function slug(string $text): string
    {
        return Str::slug(strtr($text, ['đ' => 'dj', 'Đ' => 'Dj']));
    }
}
