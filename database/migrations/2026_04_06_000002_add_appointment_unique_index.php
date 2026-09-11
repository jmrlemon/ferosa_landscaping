<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Prevent double-booking the same service+datetime.
            // (If you later want staff capacity > 1, replace with capacity logic.)
            $table->unique(['service_type_id', 'appointment_at'], 'appointments_service_time_unique');
            $table->index(['appointment_at', 'status'], 'appointments_time_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique('appointments_service_time_unique');
            $table->dropIndex('appointments_time_status_idx');
        });
    }
};
