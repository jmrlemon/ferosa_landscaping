<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_otps', function (Blueprint $table): void {
            // Some MySQL/MariaDB configurations implicitly attach
            // ON UPDATE CURRENT_TIMESTAMP to the first TIMESTAMP column.
            // Updating sent_at then made a newly delivered OTP expire at once.
            $table->dateTime('expires_at')->change();
        });
    }

    public function down(): void
    {
        Schema::table('registration_otps', function (Blueprint $table): void {
            $table->timestamp('expires_at')->change();
        });
    }
};
