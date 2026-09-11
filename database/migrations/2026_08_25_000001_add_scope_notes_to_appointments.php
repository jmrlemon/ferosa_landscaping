<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A visit is one crew at one address, so a customer who wants hardscaping
     * *and* lawn care on the same slot is one appointment with a wider scope --
     * not two rows, which would consume two slots and raise two invoices.
     * `notes` stays the customer's own words; staff record the agreed scope
     * here and adjust `appointment_amount` to match.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->text('scope_notes')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('scope_notes');
        });
    }
};
