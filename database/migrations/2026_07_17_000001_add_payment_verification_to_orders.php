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
            $table->string('payment_reference_normalized')->nullable()->after('payment_reference');
            $table->string('payment_proof_path')->nullable()->after('payment_reference_normalized');
            $table->text('payment_review_notes')->nullable()->after('payment_proof_path');
            $table->timestamp('payment_verified_at')->nullable()->after('payment_review_notes');
            $table->foreignId('payment_verified_by')->nullable()->after('payment_verified_at')
                ->constrained('users')->nullOnDelete();
        });

        $seen = [];
        DB::table('orders')
            ->whereNotNull('payment_reference')
            ->orderBy('id')
            ->get(['id', 'payment_reference'])
            ->each(function (object $order) use (&$seen): void {
                $normalized = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $order->payment_reference));
                if ($normalized === '' || isset($seen[$normalized])) {
                    return;
                }

                $seen[$normalized] = true;
                DB::table('orders')->where('id', $order->id)->update([
                    'payment_reference_normalized' => $normalized,
                ]);
            });

        Schema::table('orders', function (Blueprint $table) {
            $table->unique('payment_reference_normalized', 'orders_payment_reference_normalized_unique');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_payment_reference_normalized_unique');
            $table->dropConstrainedForeignId('payment_verified_by');
            $table->dropColumn([
                'payment_reference_normalized',
                'payment_proof_path',
                'payment_review_notes',
                'payment_verified_at',
            ]);
        });
    }
};
