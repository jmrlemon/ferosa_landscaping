<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The only place `products.stock_qty` is allowed to change.
 *
 * Every adjustment is applied under a row lock and written to `stock_movements`
 * in the same transaction, so the ledger and the stock level cannot drift apart.
 * Callers pass a signed delta and a reason; the service resolves the new level.
 */
class InventoryService
{
    /**
     * Apply a signed change to a product's stock and log why it happened.
     *
     * @param  int  $quantity  positive to add stock, negative to remove it
     * @param  array{unit_cost?: float|null, supplier?: string|null, reference?: string|null, note?: string|null, user_id?: int|null, allow_negative?: bool}  $meta
     */
    public function record(Product $product, string $type, int $quantity, array $meta = []): StockMovement
    {
        if ($quantity === 0) {
            throw new RuntimeException('A stock movement must change the quantity.');
        }

        $movement = DB::transaction(function () use ($product, $type, $quantity, $meta): StockMovement {
            /** @var Product $locked */
            $locked = Product::query()->lockForUpdate()->findOrFail($product->getKey());

            $after = (int) $locked->stock_qty + $quantity;

            if ($after < 0 && ! ($meta['allow_negative'] ?? false)) {
                throw new RuntimeException(
                    "Only {$locked->stock_qty} unit(s) of {$locked->name} are in stock."
                );
            }

            $locked->forceFill(['stock_qty' => $after])->save();

            $movement = StockMovement::query()->create([
                'product_id' => $locked->getKey(),
                'type' => $type,
                'quantity' => $quantity,
                'quantity_after' => $after,
                'unit_cost' => $meta['unit_cost'] ?? null,
                'supplier' => $meta['supplier'] ?? null,
                'reference' => $meta['reference'] ?? null,
                'note' => $meta['note'] ?? null,
                'user_id' => $meta['user_id'] ?? null,
            ]);

            $product->setAttribute('stock_qty', $after);

            return $movement;
        }, 3);

        Cache::forget('shop_products_active');

        return $movement;
    }

    /**
     * Stock leaving because a customer bought it.
     */
    public function recordSale(Product $product, int $quantity, string $reference): StockMovement
    {
        return $this->record($product, StockMovement::TYPE_SALE, -abs($quantity), [
            'reference' => $reference,
            'note' => 'Sold on '.$reference.'.',
        ]);
    }

    /**
     * Stock coming back because an order was cancelled.
     */
    public function recordReturn(Product $product, int $quantity, string $reference): StockMovement
    {
        return $this->record($product, StockMovement::TYPE_RETURN, abs($quantity), [
            'reference' => $reference,
            'note' => 'Returned to stock when '.$reference.' was cancelled.',
        ]);
    }
}
