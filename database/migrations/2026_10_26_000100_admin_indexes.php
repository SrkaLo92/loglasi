<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Admin module: indexes for the admin tables (filters + default sort) at 200k listings.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            // Default admin list (newest first, without deleted) and status tabs / API queue.
            $table->index(['deleted_at', 'created_at'], 'listings_admin_created');
            $table->index(['status', 'deleted_at', 'created_at'], 'listings_admin_status_created');
            $table->index(['category_id', 'deleted_at', 'created_at'], 'listings_admin_category_created');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index(['deleted_at', 'created_at'], 'users_admin_created');
            $table->index(['phone_verified_at', 'phone'], 'users_admin_phone_queue');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'orders_admin_status_created');
            $table->index('paid_at', 'orders_admin_paid_at');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'reports_admin_status_created');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['action', 'id'], 'audit_logs_admin_action');
            $table->index(['user_id', 'id'], 'audit_logs_admin_user');
        });

        // Dashboard sums by date: covering index, no row lookups.
        Schema::table('listing_daily_stats', function (Blueprint $table) {
            $table->index(['date', 'views', 'phone_reveals', 'messages'], 'listing_daily_stats_admin_totals');
        });

        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->index('last_used_at', 'refresh_tokens_admin_last_used');
        });
    }

    public function down(): void
    {
        Schema::table('refresh_tokens', fn (Blueprint $t) => $t->dropIndex('refresh_tokens_admin_last_used'));
        Schema::table('listing_daily_stats', fn (Blueprint $t) => $t->dropIndex('listing_daily_stats_admin_totals'));
        Schema::table('audit_logs', function (Blueprint $t) {
            $t->dropIndex('audit_logs_admin_action');
            $t->dropIndex('audit_logs_admin_user');
        });
        Schema::table('reports', fn (Blueprint $t) => $t->dropIndex('reports_admin_status_created'));
        Schema::table('orders', function (Blueprint $t) {
            $t->dropIndex('orders_admin_status_created');
            $t->dropIndex('orders_admin_paid_at');
        });
        Schema::table('users', function (Blueprint $t) {
            $t->dropIndex('users_admin_created');
            $t->dropIndex('users_admin_phone_queue');
        });
        Schema::table('listings', function (Blueprint $t) {
            $t->dropIndex('listings_admin_created');
            $t->dropIndex('listings_admin_status_created');
            $t->dropIndex('listings_admin_category_created');
        });
    }
};
