<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Adds request workflow storage and an eligibility snapshot for appointments.
    public function up(): void
    {
        Schema::create('discount_requests', function (Blueprint $table) {
            $table->id();
            $table->morphs('requestable');
            $table->unique(['requestable_type', 'requestable_id'], 'discount_requests_one_per_record');
            $table->string('beneficiary_type', 20);
            $table->string('evidence_path');
            $table->string('status', 20)->default('pending');
            $table->string('id_reference_last4', 4)->nullable();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::table('service_types', function (Blueprint $table) {
            $table->string('discount_scheme', 40)->default('none')->after('default_fee')->index();
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->string('discount_scheme', 40)->default('none')->after('appointment_amount')->index();
        });

        Schema::table('discount_applications', function (Blueprint $table) {
            $table->foreignId('discount_request_id')
                ->nullable()
                ->unique()
                ->constrained('discount_requests')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('discount_applications', function (Blueprint $table) {
            $table->dropForeign(['discount_request_id']);
            $table->dropUnique(['discount_request_id']);
            $table->dropColumn('discount_request_id');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['discount_scheme']);
            $table->dropColumn('discount_scheme');
        });

        Schema::table('service_types', function (Blueprint $table) {
            $table->dropIndex(['discount_scheme']);
            $table->dropColumn('discount_scheme');
        });

        Schema::dropIfExists('discount_requests');
    }
};
