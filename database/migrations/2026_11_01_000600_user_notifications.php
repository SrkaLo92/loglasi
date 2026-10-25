<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-app notifications ("Obaveštenja", the bell on the site and in the apps): someone saved your listing,
 * a new rating, your listing was approved or rejected, new listings for a saved search. Shown on the site
 * and in the apps only; mail and push stay with their own rules. Newest first per user, keyset on id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            // Who did it (saved the listing, rated); never shown by name for a save.
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('saved_search_id')->nullable()->constrained()->nullOnDelete();
            // Texts at the time (listing title, score, reason, count), kept when the listing is gone.
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'id']);
            $table->index(['user_id', 'read_at']);
            $table->index(['type', 'listing_id', 'actor_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
