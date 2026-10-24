<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * End of the free first year kept apart from package_ends_at, so a package bought during the free
 * year can be queued after it (package_starts_at = end of the free year) without cutting it short.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('firms', function (Blueprint $table) {
            $table->timestamp('free_program_ends_at')->nullable()->after('free_program');
        });

        DB::table('firms')->where('free_program', true)->update(['free_program_ends_at' => DB::raw('package_ends_at')]);
    }

    public function down(): void
    {
        Schema::table('firms', function (Blueprint $table) {
            $table->dropColumn('free_program_ends_at');
        });
    }
};
