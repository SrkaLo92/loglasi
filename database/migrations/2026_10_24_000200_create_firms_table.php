<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Profil firme (nalog tipa "firm"). Biznis paket važi do biznis_until.
        Schema::create('firms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('address')->nullable();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('website')->nullable();
            $table->string('facebook')->nullable();
            $table->string('instagram')->nullable();
            $table->json('working_hours')->nullable();
            $table->string('pib', 16)->nullable();
            $table->string('mb', 16)->nullable();
            $table->timestamp('biznis_until')->nullable();
            $table->boolean('founding_free')->default(false); // jedna od prvih 30 firmi
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firms');
    }
};
