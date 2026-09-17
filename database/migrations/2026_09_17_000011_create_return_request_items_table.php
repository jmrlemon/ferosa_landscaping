<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity_claimed');
            $table->string('issue_type', 40);
            $table->text('issue_description');
            $table->string('preferred_resolution', 30);
            $table->string('resolution', 30)->nullable();
            $table->unsignedInteger('replacement_quantity')->default(0);
            $table->decimal('refund_amount', 10, 2)->default(0);
            $table->text('decision_note')->nullable();
            $table->string('disposition', 30)->default('not_required');
            $table->unsignedInteger('restocked_quantity')->default(0);
            $table->timestamps();

            $table->unique(['return_request_id', 'order_item_id'], 'return_request_item_unique');
            $table->index(['order_item_id', 'resolution']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_request_items');
    }
};
