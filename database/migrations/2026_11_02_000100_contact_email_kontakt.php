<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The contact address moved from office@ to kontakt@loglasi.rs; category FAQ texts had the old one written in.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('categories')->where('seo_faq', 'like', '%office@loglasi.rs%')
            ->update(['seo_faq' => DB::raw("REPLACE(seo_faq, 'office@loglasi.rs', 'kontakt@loglasi.rs')")]);
    }

    public function down(): void {}
};
