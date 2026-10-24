<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Landing fields of a category (admin): H1, intro under it, FAQ. Fills the texts kept from
// WordPress for the six main categories, only where a field is still empty.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('seo_h1')->nullable()->after('seo_description');
            $table->text('seo_intro')->nullable()->after('seo_h1');
            $table->text('seo_faq')->nullable()->after('seo_intro');
        });

        foreach (require database_path('seeders/data/category-seo-2026-10-24.php') as $slug => $fields) {
            foreach ($fields as $column => $value) {
                DB::table('categories')->where('slug', $slug)
                    ->where(fn ($q) => $q->whereNull($column)->orWhere($column, ''))
                    ->update([$column => $value]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['seo_h1', 'seo_intro', 'seo_faq']);
        });
    }
};
