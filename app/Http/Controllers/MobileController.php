<?php

namespace App\Http\Controllers;

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
        return response()->json(config('estimator'));
    }
}
