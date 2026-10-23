<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Messages module: denormalized unread counters and indexes for inbox, spam guard and favourites at scale.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // Unread messages for each side; incremented on send, reset when that side reads.
            $table->unsignedInteger('buyer_unread')->default(0)->after('seller_read_at');
            $table->unsignedInteger('seller_unread')->default(0)->after('buyer_unread');

            // Inbox keyset pagination: one index per side, matching WHERE side = ? ORDER BY last_message_at, id.
            $table->index(['buyer_id', 'last_message_at', 'id'], 'conversations_buyer_inbox_index');
            $table->index(['seller_id', 'last_message_at', 'id'], 'conversations_seller_inbox_index');
        });

        Schema::table('messages', function (Blueprint $table) {
            // Spam guard and rate checks: messages of one sender in a time window.
            $table->index(['sender_id', 'created_at'], 'messages_sender_created_index');
        });

        Schema::table('favorites', function (Blueprint $table) {
            // "My favourites" newest first, covering the listing id.
            $table->index(['user_id', 'created_at', 'listing_id'], 'favorites_user_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('favorites', function (Blueprint $table) {
            $table->dropIndex('favorites_user_created_index');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_sender_created_index');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('conversations_buyer_inbox_index');
            $table->dropIndex('conversations_seller_inbox_index');
            $table->dropColumn(['buyer_unread', 'seller_unread']);
        });
    }
};
