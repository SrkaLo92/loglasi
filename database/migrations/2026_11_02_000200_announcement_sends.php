<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// One row per user and announcement mail, so a second run of mail:announce never sends the same mail twice.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_sends', function (Blueprint $table) {
            $table->id();
            $table->string('campaign', 60);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['campaign', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_sends');
    }
};
