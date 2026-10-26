<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id(); // same ID as in WordPress for imported listings
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique(); // /oglas/{slug}/
            $table->longText('description')->nullable();
            $table->decimal('price', 14, 2)->nullable(); // null = "Dogovor"
            $table->char('currency', 3)->default('EUR'); // EUR | RSD
            $table->decimal('price_eur', 14, 2)->nullable()->index(); // for sorting and filtering
            $table->string('status', 16)->default('pending')->index(); // App\Enums\ListingStatus
            $table->string('rejection_reason')->nullable();
            $table->string('contact_phone', 20)->nullable(); // E.164; empty = the account's phone
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamp('featured_until')->nullable()->index();
            $table->timestamp('bumped_at')->nullable(); // poslednje podizanje
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('phone_reveals')->default(0);
            $table->unsignedBigInteger('wp_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'category_id', 'bumped_at']);
            $table->index(['user_id', 'created_at']);
        });

        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement('ALTER TABLE listings ADD FULLTEXT listings_fulltext (title, description)');
        }

        Schema::create('listing_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('field_id')->constrained()->cascadeOnDelete();
            $table->string('value')->nullable();
            $table->decimal('value_num', 16, 2)->nullable(); // for ranges (from-to)
            $table->index(['field_id', 'value']);
            $table->index(['field_id', 'value_num']);
            $table->index('listing_id');
        });

        Schema::create('listing_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->nullable()->constrained()->cascadeOnDelete(); // null until the listing is saved
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // who uploaded it
            $table->string('path'); // original on the "public" disk
            $table->json('variants')->nullable(); // {"sm":{"webp":"...","avif":"..."},"md":...}
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->string('alt')->nullable();
            $table->unsignedBigInteger('wp_attachment_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['user_id', 'listing_id']);
        });

        // Daily statistics per listing (firm statistics, admin analytics).
        Schema::create('listing_daily_stats', function (Blueprint $table) {
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('phone_reveals')->default(0);
            $table->unsignedInteger('messages')->default(0);
            $table->unsignedInteger('favorites')->default(0);
            $table->primary(['listing_id', 'date']);
            $table->index('date');
        });

        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->json('query'); // same parameters as /listings
            $table->boolean('notify')->default(true);
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
        Schema::dropIfExists('listing_daily_stats');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('listing_images');
        Schema::dropIfExists('listing_field_values');
        Schema::dropIfExists('listings');
    }
};
