<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A buyer's rating of a seller, given from a conversation both of them wrote in. One per buyer and seller.
        Schema::create('seller_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('rater_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('score'); // 1-5
            $table->text('comment')->nullable();
            $table->text('reply')->nullable(); // the seller's public answer
            $table->timestamp('replied_at')->nullable();
            $table->string('status', 16)->default('visible'); // visible | hidden (admin)
            $table->timestamp('reported_at')->nullable(); // the seller asked the admin to look at it
            $table->string('report_reason', 500)->nullable();
            $table->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['rater_id', 'seller_id']);
            $table->index(['seller_id', 'status', 'created_at']);
            $table->index(['reported_at']);
        });

        // Average and count of visible ratings, kept on the user for cards and lists.
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('rating_avg', 3, 2)->nullable();
            $table->unsignedInteger('rating_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['rating_avg', 'rating_count']);
        });
        Schema::dropIfExists('seller_ratings');
    }
};
