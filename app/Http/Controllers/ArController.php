<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArController extends Controller
{
    /**
     * GET /api/ar/products
     *
     * Returns AR-enabled products that are active, not archived, and have
     * an associated PlantModel.
     */
    public function index(): JsonResponse
    {
        $products = Product::arEnabled()
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->with('plantModel:id,product_id,file_path,height_cm,file_size')
            ->get(['id', 'name', 'description', 'image_url', 'price', 'stock_qty', 'category']);

        return response()->json($products->map(fn (Product $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'description' => $p->description,
            'price' => (float) $p->price,
            'thumbnail_url' => $p->image_url,
            'model_url' => '/api/ar/products/'.$p->id.'/model',
            'height_cm' => (float) $p->plantModel->height_cm,
            'category' => $p->category,
            'in_stock' => $p->inStock(),
        ]));
    }

    /**
     * GET /api/ar/products/{product}/model
     *
     * Streams the GLB/GLTF file from storage.
     * Returns 404 if the product has no associated PlantModel.
     */
    public function downloadModel(Product $product): StreamedResponse|JsonResponse
    {
        $plantModel = $product->plantModel;

        if (! $plantModel) {
            return response()->json([
                'error' => 'No AR model available for this product',
            ], 404);
        }

        $filePath = $plantModel->file_path;

        if (! Storage::disk('public')->exists($filePath)) {
            return response()->json([
                'error' => 'Model file not found',
            ], 404);
        }

        $fileName = $plantModel->file_name;
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $mimeType = $extension === 'glb' ? 'model/gltf-binary' : 'model/gltf+json';

        return Storage::disk('public')->download($filePath, $fileName, [
            'Content-Type' => $mimeType,
        ]);
    }

    /**
     * GET /api/ar/products/{product}/model-info
     *
     * Returns model metadata for cache freshness checks.
     * Returns 404 if the product has no associated PlantModel.
     */
    public function modelInfo(Product $product): JsonResponse
    {
        $plantModel = $product->plantModel;

        if (! $plantModel) {
            return response()->json([
                'error' => 'No AR model available for this product',
            ], 404);
        }

        return response()->json([
            'updated_at' => $plantModel->updated_at->toIso8601String(),
            'file_size' => $plantModel->file_size,
        ]);
    }

    /**
     * POST /api/ar/cart/add
     *
     * Adds a product to the user's cart (pending order).
     * Validates product_id, quantity, and stock availability.
     */
    public function addToCart(Request $request, CartService $cart): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $summary = $cart->add(
            $request->user(),
            (int) $validated['product_id'],
            (int) $validated['quantity'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Item added to cart',
            ...$summary,
        ]);
    }
}
