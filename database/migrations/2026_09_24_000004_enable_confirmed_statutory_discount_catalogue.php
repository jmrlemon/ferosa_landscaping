<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Eligibility is item-specific under Philippine law. A VATable sale is
        // not automatically eligible for the Senior/PWD statutory discount.
        // Leave existing catalogue classifications unchanged for manual review.
    }

    public function down(): void
    {
        // The up migration does not modify catalogue data.
    }
};
