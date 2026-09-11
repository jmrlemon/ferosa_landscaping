<?php

use App\Models\StockMovement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            // Signed: positive is stock in, negative is stock out. quantity_after is
            // the resulting stock_qty so the ledger can be read without replaying it.
            $table->integer('quantity');
            $table->integer('quantity_after');
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->string('supplier')->nullable();
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
            $table->index('type');
        });

        // Existing products have a stock_qty with no history behind it. Record the
        // current level as an opening balance so the ledger reconciles from day one.
        $now = now();

        DB::table('products')
            ->orderBy('id')
            ->get(['id', 'stock_qty'])
            ->each(function (object $product) use ($now): void {
                DB::table('stock_movements')->insert([
                    'product_id' => $product->id,
                    'type' => StockMovement::TYPE_OPENING,
                    'quantity' => (int) $product->stock_qty,
                    'quantity_after' => (int) $product->stock_qty,
                    'note' => 'Opening balance recorded when stock tracking was introduced.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
