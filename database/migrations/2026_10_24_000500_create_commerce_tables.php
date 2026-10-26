<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What is paid for: bump, featuring, Biznis package, later Start/Pro/Max. Prices are changed from the admin.
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // bump, featured_7, featured_30, biznis_12m...
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('price_rsd');
            $table->unsignedInteger('duration_days')->nullable();
            $table->unsignedInteger('listing_limit')->nullable(); // for firm packages
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        // An order is paid by bank transfer; the admin marks it as paid.
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('amount_rsd');
            $table->string('reference', 32)->unique(); // "poziv na broj" (payment reference)
            $table->string('status', 16)->default('pending')->index(); // App\Enums\OrderStatus
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('marked_paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('wp_id')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
        Schema::dropIfExists('products');
    }
};
