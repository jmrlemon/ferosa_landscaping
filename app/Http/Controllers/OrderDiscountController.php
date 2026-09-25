<?php

namespace App\Http\Controllers;

use App\Models\DiscountApplication;
use App\Models\Order;
use App\Services\OrderDiscountService;
use App\Services\PhilippineDiscountCalculator;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderDiscountController extends Controller
{
    public function __construct(private readonly OrderDiscountService $discounts) {}

    public function store(Request $request, Order $order): RedirectResponse
    {
        if ($order->discountRequest()->where('status', 'pending')->exists()) {
            return back()->withErrors([
                'discount_request' => 'Use the customer discount request review form to verify this order.',
            ]);
        }

        $data = $request->validate([
            'scheme' => ['required', Rule::in([
                PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
                PhilippineDiscountCalculator::SCHEME_BNPC_5,
                PhilippineDiscountCalculator::SCHEME_VOLUNTARY_20,
            ])],
            'beneficiary_type' => ['required', Rule::in(DiscountApplication::BENEFICIARY_TYPES)],
            'eligibility_confirmed' => ['accepted'],
        ], [
            'eligibility_confirmed.accepted' => 'Confirm that the customer ID and selected discount were checked.',
        ]);

        $discount = $this->discounts->apply($order, $data, (int) $request->user()->id);

        Audit::log($request, 'order.discount.apply', $discount, null, Audit::snapshot($discount, [
            'scheme',
            'beneficiary_type',
            'gross_total',
            'vat_removed',
            'discount_base',
            'discount_rate',
            'discount_amount',
            'net_total',
            'verified_by',
            'verified_at',
        ]));

        return redirect()->route('admin.orders.show', $order)
            ->with('status', 'Discount verified and applied. The invoice total was recalculated.');
    }

    public function destroy(Request $request, Order $order, DiscountApplication $discount): RedirectResponse
    {
        $data = $request->validate([
            'void_reason' => ['required', 'string', 'max:255'],
        ]);

        $before = Audit::snapshot($discount, ['status', 'voided_by', 'voided_at', 'void_reason']);

        $this->discounts->void(
            $order,
            $discount,
            (int) $request->user()->id,
            $data['void_reason'],
        );

        $discount->refresh();
        Audit::log($request, 'order.discount.void', $discount, $before, Audit::snapshot($discount, [
            'status', 'voided_by', 'voided_at', 'void_reason',
        ]));

        return redirect()->route('admin.orders.show', $order)
            ->with('status', 'Discount voided and the original order total restored.');
    }
}
