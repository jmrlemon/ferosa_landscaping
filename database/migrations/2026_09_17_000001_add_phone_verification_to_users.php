<?php

use App\Support\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $canonicalPhones = [];
        foreach (DB::table('users')->whereNotNull('phone_number')->get(['id', 'phone_number']) as $row) {
            $canonical = PhoneNumber::normalize((string) $row->phone_number);
            if (isset($canonicalPhones[$canonical])) {
                throw new LogicException('Cannot add phone verification until duplicated Philippine mobile numbers are resolved.');
            }
            $canonicalPhones[$canonical] = (int) $row->id;
        }

        // MySQL commits DDL implicitly. These guards make a retry safe if a
        // deployment is interrupted between the table and column operations.
        if (! Schema::hasTable('registration_otps')) {
            Schema::create('registration_otps', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('phone_number', 20);
                $table->string('otp');
                $table->unsignedTinyInteger('attempts')->default(0);
                // DATETIME avoids legacy MySQL/MariaDB treating the first
                // TIMESTAMP column as ON UPDATE CURRENT_TIMESTAMP.
                $table->dateTime('expires_at');
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('used_at')->nullable();
                $table->timestamp('locked_at')->nullable();
                $table->timestamps();

                $table->index(['phone_number', 'sent_at']);
            });
        }

        if (! Schema::hasColumn('users', 'phone_verified_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('phone_verified_at')->nullable()->after('phone_number');
            });
        }

        // Phone verification is a new requirement. Preserve access for every
        // account that existed before this migration; only later registrations
        // must complete the OTP challenge. An account created by an old app
        // instance during rollout can still sign in and request its first code.
        foreach ($canonicalPhones as $canonical => $userId) {
            DB::table('users')->where('id', $userId)->update(['phone_number' => $canonical]);
        }

        DB::table('users')->whereNull('phone_verified_at')->update(['phone_verified_at' => now()]);

        if (! Schema::hasIndex('users', 'users_phone_number_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('phone_number', 'users_phone_number_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_otps');

        if (Schema::hasIndex('users', 'users_phone_number_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_phone_number_unique');
            });
        }

        if (Schema::hasColumn('users', 'phone_verified_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('phone_verified_at');
            });
        }
    }
};
