<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Municipality of a firm in the directory (Loznica, Šabac, Bogatić, ...), for the directory filter. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('info_businesses', function (Blueprint $t) {
            $t->string('municipality', 60)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('info_businesses', function (Blueprint $t) {
            $t->dropIndex(['municipality']);
            $t->dropColumn('municipality');
        });
    }
};
