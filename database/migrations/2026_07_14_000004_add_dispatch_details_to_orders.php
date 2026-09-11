<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('dispatch_proof_url')->nullable()->after('delivery_proof_url');
            $table->timestamp('dispatched_at')->nullable()->after('dispatch_proof_url');
            $table->string('driver_name')->nullable()->after('dispatched_at');
            $table->string('driver_phone', 50)->nullable()->after('driver_name');
            $table->text('dispatch_notes')->nullable()->after('driver_phone');
            $table->string('delivery_recipient_name')->nullable()->after('dispatch_notes');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'dispatch_proof_url',
                'dispatched_at',
                'driver_name',
                'driver_phone',
                'dispatch_notes',
                'delivery_recipient_name',
            ]);
        });
    }
};
