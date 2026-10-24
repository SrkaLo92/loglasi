<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Price list of 24 Oct 2026: products that are shown on /cenovnik/ before they can be bought.
 * display_only: listed with its price, not sold yet (is_active stays false until payments supports it).
 * Only missing codes are inserted; prices already edited in the admin are kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('display_only')->default(false)->after('is_active');
        });

        $now = now();
        foreach (self::products() as $product) {
            DB::table('products')->insertOrIgnore($product + [
                'is_active' => false,
                'display_only' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // New prices of existing products, unless they were already changed in the admin.
        DB::table('products')->where('code', 'bump')->where('price_rsd', 50)->update(['price_rsd' => 49, 'updated_at' => $now]);
        DB::table('products')->where('code', 'featured_7')->where('price_rsd', 300)->update(['price_rsd' => 199, 'updated_at' => $now]);
    }

    public function down(): void
    {
        DB::table('products')->whereIn('code', array_column(self::products(), 'code'))->delete();
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('display_only');
        });
    }

    /** @return list<array<string, mixed>> */
    public static function products(): array
    {
        return require database_path('seeders/data/price-list-2026-10-24.php');
    }
};
