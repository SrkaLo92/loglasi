<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Search index: one narrow row per active listing (published, not deleted, not expired),
 * kept in sync by App\Services\SearchIndex. Every list and search reads ids from here
 * with an index that matches the filter and the sort, then loads only the page of listings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_search', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary(); // = listings.id
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('category_id');
            // Category path from the root: a filter on a category of depth N is cat{N} = id.
            $table->unsignedBigInteger('cat1');
            $table->unsignedBigInteger('cat2')->nullable();
            $table->unsignedBigInteger('cat3')->nullable();
            $table->unsignedBigInteger('location_id')->nullable();
            $table->boolean('is_firm')->default(false);
            $table->decimal('price_eur', 14, 2)->nullable();
            $table->boolean('no_price')->default(false); // "Dogovor", last when sorting by price
            $table->boolean('featured')->default(false); // featured_until in the future (refreshed every minute)
            $table->timestamp('featured_until')->nullable();
            $table->timestamp('sort_at'); // bumped_at, else published_at
            $table->timestamp('expires_at')->nullable();
            $table->text('search_text'); // title + description without diacritics (App\Support\Search\SearchText)

            $table->foreign('id')->references('id')->on('listings')->cascadeOnDelete();

            // Newest first (featured first), with each common filter in front.
            $table->index(['featured', 'sort_at', 'id'], 'ls_all');
            $table->index(['cat1', 'featured', 'sort_at', 'id'], 'ls_cat1');
            $table->index(['cat2', 'featured', 'sort_at', 'id'], 'ls_cat2');
            $table->index(['cat3', 'featured', 'sort_at', 'id'], 'ls_cat3');
            $table->index(['location_id', 'featured', 'sort_at', 'id'], 'ls_location');
            $table->index(['cat1', 'location_id', 'featured', 'sort_at', 'id'], 'ls_cat1_location');
            $table->index(['cat2', 'location_id', 'featured', 'sort_at', 'id'], 'ls_cat2_location');
            $table->index(['is_firm', 'featured', 'sort_at', 'id'], 'ls_seller');
            $table->index(['user_id', 'featured', 'sort_at', 'id'], 'ls_user');

            // Housekeeping (SearchIndex::refresh).
            $table->index('expires_at', 'ls_expires');
            $table->index(['featured', 'featured_until'], 'ls_featured_until');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE listing_search ADD FULLTEXT ls_text (search_text)');

            // Price sorts: featured first (DESC) and the price in both directions need
            // descending index parts, so the first page is read from the index alone.
            DB::statement('ALTER TABLE listing_search
                ADD INDEX ls_price_asc (featured DESC, no_price, price_eur, id),
                ADD INDEX ls_price_desc (featured DESC, no_price, price_eur DESC, id DESC),
                ADD INDEX ls_cat1_price_asc (cat1, featured DESC, no_price, price_eur, id),
                ADD INDEX ls_cat1_price_desc (cat1, featured DESC, no_price, price_eur DESC, id DESC),
                ADD INDEX ls_cat2_price_asc (cat2, featured DESC, no_price, price_eur, id),
                ADD INDEX ls_cat2_price_desc (cat2, featured DESC, no_price, price_eur DESC, id DESC)');
        } else {
            Schema::table('listing_search', function (Blueprint $table) {
                $table->index(['featured', 'no_price', 'price_eur', 'id'], 'ls_price_asc');
                $table->index(['cat1', 'featured', 'no_price', 'price_eur', 'id'], 'ls_cat1_price_asc');
                $table->index(['cat2', 'featured', 'no_price', 'price_eur', 'id'], 'ls_cat2_price_asc');
            });
        }

        // Field filters (EXISTS per field). lfv_search: one probe per candidate listing;
        // lfv_value / lfv_num: the optimizer may start from the matching values instead
        // (semi-join), and these cover it without reading table rows.
        Schema::table('listing_field_values', function (Blueprint $table) {
            $table->index(['listing_id', 'field_id', 'value', 'value_num'], 'lfv_search');
            $table->index(['field_id', 'value', 'listing_id'], 'lfv_value');
            $table->index(['field_id', 'value_num', 'listing_id'], 'lfv_num');
        });
    }

    public function down(): void
    {
        Schema::table('listing_field_values', function (Blueprint $table) {
            $table->dropIndex('lfv_search');
            $table->dropIndex('lfv_value');
            $table->dropIndex('lfv_num');
        });
        Schema::dropIfExists('listing_search');
    }
};
