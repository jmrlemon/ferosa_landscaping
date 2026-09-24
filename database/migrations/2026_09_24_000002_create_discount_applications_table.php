<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_applications', function (Blueprint $table) {
            $table->id();
            $table->morphs('discountable');
            $table->string('scheme', 40);
            $table->string('beneficiary_type', 20);
            $table->string('id_reference_last4', 4)->nullable();
            $table->string('status', 20)->default('approved');
            $table->decimal('gross_total', 12, 2);
            $table->decimal('eligible_gross', 12, 2);
            $table->decimal('vat_removed', 12, 2)->default(0);
            $table->decimal('discount_base', 12, 2);
            $table->decimal('discount_rate', 5, 2);
            $table->decimal('discount_amount', 12, 2);
            $table->decimal('net_total', 12, 2);
            $table->string('legal_basis_version', 100);
            $table->json('metadata')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at');
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->timestamps();

            $table->index(['scheme', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_applications');
    }
};
