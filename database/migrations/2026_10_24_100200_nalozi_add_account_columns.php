<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('phone_verified_at')->constrained()->nullOnDelete();
            $table->json('notification_settings')->nullable()->after('avatar_path'); // in-app notification settings
        });

        // Rotation: the old refresh token points to the new one, so reuse of the old one is detected.
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->foreignId('replaced_by_id')->nullable()->after('revoked_at');
        });
    }

    public function down(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->dropColumn('replaced_by_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
            $table->dropColumn('notification_settings');
        });
    }
};
