<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Clearer price list descriptions (Cenovnik copy review). Only rows that still have the old default text
 * are changed, so descriptions edited in the admin stay. Prices are not touched.
 */
return new class extends Migration
{
    /** code => [old, new] */
    public const DESCRIPTIONS = [
        'bump_10' => ['Deset podizanja koja koristite kad vam odgovara.', '10 podizanja na vrh liste, za bilo koji svoj oglas, kad vama odgovara.'],
        'priority_3' => ['Kratko i jako: oglas je među prvima u svojoj kategoriji.', 'Oglas stoji među prvima u svojoj kategoriji 3 dana.'],
        'top_7' => ['Na samom vrhu kategorije i pretrage, iznad isticanih oglasa.', 'Oglas je na samom vrhu kategorije i pretrage, iznad istaknutih oglasa.'],
        'premium_xl_7' => ['Veća kartica u listi: veća slika, veći naslov i dugme za kontakt. Nije baner.', 'Veća kartica u listi oglasa: veća slika, veći naslov i dugme za poziv.'],
        'premium_xl_home_7' => ['Premium XL kartica u listi i na početnoj strani.', 'Premium XL kartica u listi oglasa i na početnoj strani.'],
        'quick_sale_7' => ['Istaknut oglas 7 dana i 3 podizanja koja koristite kad želite.', 'Oglas je istaknut 7 dana, uz 3 podizanja na vrh kad vi izaberete.'],
    ];

    public const PLUS_OLD = 'Automatska obnova oglasa na 15 dana';

    public const PLUS_NEW = 'Oglasi se sami obnavljaju na svakih 15 dana';

    public function up(): void
    {
        $this->apply(0, 1);
    }

    public function down(): void
    {
        $this->apply(1, 0);
    }

    private function apply(int $from, int $to): void
    {
        $now = now();
        foreach (self::DESCRIPTIONS as $code => $texts) {
            DB::table('products')->where('code', $code)->where('description', $texts[$from])->update(['description' => $texts[$to], 'updated_at' => $now]);
        }

        [$old, $new] = $from === 0 ? [self::PLUS_OLD, self::PLUS_NEW] : [self::PLUS_NEW, self::PLUS_OLD];
        $plus = DB::table('products')->where('code', 'plus_seller_30')->value('description');
        if (is_string($plus) && str_contains($plus, $old)) {
            DB::table('products')->where('code', 'plus_seller_30')->update(['description' => str_replace($old, $new, $plus), 'updated_at' => $now]);
        }
    }
};
