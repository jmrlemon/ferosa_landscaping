<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CustomerSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints consumed only by the Android app.
 *
 * Authentication is the same cookie-session model the AR endpoints already use
 * (see routes/api.php) - the app's WebViewCookieJar forwards the WebView's
 * Laravel session, so there is no token to leak into a deep link.
 */
class MobileController extends Controller
{
    /**
     * The account snapshot behind the native Home screen, the navigation
     * badges, and the background poll that raises local notifications.
     */
    public function summary(Request $request, CustomerSummaryService $summary): JsonResponse
    {
        return response()->json($summary->forUser($request->user()));
    }

    /**
     * The estimator rate card.
     *
     * The app ships a bundled copy as an offline fallback, so this endpoint is
     * what lets a price change reach existing installs without an app release.
     * Everything returned here is already public on the /estimator web page.
     */
    public function estimatorRates(): JsonResponse
    {
        $rateCard = (array) config('estimator');
        $rateCard['estimate_products'] = Product::query()
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->where('stock_qty', '>', 0)
            ->orderBy('category')
            ->orderBy('name')
            ->take(12)
            ->get([
                'id',
                'name',
                'category',
                'price',
                'stock_qty',
                'sale_unit',
                'coverage_sqm_per_unit',
                'coverage_waste_percent',
            ])
            ->map(function (Product $product): array {
                $supportsAreaCoverage = $product->supportsAreaCoverage();

                return [
                    'id' => (int) $product->id,
                    'name' => $product->name,
                    'category' => $product->category,
                    'price' => (float) $product->price,
                    'stock_qty' => (int) $product->stock_qty,
                    'sale_unit' => $supportsAreaCoverage ? $product->sale_unit : null,
                    'coverage_sqm_per_unit' => $supportsAreaCoverage
                        ? (float) $product->coverage_sqm_per_unit
                        : null,
                    'coverage_waste_percent' => $supportsAreaCoverage
                        ? (float) ($product->coverage_waste_percent ?? 10)
                        : null,
                ];
            })
            ->all();

        return response()->json($rateCard);
    }
}
