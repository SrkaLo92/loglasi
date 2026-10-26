<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Import: source keys of messages from WordPress, so a repeated import does not create duplicates.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('wp_key', 64)->nullable()->unique();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->unsignedBigInteger('wp_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropUnique(['wp_id']);
            $table->dropColumn('wp_id');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique(['wp_key']);
            $table->dropColumn('wp_key');
        });
    }
};
