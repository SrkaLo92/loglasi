<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Local info portal: posts (news, events, stories, guides), service notices, pharmacy duty,
// sources, the business directory and requests from the firms listed in it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('info_posts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16); // news, event, story, guide, notice
            $table->string('status', 16)->default('pending'); // pending, published, rejected
            $table->string('origin', 16)->default('manual'); // manual, automation, citizen
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary');
            $table->mediumText('body')->nullable();
            $table->string('topic', 32)->nullable();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('place')->nullable();
            $table->string('source_name')->nullable();
            $table->string('source_url', 700)->nullable();
            $table->char('source_hash', 40)->nullable()->unique();
            $table->string('image_path')->nullable();
            $table->string('image_credit')->nullable();
            $table->dateTime('event_starts_at')->nullable();
            $table->dateTime('event_ends_at')->nullable();
            $table->string('event_venue')->nullable();
            $table->boolean('sponsored')->default(false);
            $table->string('sponsor_name')->nullable();
            $table->string('sponsor_url')->nullable();
            $table->boolean('featured')->default(false);
            $table->boolean('needs_check')->default(false);
            $table->string('submitter_name')->nullable();
            $table->string('submitter_email')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['type', 'status', 'published_at']);
            $table->index(['type', 'status', 'event_starts_at']);
        });

        Schema::create('info_service_notices', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 16); // power, water, road, other
            $table->string('municipality')->nullable();
            $table->string('place')->nullable();
            $table->text('area');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('source_name')->nullable();
            $table->string('source_url', 700)->nullable();
            $table->char('external_hash', 40)->nullable()->unique();
            $table->boolean('published')->default(true);
            $table->timestamps();

            $table->index(['kind', 'published', 'starts_at']);
        });

        Schema::create('info_pharmacy_duties', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('hours', 60)->nullable();
            $table->timestamps();
        });

        Schema::create('info_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->string('feed_url')->nullable();
            $table->string('kind', 16)->default('portal'); // portal, institution
            $table->boolean('active')->default(true);
            $table->boolean('consent')->default(false); // written OK from the source
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('info_businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->string('address')->nullable();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('place')->nullable();
            $table->string('phone', 120)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('facebook')->nullable();
            $table->string('instagram')->nullable();
            $table->foreignId('firm_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('listed'); // listed, hidden
            $table->boolean('featured')->default(false);
            $table->string('source')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'category']);
            $table->index(['status', 'name']);
        });

        Schema::create('info_business_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('info_business_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16); // claim, correct, remove
            $table->string('name');
            $table->string('email');
            $table->string('phone', 40)->nullable();
            $table->text('message')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('info_business_requests');
        Schema::dropIfExists('info_businesses');
        Schema::dropIfExists('info_sources');
        Schema::dropIfExists('info_pharmacy_duties');
        Schema::dropIfExists('info_service_notices');
        Schema::dropIfExists('info_posts');
    }
};
