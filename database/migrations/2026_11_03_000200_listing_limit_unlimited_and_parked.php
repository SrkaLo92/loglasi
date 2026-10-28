<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * users.listing_unlimited: an admin's private "no limit" deal with one account.
 * listings.parked_at: an active listing moved to drafts because the limit went down; it keeps its
 * address and comes back without a new review.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('listing_unlimited')->default(false)->after('listing_limit');
        });
        Schema::table('listings', function (Blueprint $table) {
            $table->timestamp('parked_at')->nullable()->after('expires_at');
            $table->index(['user_id', 'parked_at']);
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'parked_at']);
            $table->dropColumn('parked_at');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('listing_unlimited');
        });
    }
};
