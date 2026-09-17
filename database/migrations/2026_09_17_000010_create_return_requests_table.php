<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->string('claim_number', 50)->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 40)->default('submitted');
            $table->text('customer_summary')->nullable();
            $table->text('customer_contact_notes')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_reason')->nullable();
            $table->text('admin_notes')->nullable();
            $table->boolean('return_required')->default(false);
            $table->text('return_instructions')->nullable();
            $table->string('replacement_driver_name')->nullable();
            $table->string('replacement_driver_phone', 30)->nullable();
            $table->text('replacement_dispatch_notes')->nullable();
            $table->string('replacement_dispatch_proof_path')->nullable();
            $table->timestamp('replacement_dispatched_at')->nullable();
            $table->timestamp('replacement_delivered_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['order_id', 'status']);
            $table->index(['status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_requests');
    }
};
