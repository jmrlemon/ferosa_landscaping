<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\DiscountRequest;
use App\Models\Order;
use App\Services\DiscountRequestReviewService;
use App\Services\PhilippineDiscountCalculator;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DiscountRequestReviewController extends Controller
{
    public function approve(
        Request $request,
        DiscountRequest $discountRequest,
        DiscountRequestReviewService $review,
    ): RedirectResponse {
        $data = $request->validate([
            'scheme' => ['required', Rule::in([
                PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
                PhilippineDiscountCalculator::SCHEME_BNPC_5,
            ])],
            'eligibility_confirmed' => ['accepted'],
        ], [
            'eligibility_confirmed.accepted' => 'Confirm that the customer and eligible goods or service were checked.',
        ]);

        $before = Audit::snapshot($discountRequest, ['beneficiary_type', 'status']);
        $application = $review->approve($discountRequest, $data, (int) $request->user()->id);
        $discountRequest->refresh();

        Audit::log($request, 'discount.request.approve', $discountRequest, $before, [
            ...Audit::snapshot($discountRequest, [
                'beneficiary_type', 'status', 'reviewed_by', 'reviewed_at',
            ]),
            'discount_application_id' => $application->id,
            'scheme' => $application->scheme,
            'discount_amount' => $application->discount_amount,
            'net_total' => $application->net_total,
        ]);

        return $this->redirectToPayable($discountRequest)
            ->with('status', 'ID verified and the eligible discount was applied.');
    }

    public function reject(Request $request, DiscountRequest $discountRequest, DiscountRequestReviewService $review): RedirectResponse
    {
        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:255'],
        ]);

        $before = Audit::snapshot($discountRequest, ['beneficiary_type', 'status']);
        $review->reject($discountRequest, $data['rejection_reason'], (int) $request->user()->id);
        $discountRequest->refresh();

        Audit::log($request, 'discount.request.reject', $discountRequest, $before, Audit::snapshot($discountRequest, [
            'beneficiary_type', 'status', 'reviewed_by', 'reviewed_at', 'rejection_reason',
        ]));

        return $this->redirectToPayable($discountRequest)
            ->with('status', 'Discount request rejected. The original total remains due.');
    }

    public function evidence(Request $request, DiscountRequest $discountRequest): StreamedResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless(Storage::disk('local')->exists($discountRequest->evidence_path), 404);

        return Storage::disk('local')->response($discountRequest->evidence_path, null, [
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function redirectToPayable(DiscountRequest $discountRequest): RedirectResponse
    {
        $payable = $discountRequest->requestable;

        return match (true) {
            $payable instanceof Order => redirect()->route('admin.orders.show', $payable),
            $payable instanceof Appointment => redirect()->route('admin.appointments.show', $payable),
            default => abort(404),
        };
    }
}
