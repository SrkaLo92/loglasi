<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SEO module: hit counts for redirects, 410s and rules from code.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('redirects', function (Blueprint $table) {
            $table->timestamp('last_hit_at')->nullable()->after('hits');
        });

        Schema::table('gone_urls', function (Blueprint $table) {
            $table->unsignedInteger('hits')->default(0)->after('note');
            $table->timestamp('last_hit_at')->nullable()->after('hits');
        });

        Schema::create('seo_rule_hits', function (Blueprint $table) {
            $table->id();
            $table->string('rule', 64)->unique(); // e.g. kategorija-param, woo-product, yoast-sitemap
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_rule_hits');

        Schema::table('gone_urls', function (Blueprint $table) {
            $table->dropColumn(['hits', 'last_hit_at']);
        });

        Schema::table('redirects', function (Blueprint $table) {
            $table->dropColumn('last_hit_at');
        });
    }
};
