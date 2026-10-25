<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Price list of 25 Oct 2026: 10 free active listings, Plus prodavac, Brza prodaja, Vizitka for firms,
 * Business Start 50 listings and 3 bumps, Business Pro unlimited; +30 oglasa and Istaknut 30 dana retired.
 * Values already changed in the admin are kept (updates only touch the old defaults).
 */
return new class extends Migration
{
    public const RETIRED = ['listing_slots_30', 'featured_30'];

    public function up(): void
    {
        // Plus prodavac terms (several purchases follow each other).
        Schema::create('plus_seller_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamps();
            $table->index(['user_id', 'ends_at']);
        });

        $now = now();
        foreach (require database_path('seeders/data/price-list-2026-10-25.php') as $product) {
            DB::table('products')->insertOrIgnore($product + ['is_active' => true, 'display_only' => false, 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('products')->whereIn('code', self::RETIRED)->update(['is_active' => false, 'display_only' => false, 'updated_at' => $now]);

        DB::table('products')->where('code', 'like', 'business_start_%')->where('listing_limit', 20)->update(['listing_limit' => 50, 'updated_at' => $now]);
        DB::table('products')->where('code', 'like', 'business_pro_%')->where('listing_limit', 100)->update(['listing_limit' => null, 'updated_at' => $now]);
        DB::table('products')->where('code', 'business_start_monthly')->where('description', 'like', 'Do 20 aktivnih oglasa%')->update([
            'description' => "Do 50 aktivnih oglasa\nProfil firme sa logom i oznaka „Proverena firma”\n3 podizanja mesečno\nStatistika pregleda i direktan kontakt",
            'updated_at' => $now,
        ]);
        DB::table('products')->where('code', 'business_pro_monthly')->where('description', 'like', 'Do 100 aktivnih oglasa%')->update([
            'description' => "Neograničeno oglasa\nSve iz Start paketa\n10 podizanja i 2 isticanja mesečno\nUvoz oglasa i napredna statistika\n1 objava u nedeljnom pregledu na mrežama mesečno",
            'updated_at' => $now,
        ]);
        DB::table('products')->where('code', 'business_max_monthly')->where('description', 'like', 'Neograničeno oglasa%')->where('description', 'not like', '%mrežama%')->update([
            'description' => "Neograničeno oglasa\nSve iz Pro paketa\n20 podizanja, 5 isticanja i 1 Premium XL mesečno\nCSV/XML uvoz i prioritetna podrška\n1 posebna objava na mrežama mesečno",
            'updated_at' => $now,
        ]);

        // 10 free active listings, unless the admin already set another number than the old 5.
        $limit = DB::table('settings')->where('key', 'private_active_limit')->value('value');
        if ($limit !== null && (int) json_decode((string) $limit) === 5) {
            DB::table('settings')->where('key', 'private_active_limit')->update(['value' => json_encode(10), 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        DB::table('products')->whereIn('code', array_column(require database_path('seeders/data/price-list-2026-10-25.php'), 'code'))->delete();
        DB::table('products')->whereIn('code', self::RETIRED)->update(['is_active' => true]);
        Schema::dropIfExists('plus_seller_terms');
    }
};
