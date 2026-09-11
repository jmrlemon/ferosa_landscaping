<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'is_active')) {
                $table->index('is_active', 'products_is_active_idx');
            }
        });

        Schema::table('service_types', function (Blueprint $table) {
            if (Schema::hasColumn('service_types', 'is_active')) {
                $table->index('is_active', 'service_types_is_active_idx');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('items')->index();
            }
            $table->index(['user_id', 'created_at'], 'orders_user_created_idx');
            $table->index(['status', 'created_at'], 'orders_status_created_idx');
        });

        Schema::table('appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('appointments', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('notes')->index();
            }
            $table->index(['user_id', 'appointment_at'], 'appointments_user_time_idx');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_is_active_idx');
        });

        Schema::table('service_types', function (Blueprint $table) {
            $table->dropIndex('service_types_is_active_idx');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_user_created_idx');
            $table->dropIndex('orders_status_created_idx');
            if (Schema::hasColumn('orders', 'archived_at')) {
                $table->dropIndex(['archived_at']);
                $table->dropColumn('archived_at');
            }
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_user_time_idx');
            if (Schema::hasColumn('appointments', 'archived_at')) {
                $table->dropIndex(['archived_at']);
                $table->dropColumn('archived_at');
            }
        });
    }
};
