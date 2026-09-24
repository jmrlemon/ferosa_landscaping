<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('discount_scheme', 40)->default('none')->after('price')->index();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('discount_scheme', 40)->default('none')->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('discount_scheme');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['discount_scheme']);
            $table->dropColumn('discount_scheme');
        });
    }
};
