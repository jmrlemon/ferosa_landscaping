<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_proof_url')->nullable()->after('payment_reference');
            $table->timestamp('delivered_at')->nullable()->after('delivery_proof_url');
            $table->timestamp('customer_confirmed_at')->nullable()->after('delivered_at');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY status ENUM('pending','confirmed','out_for_delivery','delivered','completed','cancelled') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        DB::table('orders')->where('status', 'completed')->update(['status' => 'delivered']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY status ENUM('pending','confirmed','out_for_delivery','delivered','cancelled') NOT NULL DEFAULT 'pending'");
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_proof_url',
                'delivered_at',
                'customer_confirmed_at',
            ]);
        });
    }
};
