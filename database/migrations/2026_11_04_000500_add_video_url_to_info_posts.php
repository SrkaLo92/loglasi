<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Our own clip for a post (YouTube is played on the page, Instagram/Facebook/TikTok get a button). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('info_posts', function (Blueprint $t) {
            $t->string('video_url', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('info_posts', function (Blueprint $t) {
            $t->dropColumn('video_url');
        });
    }
};
