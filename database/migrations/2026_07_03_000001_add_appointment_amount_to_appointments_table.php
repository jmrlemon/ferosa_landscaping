<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('appointments', 'appointment_amount')) {
                $table->decimal('appointment_amount', 10, 2)->default(0)->after('payment_status');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('
                UPDATE appointments
                INNER JOIN service_types ON service_types.id = appointments.service_type_id
                SET appointments.appointment_amount = service_types.default_fee
                WHERE appointments.appointment_amount = 0
            ');
        } else {
            DB::statement('
                UPDATE appointments
                SET appointment_amount = (
                    SELECT default_fee FROM service_types
                    WHERE service_types.id = appointments.service_type_id
                )
                WHERE appointment_amount = 0
            ');
        }
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'appointment_amount')) {
                $table->dropColumn('appointment_amount');
            }
        });
    }
};
