<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Price list of 24 Oct 2026, the sellable part: promotion periods on listings, bump packs,
 * extra listing slots, the search rank of promotions, and the products switched on for sale.
 */
return new class extends Migration
{
    /** Products that can be bought from now on (the rest of the price list stays display-only). */
    public const SELLABLE = [
        'listing_slots_3', 'listing_slots_10', 'listing_slots_30', 'bump_10', 'priority_3', 'top_7',
        'premium_xl_7', 'premium_xl_home_7',
        'business_start_monthly', 'business_start_yearly', 'business_pro_monthly', 'business_pro_yearly',
        'business_max_monthly', 'business_max_yearly',
    ];

    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->timestamp('priority_until')->nullable()->after('featured_until');   // priority listing
            $table->timestamp('top_until')->nullable()->after('priority_until');        // TOP listing
            $table->timestamp('premium_xl_until')->nullable()->after('top_until');      // bigger card in lists
            $table->timestamp('premium_home_until')->nullable()->after('premium_xl_until'); // also on the home page
            $table->index('premium_home_until');
        });

        // Bumps bought as a pack (Paket 10 podizanja), used one at a time.
        Schema::create('bump_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('granted');
            $table->unsignedSmallInteger('used')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'id']);
        });

        // Extra active listings for 30 days (+3 / +10 / +30), on top of private_active_limit.
        Schema::create('listing_slot_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('slots');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamps();
            $table->index(['user_id', 'ends_at']);
        });

        // Search rank of a listing: 0 normal, 1 highlighted, 2 priority, 3 TOP (same indexes, DESC).
        // featured_until becomes the moment the rank next changes.
        Schema::table('listing_search', function (Blueprint $table) {
            $table->unsignedTinyInteger('featured')->default(0)->change();
        });

        DB::table('products')->whereIn('code', self::SELLABLE)->update(['is_active' => true, 'display_only' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('products')->whereIn('code', self::SELLABLE)->update(['is_active' => false, 'display_only' => true]);
        DB::table('listing_search')->where('featured', '>', 1)->update(['featured' => 1]);
        Schema::table('listing_search', function (Blueprint $table) {
            $table->boolean('featured')->default(false)->change();
        });
        Schema::dropIfExists('listing_slot_grants');
        Schema::dropIfExists('bump_credits');
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex(['premium_home_until']);
            $table->dropColumn(['priority_until', 'top_until', 'premium_xl_until', 'premium_home_until']);
        });
    }
};
