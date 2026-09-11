<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove Google/Facebook OAuth columns that are not used in this system.
     * Also restore password to non-nullable (social login is removed).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop unique indexes first before dropping columns
            $table->dropUnique(['google_id']);
            $table->dropUnique(['facebook_id']);
            $table->dropColumn(['google_id', 'facebook_id']);

            // Restore password to required (no longer nullable for OAuth)
            $table->string('password')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('role');
            $table->string('facebook_id')->nullable()->unique()->after('google_id');
            $table->string('password')->nullable()->change();
        });
    }
};
