<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Buyer: a private person gets a fiscal receipt, a firm gets an invoice (name, PIB, MB on it).
            $table->string('buyer_type', 16)->default('private')->after('amount_rsd'); // App\Enums\BuyerType
            $table->string('buyer_name')->nullable()->after('buyer_type');
            $table->string('buyer_pib', 16)->nullable()->after('buyer_name');
            $table->string('buyer_mb', 16)->nullable()->after('buyer_pib');
            $table->string('buyer_address')->nullable()->after('buyer_mb');
            $table->string('buyer_email')->nullable()->after('buyer_address'); // where the firm's invoice goes

            // Terms accepted by everyone; private buyers also consent that the service starts on
            // payment (with that the withdrawal right ends once the service is fully performed).
            $table->timestamp('terms_accepted_at')->nullable()->after('buyer_email');
            $table->timestamp('consent_at')->nullable()->after('terms_accepted_at');
            $table->string('consent_ip', 45)->nullable()->after('consent_at');

            // Test mode: the whole flow runs, documents are fake (TEST-...), nothing is booked.
            $table->boolean('is_test')->default(false)->after('consent_ip');

            $table->timestamp('activated_at')->nullable()->after('marked_paid_by');
            // Period this order added (featured or Biznis), for pro-rata refunds of unused days.
            $table->timestamp('service_starts_at')->nullable()->after('activated_at');
            $table->timestamp('service_ends_at')->nullable()->after('service_starts_at');

            // Receipt or invoice for the sale (number entered by the admin, or from the fiscal service later).
            $table->string('receipt_type', 24)->nullable()->after('activated_at'); // App\Enums\ReceiptType
            $table->string('receipt_number', 64)->nullable()->after('receipt_type');
            $table->timestamp('receipt_issued_at')->nullable()->after('receipt_number');
            // Payment confirmed on a later day than it arrived: advance receipt first, final receipt after.
            $table->string('advance_receipt_number', 64)->nullable()->after('receipt_issued_at');
            $table->timestamp('advance_receipt_issued_at')->nullable()->after('advance_receipt_number');

            // Refund: the refund document references the original receipt.
            $table->timestamp('refunded_at')->nullable()->after('advance_receipt_issued_at');
            $table->foreignId('refunded_by')->nullable()->after('refunded_at')->constrained('users')->nullOnDelete();
            $table->string('refund_of_receipt', 64)->nullable()->after('refunded_by');
            $table->string('refund_receipt_number', 64)->nullable()->after('refund_of_receipt');
            $table->unsignedInteger('refund_amount_rsd')->nullable()->after('refund_receipt_number');

            $table->timestamp('cancelled_at')->nullable()->after('refund_receipt_number');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();

            // "My orders" list, newest first.
            $table->index(['user_id', 'created_at'], 'orders_user_created_index');
            // Admin queue and the stale-order job: WHERE status = ? ORDER BY / WHERE created_at < ?.
            $table->index(['status', 'created_at'], 'orders_status_created_index');
            // Duplicate check before creating an order: same listing, same product, still pending.
            $table->index(['listing_id', 'product_id', 'status'], 'orders_listing_product_status_index');
            // Admin search by receipt number.
            $table->index('receipt_number', 'orders_receipt_number_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_receipt_number_index');
            $table->dropIndex('orders_listing_product_status_index');
            $table->dropIndex('orders_status_created_index');
            $table->dropIndex('orders_user_created_index');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropConstrainedForeignId('refunded_by');
            $table->dropColumn([
                'buyer_type', 'buyer_name', 'buyer_pib', 'buyer_mb', 'buyer_address', 'buyer_email',
                'terms_accepted_at', 'consent_at', 'consent_ip', 'is_test',
                'activated_at', 'service_starts_at', 'service_ends_at',
                'receipt_type', 'receipt_number', 'receipt_issued_at', 'advance_receipt_number', 'advance_receipt_issued_at',
                'refunded_at', 'refund_of_receipt', 'refund_receipt_number', 'refund_amount_rsd', 'cancelled_at',
            ]);
        });
    }
};
