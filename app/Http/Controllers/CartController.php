<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request, CartService $cart): JsonResponse
    {
        return response()->json($cart->summary($request->user()));
    }

    public function store(Request $request, CartService $cart): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:999'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Item added to cart',
            ...$cart->add($request->user(), (int) $data['product_id'], (int) ($data['quantity'] ?? 1)),
        ]);
    }

    public function update(Request $request, Product $product, CartService $cart): JsonResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:999']]);

        return response()->json($cart->setQuantity($request->user(), $product->id, (int) $data['quantity']));
    }

    public function destroy(Request $request, Product $product, CartService $cart): JsonResponse
    {
        return response()->json($cart->setQuantity($request->user(), $product->id, 0));
    }

    public function sync(Request $request, CartService $cart): JsonResponse
    {
        $data = $request->validate([
            'items' => ['present', 'array', 'max:100'],
            'items.*.id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        return response()->json($cart->merge($request->user(), $data['items']));
    }
}
