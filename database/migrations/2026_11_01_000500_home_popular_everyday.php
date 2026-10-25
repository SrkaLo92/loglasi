<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Popularno on the home page: everyday categories (phones, flats, furniture, appliances) instead of car parts
 * and tractors. Only a saved value that is still the old default is changed, so an admin's own list stays.
 */
return new class extends Migration
{
    public const OLD = 'auto-delovi,stanovi-iznajmljivanje,mobilni-telefoni,namestaj,traktori,bela-tehnika-i-kucni-aparati,alati-i-orudja,kucni-ljubimci';

    public const NEW = 'mobilni-telefoni,stanovi-prodaja,stanovi-iznajmljivanje,namestaj,bela-tehnika-i-kucni-aparati,kompjuteri-laptop-i-tablet,tv-i-video,moda';

    public function up(): void
    {
        $this->apply(self::OLD, self::NEW);
    }

    public function down(): void
    {
        $this->apply(self::NEW, self::OLD);
    }

    private function apply(string $from, string $to): void
    {
        DB::table('settings')->where('key', 'home.popular_categories')->where('value', json_encode($from))
            ->update(['value' => json_encode($to), 'updated_at' => now()]);
    }
};
