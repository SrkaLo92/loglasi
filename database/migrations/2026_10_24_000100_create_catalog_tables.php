<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique(); // /oglasi/{slug}/
            $table->text('description')->nullable();
            $table->string('icon_path')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('wp_term_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort')->default(0);
            $table->unsignedBigInteger('wp_term_id')->nullable()->unique();
            $table->timestamps();
        });

        // Dodatna polja oglasa (marka, kilometraža, kvadratura...), definišu se iz admina.
        Schema::create('fields', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // ključ u API-ju i u f[ključ]=
            $table->string('label');
            $table->string('type', 16); // App\Enums\FieldType
            $table->string('filter', 16)->default('none'); // App\Enums\FieldFilter
            $table->string('unit', 16)->nullable();
            $table->json('options')->nullable(); // [{value, label}] za izbor
            $table->unsignedInteger('sort')->default(0);
            $table->string('wp_key')->nullable(); // Listivo ključ, za uvoz
            $table->timestamps();
        });

        Schema::create('category_field', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('field_id')->constrained()->cascadeOnDelete();
            $table->boolean('required')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->primary(['category_id', 'field_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_field');
        Schema::dropIfExists('fields');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('categories');
    }
};
