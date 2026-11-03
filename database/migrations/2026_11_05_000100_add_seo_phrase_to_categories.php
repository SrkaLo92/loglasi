<?php

use App\Support\Seo\CategoryPhrases;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Search phrase of a category („Prodaja stanova”) for titles and H1 of the category and its place pages,
 * plus the intro under the H1 of place pages. Fills the phrase for „Stanovi | prodaja” style names.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $t) {
            $t->string('seo_phrase', 120)->nullable()->after('seo_description');
            $t->text('seo_place_intro')->nullable()->after('seo_intro');
        });

        CategoryPhrases::apply();
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $t) {
            $t->dropColumn(['seo_phrase', 'seo_place_intro']);
        });
    }
};
