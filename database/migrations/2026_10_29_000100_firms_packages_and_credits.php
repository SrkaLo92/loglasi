<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Firm packages of the 24 Oct 2026 price list (Business Start / Pro / Max, monthly or yearly) and
 * the free first year for the first 30 firms, replacing the single 12-month package (biznis_until).
 * Monthly included promotions (bumps, highlights, Premium XL) are counted in firm_monthly_credits.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('firms', function (Blueprint $table) {
            $table->string('package', 16)->nullable()->after('mb');          // start | pro | max
            $table->string('package_period', 8)->nullable()->after('package'); // monthly | yearly
            $table->timestamp('package_starts_at')->nullable()->after('package_period');
            $table->timestamp('package_ends_at')->nullable()->after('package_starts_at');
            $table->boolean('free_program')->default(false)->after('package_ends_at'); // current term is the free year
            // Expiry reminders: which one (days before) was sent for which package_ends_at.
            $table->unsignedSmallInteger('package_reminder_days')->nullable()->after('free_program');
            $table->timestamp('package_reminder_for')->nullable()->after('package_reminder_days');
            $table->index('package_ends_at');
        });

        // Old single package: a free founding term stays free, any other paid term becomes Business Start.
        DB::table('firms')->whereNotNull('biznis_until')->orderBy('id')->each(function ($firm) {
            DB::table('firms')->where('id', $firm->id)->update([
                'free_program' => (bool) $firm->founding_free,
                'package' => $firm->founding_free ? null : 'start',
                'package_period' => $firm->founding_free ? null : 'yearly',
                'package_ends_at' => $firm->biznis_until,
            ]);
        });

        Schema::table('firms', function (Blueprint $table) {
            $table->dropColumn('biznis_until');
        });

        DB::table('products')->where('code', 'biznis_12m')->update(['is_active' => false, 'updated_at' => now()]);

        Schema::create('firm_monthly_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained()->cascadeOnDelete();
            $table->date('month'); // first day of the month (Europe/Belgrade)
            $table->unsignedSmallInteger('bumps_granted')->default(0);
            $table->unsignedSmallInteger('bumps_used')->default(0);
            $table->unsignedSmallInteger('highlights_granted')->default(0);
            $table->unsignedSmallInteger('highlights_used')->default(0);
            $table->unsignedSmallInteger('premium_xl_granted')->default(0);
            $table->unsignedSmallInteger('premium_xl_used')->default(0);
            $table->timestamps();
            $table->unique(['firm_id', 'month']);
        });

        if (! Schema::hasIndex('listings', ['user_id', 'status', 'bumped_at'])) {
            Schema::table('listings', function (Blueprint $table) {
                $table->index(['user_id', 'status', 'bumped_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('firm_monthly_credits');

        Schema::table('firms', function (Blueprint $table) {
            $table->timestamp('biznis_until')->nullable()->after('mb');
        });
        DB::table('firms')->update(['biznis_until' => DB::raw('package_ends_at')]);

        Schema::table('firms', function (Blueprint $table) {
            $table->dropIndex(['package_ends_at']);
            $table->dropColumn(['package', 'package_period', 'package_starts_at', 'package_ends_at', 'free_program', 'package_reminder_days', 'package_reminder_for']);
        });
    }
};
