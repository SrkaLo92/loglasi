<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Info portal advertising: richer business profiles (featured period, hours, gallery), paid ad slots
// and the inquiry form on /oglasavanje/.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('info_businesses', function (Blueprint $table) {
            $table->timestamp('featured_until')->nullable()->after('featured');
            $table->string('viber', 40)->nullable()->after('phone');
            $table->text('opening_hours')->nullable()->after('description');
            $table->json('gallery')->nullable()->after('opening_hours');
        });

        Schema::create('info_ads', function (Blueprint $table) {
            $table->id();
            $table->string('slot', 32); // today_sponsor, article_bottom, home_middle, category_top
            $table->foreignId('info_business_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category')->nullable(); // category_top: which directory category
            $table->string('title');
            $table->string('text')->nullable();
            $table->string('image_path')->nullable();
            $table->string('link_url', 500)->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();

            $table->index(['slot', 'active', 'starts_on']);
        });

        Schema::create('info_ad_inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('company');
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('interest', 40);
            $table->text('message')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('info_ad_inquiries');
        Schema::dropIfExists('info_ads');
        Schema::table('info_businesses', function (Blueprint $table) {
            $table->dropColumn(['featured_until', 'viber', 'opening_hours', 'gallery']);
        });
    }
};
