<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Notifications module: push devices, saved-search dedupe and pending alert matches.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('app_version', 32)->nullable()->after('platform');
            $table->timestamp('last_seen_at')->nullable()->after('app_version');
            $table->index(['user_id', 'last_seen_at']);
        });

        Schema::table('saved_searches', function (Blueprint $table) {
            // sha1 of the normalized query: the same search is saved once per user.
            $table->char('query_hash', 40)->nullable()->after('query');
            $table->unique(['user_id', 'query_hash']);
            $table->index(['notify', 'id']);
        });

        // A listing that matched a saved search; notified_at is set when it went out in a digest.
        Schema::create('saved_search_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saved_search_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->unique(['saved_search_id', 'listing_id']);
            $table->index(['notified_at', 'saved_search_id']);
            $table->index('created_at');
        });

        // Window of newly published listings for the hourly matcher.
        Schema::table('listings', function (Blueprint $table) {
            $table->index(['status', 'published_at', 'id'], 'listings_status_published_index');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex('listings_status_published_index');
        });

        Schema::dropIfExists('saved_search_matches');

        Schema::table('saved_searches', function (Blueprint $table) {
            $table->dropIndex(['notify', 'id']);
            $table->dropUnique(['user_id', 'query_hash']);
            $table->dropColumn('query_hash');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'last_seen_at']);
            $table->dropColumn(['app_version', 'last_seen_at']);
        });
    }
};
