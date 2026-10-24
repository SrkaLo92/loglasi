<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A field can depend on another select field (model on make): `depends_on` is the parent field's
// key and every option carries `parent`, the parent option's value.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fields', function (Blueprint $table) {
            $table->string('depends_on', 64)->nullable()->after('unit');
        });
    }

    public function down(): void
    {
        Schema::table('fields', function (Blueprint $table) {
            $table->dropColumn('depends_on');
        });
    }
};
