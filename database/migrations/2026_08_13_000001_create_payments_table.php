<?php

use App\Models\Appointment;
use App\Models\Order;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // Orders and appointments are both billable, so the ledger is polymorphic
            // rather than carrying two nullable foreign keys.
            $table->morphs('payable');
            $table->decimal('amount', 10, 2);
            $table->string('method')->default('cash');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            // Payments are never deleted - a mistake is voided so the trail survives.
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamps();

            $table->index(['payable_type', 'payable_id', 'voided_at'], 'payments_payable_voided_index');
        });

        // Orders and appointments already marked paid predate the ledger. Seed one
        // settling entry each so their balance reads zero instead of fully unpaid.
        $now = now();

        DB::table('orders')
            ->where('payment_status', 'paid')
            ->where('total_amount', '>', 0)
            ->orderBy('id')
            ->get(['id', 'total_amount', 'payment_method', 'payment_reference', 'payment_verified_at', 'payment_verified_by', 'created_at'])
            ->each(function (object $order) use ($now): void {
                DB::table('payments')->insert([
                    'payable_type' => Order::class,
                    'payable_id' => $order->id,
                    'amount' => $order->total_amount,
                    'method' => $order->payment_method ?: 'cash',
                    'reference' => $order->payment_reference,
                    'notes' => 'Backfilled from the pre-ledger paid status.',
                    'paid_at' => $order->payment_verified_at ?: $order->created_at ?: $now,
                    'recorded_by' => $order->payment_verified_by,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });

        DB::table('appointments')
            ->where('payment_status', 'paid')
            ->where('appointment_amount', '>', 0)
            ->orderBy('id')
            ->get(['id', 'appointment_amount', 'created_at'])
            ->each(function (object $appointment) use ($now): void {
                DB::table('payments')->insert([
                    'payable_type' => Appointment::class,
                    'payable_id' => $appointment->id,
                    'amount' => $appointment->appointment_amount,
                    'method' => 'cash',
                    'notes' => 'Backfilled from the pre-ledger paid status.',
                    'paid_at' => $appointment->created_at ?: $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
