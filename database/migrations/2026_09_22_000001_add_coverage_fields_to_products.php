<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('sale_unit', 40)->nullable()->after('stock_qty');
            $table->decimal('coverage_sqm_per_unit', 10, 4)->nullable()->after('sale_unit');
            $table->decimal('coverage_waste_percent', 5, 2)->nullable()->after('coverage_sqm_per_unit');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'sale_unit',
                'coverage_sqm_per_unit',
                'coverage_waste_percent',
            ]);
        });
    }
};
