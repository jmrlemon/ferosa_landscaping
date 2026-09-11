<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL may reuse the old composite unique index for this foreign key,
        // so provide a dedicated index before removing the unique constraint.
        Schema::table('appointments', function (Blueprint $table) {
            $table->index('service_type_id', 'appointments_service_type_idx');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique('appointments_service_time_unique');
            $table->string('slot_key')->nullable()->after('appointment_at');
            $table->unique('slot_key');
        });

        DB::table('appointments')
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->orderBy('id')
            ->eachById(function ($appointment): void {
                DB::table('appointments')->where('id', $appointment->id)->update([
                    'slot_key' => $appointment->service_type_id.'|'.date('Y-m-d H:i:00', strtotime($appointment->appointment_at)),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique(['slot_key']);
            $table->dropColumn('slot_key');
            $table->unique(['service_type_id', 'appointment_at'], 'appointments_service_time_unique');
            $table->dropIndex('appointments_service_type_idx');
        });
    }
};
