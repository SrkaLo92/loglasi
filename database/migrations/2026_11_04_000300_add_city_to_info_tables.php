<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** City key on all info portal content, so the portal can later be filtered by city (Loznica, Šabac, ...). */
return new class extends Migration
{
    private const TABLES = ['info_posts', 'info_service_notices', 'info_pharmacy_duties', 'info_businesses', 'info_ads'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('city', 40)->default('loznica')->index();
            });
        }
        // One pharmacy on duty per day and city.
        Schema::table('info_pharmacy_duties', function (Blueprint $t) {
            $t->dropUnique('info_pharmacy_duties_date_unique');
            $t->unique(['date', 'city']);
        });
    }

    public function down(): void
    {
        Schema::table('info_pharmacy_duties', function (Blueprint $t) {
            $t->dropUnique(['date', 'city']);
            $t->unique('date');
        });
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropIndex($table.'_city_index');
                $t->dropColumn('city');
            });
        }
    }
};
