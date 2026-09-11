<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function summary(User $user): array
    {
        $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        $cart->load(['items.product']);

        $items = $cart->items
            ->filter(fn (CartItem $item) => $item->product
                && $item->product->is_active
                && is_null($item->product->archived_at))
            ->map(function (CartItem $item): array {
                $product = $item->product;

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => (float) $product->price,
                    'qty' => $item->qty,
                    'stock_qty' => (int) $product->stock_qty,
                    'image_url' => $product->image_url,
                ];
            })
            ->values();

        return [
            'items' => $items->all(),
            'cart_count' => (int) $items->sum('qty'),
            'subtotal' => round((float) $items->sum(fn (array $item) => $item['price'] * $item['qty']), 2),
        ];
    }

    public function add(User $user, int $productId, int $quantity = 1): array
    {
        DB::transaction(function () use ($user, $productId, $quantity): void {
            $product = $this->availableProduct($productId, true);
            $cart = Cart::firstOrCreate(['user_id' => $user->id]);
            $item = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            $newQuantity = ($item?->qty ?? 0) + $quantity;
            $this->ensureStock($product, $newQuantity);

            CartItem::updateOrCreate(
                ['cart_id' => $cart->id, 'product_id' => $product->id],
                ['qty' => $newQuantity],
            );
        }, 3);

        return $this->summary($user);
    }

    public function setQuantity(User $user, int $productId, int $quantity): array
    {
        DB::transaction(function () use ($user, $productId, $quantity): void {
            $cart = Cart::firstOrCreate(['user_id' => $user->id]);

            if ($quantity <= 0) {
                $cart->items()->where('product_id', $productId)->delete();

                return;
            }

            $product = $this->availableProduct($productId, true);
            $this->ensureStock($product, $quantity);

            CartItem::updateOrCreate(
                ['cart_id' => $cart->id, 'product_id' => $product->id],
                ['qty' => $quantity],
            );
        }, 3);

        return $this->summary($user);
    }

    /** Merge a legacy browser cart without double-counting existing server items. */
    public function merge(User $user, array $legacyItems): array
    {
        foreach ($legacyItems as $legacyItem) {
            $productId = (int) ($legacyItem['id'] ?? 0);
            $quantity = max(1, (int) ($legacyItem['qty'] ?? 1));
            if ($productId <= 0) {
                continue;
            }

            $cart = Cart::firstOrCreate(['user_id' => $user->id]);
            $existing = $cart->items()->where('product_id', $productId)->value('qty') ?? 0;

            try {
                $this->setQuantity($user, $productId, max($existing, $quantity));
            } catch (ValidationException) {
                // Ignore products that were removed or became unavailable.
            }
        }

        return $this->summary($user);
    }

    public function clear(User $user): void
    {
        $user->cart?->items()->delete();
    }

    private function availableProduct(int $productId, bool $lock = false): Product
    {
        $query = Product::query()
            ->whereKey($productId)
            ->where('is_active', true)
            ->whereNull('archived_at');

        if ($lock) {
            $query->lockForUpdate();
        }

        $product = $query->first();
        if (! $product) {
            throw ValidationException::withMessages(['product_id' => 'This product is no longer available.']);
        }

        return $product;
    }

    private function ensureStock(Product $product, int $quantity): void
    {
        if ($product->stock_qty < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => $product->stock_qty <= 0
                    ? "{$product->name} is out of stock."
                    : "Only {$product->stock_qty} unit(s) of {$product->name} are available.",
            ]);
        }
    }
}
