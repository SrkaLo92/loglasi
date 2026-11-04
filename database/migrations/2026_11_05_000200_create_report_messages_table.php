<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Questions from the admin to the person who reported, and their answers, kept with the report
 * until it is closed. staff_unread marks a report whose reporter answered and nobody read it yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->boolean('from_staff')->default(false);
            $t->text('body');
            $t->timestamps();
            $t->index(['report_id', 'id']);
        });

        Schema::table('reports', function (Blueprint $t) {
            $t->boolean('staff_unread')->default(false)->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $t) {
            $t->dropColumn('staff_unread');
        });
        Schema::dropIfExists('report_messages');
    }
};
