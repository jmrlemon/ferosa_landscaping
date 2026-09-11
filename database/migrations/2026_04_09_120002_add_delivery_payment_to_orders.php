<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_method')->default('delivery')->after('archived_at');
            $table->string('delivery_name')->nullable()->after('delivery_method');
            $table->string('delivery_phone')->nullable()->after('delivery_name');
            $table->text('delivery_address')->nullable()->after('delivery_phone');
            $table->string('delivery_city')->nullable()->after('delivery_address');
            $table->text('delivery_notes')->nullable()->after('delivery_city');
            $table->string('payment_method')->default('cod')->after('delivery_notes');
            $table->string('payment_reference')->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_method',
                'delivery_name',
                'delivery_phone',
                'delivery_address',
                'delivery_city',
                'delivery_notes',
                'payment_method',
                'payment_reference',
            ]);
        });
    }
};
