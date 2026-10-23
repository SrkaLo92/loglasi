<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Firm profile fields from the WordPress profile module that had no column yet.
        Schema::table('firms', function (Blueprint $table) {
            $table->string('activity', 60)->nullable()->after('name'); // e.g. "Auto servis"
            $table->string('phone', 20)->nullable()->after('address'); // E.164; empty = account phone
            $table->boolean('whatsapp')->nullable()->after('phone'); // null = auto (mobile numbers only)
            $table->boolean('viber')->nullable()->after('whatsapp');
            $table->unsignedInteger('profile_views')->default(0)->after('founding_free');
        });

        // Seller pages and the seller card count active listings per user.
        Schema::table('listings', function (Blueprint $table) {
            $table->index(['user_id', 'status', 'bumped_at']);
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status', 'bumped_at']);
        });

        Schema::table('firms', function (Blueprint $table) {
            $table->dropColumn(['activity', 'phone', 'whatsapp', 'viber', 'profile_views']);
        });
    }
};
