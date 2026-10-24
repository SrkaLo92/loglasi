<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Messages module: a user blocks another user; neither can then write to the other.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_blocks', function (Blueprint $table) {
            $table->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['blocker_id', 'blocked_id']);
            $table->index('blocked_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_blocks');
    }
};
