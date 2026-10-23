<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Firm profile columns (activity, firm phone, messenger flags, profile views) are also added by the
     * listing module (2026_10_26_000100_listing_add_profile_and_stats_columns) with the same definitions;
     * each column here is added only when missing, so both branches merge in any order.
     */
    public function up(): void
    {
        $missing = fn (string $column) => ! Schema::hasColumn('firms', $column);

        Schema::table('firms', function (Blueprint $table) use ($missing) {
            if ($missing('activity')) {
                $table->string('activity', 60)->nullable()->after('name');
            }
            if ($missing('phone')) {
                $table->string('phone', 20)->nullable()->after('address');
            }
            if ($missing('whatsapp')) {
                $table->boolean('whatsapp')->nullable()->after('phone');
            }
            if ($missing('viber')) {
                $table->boolean('viber')->nullable()->after('whatsapp');
            }
            if ($missing('profile_views')) {
                $table->unsignedInteger('profile_views')->default(0)->after('founding_free');
            }

            // Biznis expiry reminders: which reminder (days before) was sent for which biznis_until.
            $table->unsignedSmallInteger('biznis_reminder_days')->nullable()->after('biznis_until');
            $table->timestamp('biznis_reminder_for')->nullable()->after('biznis_reminder_days');
            $table->index('biznis_until');
        });

        // Active listings per user (directory counts, firm stats join).
        if (! Schema::hasIndex('listings', ['user_id', 'status', 'bumped_at'])) {
            Schema::table('listings', function (Blueprint $table) {
                $table->index(['user_id', 'status', 'bumped_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('firms', function (Blueprint $table) {
            $table->dropIndex(['biznis_until']);
            $table->dropColumn(['biznis_reminder_days', 'biznis_reminder_for']);
        });
    }
};
