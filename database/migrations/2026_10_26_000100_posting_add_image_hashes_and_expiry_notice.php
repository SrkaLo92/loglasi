<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_images', function (Blueprint $table) {
            // sha1 of the stored file and a 64-bit difference hash (hex) for duplicate detection
            $table->char('hash', 40)->nullable()->after('path')->index();
            $table->char('phash', 16)->nullable()->after('hash')->index();
            $table->unsignedInteger('size')->nullable()->after('height');
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('listings', function (Blueprint $table) {
            // when the "expires in 3 days" mail was queued; reset on renewal
            $table->timestamp('expiry_notified_at')->nullable()->after('expires_at');
            $table->index(['status', 'expires_at']);
            $table->index(['user_id', 'category_id', 'created_at']);
            $table->index(['user_id', 'status', 'updated_at']); // "my listings" tabs
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status', 'updated_at']);
            $table->dropIndex(['user_id', 'category_id', 'created_at']);
            $table->dropIndex(['status', 'expires_at']);
            $table->dropColumn('expiry_notified_at');
        });

        Schema::table('listing_images', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropColumn(['hash', 'phash', 'size']);
        });
    }
};
