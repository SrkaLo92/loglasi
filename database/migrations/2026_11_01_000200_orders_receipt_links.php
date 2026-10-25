<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Fiscal receipt check link of the Tax Administration (suf.purs.gov.rs), sale and refund.
            $table->string('receipt_url', 512)->nullable()->after('receipt_issued_at');
            $table->string('refund_receipt_url', 512)->nullable()->after('refund_receipt_number');
        });

        // Running numbers of our own documents per year (firm invoices "12/2026", credit notes "KO-3/2026").
        Schema::create('document_counters', function (Blueprint $table) {
            $table->string('series', 32)->primary(); // e.g. invoice-2026
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_counters');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['receipt_url', 'refund_receipt_url']);
        });
    }
};
