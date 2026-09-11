<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use App\Services\InventoryService;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Manual stock movements and the per-product history.
 *
 * Sales and cancellation returns are written by the order flow; everything an
 * admin types by hand comes through here.
 */
class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * Stock ledger across all products, newest first.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'type' => ['nullable', 'string', Rule::in([
                StockMovement::TYPE_OPENING,
                StockMovement::TYPE_RESTOCK,
                StockMovement::TYPE_SALE,
                StockMovement::TYPE_RETURN,
                StockMovement::TYPE_WASTAGE,
                StockMovement::TYPE_CORRECTION,
            ])],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
        ]);

        $movements = StockMovement::query()
            ->with(['product:id,name,category', 'user:id,name'])
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['product_id'] ?? null, fn ($q, $id) => $q->where('product_id', $id))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.inventory', [
            'movements' => $movements,
            'products' => Product::query()
                ->whereNull('archived_at')
                ->orderBy('name')
                ->get(['id', 'name', 'stock_qty', 'category']),
            'filters' => $filters,
            'lowStock' => Product::query()
                ->whereNull('archived_at')
                ->where('stock_qty', '<=', 5)
                ->orderBy('stock_qty')
                ->get(['id', 'name', 'stock_qty']),
        ]);
    }

    /**
     * History for one product, with its current level.
     */
    public function show(Product $product): View
    {
        return view('admin.inventory-product', [
            'product' => $product,
            'movements' => $product->stockMovements()->with('user:id,name')->paginate(25),
        ]);
    }

    /**
     * Stock arriving from a supplier.
     */
    public function restock(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $before = Audit::snapshot($product, ['stock_qty']);

        $movement = $this->inventory->record($product, StockMovement::TYPE_RESTOCK, (int) $data['quantity'], [
            'supplier' => $data['supplier'] ?? null,
            'unit_cost' => $data['unit_cost'] ?? null,
            'note' => $data['note'] ?? null,
            'user_id' => $request->user()->id,
        ]);

        $product->refresh();
        Audit::log($request, 'inventory.restock', $product, $before, Audit::snapshot($product, ['stock_qty']));

        return back()->with('status', "Restocked {$data['quantity']} unit(s) of {$product->name}. Stock is now {$movement->quantity_after}.");
    }

    /**
     * Stock written off (wastage) or corrected after a physical count.
     *
     * The two read differently on purpose. Wastage is entered as the quantity
     * lost, because that is what the admin observed. A correction is entered as
     * the level actually counted, because after counting a shelf that is the
     * number in hand - making them subtract invites arithmetic mistakes.
     */
    public function adjust(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', Rule::in([StockMovement::TYPE_WASTAGE, StockMovement::TYPE_CORRECTION])],
            'quantity' => ['required', 'integer', 'min:0', 'max:100000'],
            'note' => ['required', 'string', 'max:1000'],
        ], [
            'note.required' => 'Record why the stock is changing.',
        ]);

        $submitted = (int) $data['quantity'];

        if ($data['type'] === StockMovement::TYPE_WASTAGE) {
            if ($submitted === 0) {
                return back()->withErrors(['quantity' => 'Enter how many units were lost.'])->withInput();
            }

            $quantity = -$submitted;
        } else {
            $quantity = $submitted - (int) $product->stock_qty;

            if ($quantity === 0) {
                return back()->withErrors([
                    'quantity' => 'That is already the recorded level, so there is nothing to correct.',
                ])->withInput();
            }
        }

        $before = Audit::snapshot($product, ['stock_qty']);

        try {
            $movement = $this->inventory->record($product, $data['type'], $quantity, [
                'note' => $data['note'],
                'user_id' => $request->user()->id,
                // Neither path may drive stock below zero: a counted level is
                // never negative, and you cannot lose more than you hold.
                'allow_negative' => false,
            ]);
        } catch (RuntimeException $e) {
            return back()->withErrors(['quantity' => $e->getMessage()])->withInput();
        }

        $product->refresh();
        Audit::log($request, 'inventory.'.$data['type'], $product, $before, Audit::snapshot($product, ['stock_qty']));

        return back()->with('status', "{$movement->typeLabel()} recorded for {$product->name}. Stock is now {$movement->quantity_after}.");
    }
}
