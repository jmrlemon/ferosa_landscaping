<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\DiscountApplication;
use App\Models\Order;
use App\Models\Payment;
use App\Services\BillingService;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Invoices and the payment ledger.
 *
 * Invoice views are readable by the customer who owns the record and by staff.
 * Recording payments is admin-only, matching the billing tab.
 */
class BillingController extends Controller
{
    public function __construct(private readonly BillingService $billing) {}

    public function orderInvoice(Request $request, Order $order): View
    {
        $this->authorizeInvoiceView($request, (int) $order->user_id);

        $order->load([
            'user',
            'orderItems',
            'activeDiscount.verifiedBy',
            'paymentHistory.recordedBy',
            'paymentHistory.voidedBy',
            'activeRefunds.processedBy',
        ]);

        return view('invoice', $this->invoiceData($order, 'order'));
    }

    public function appointmentInvoice(Request $request, Appointment $appointment): View
    {
        $this->authorizeInvoiceView($request, (int) $appointment->user_id);

        $appointment->load([
            'user',
            'serviceType',
            'activeDiscount.verifiedBy',
            'paymentHistory.recordedBy',
            'paymentHistory.voidedBy',
        ]);

        return view('invoice', $this->invoiceData($appointment, 'appointment'));
    }

    public function storeOrderPayment(Request $request, Order $order): RedirectResponse
    {
        return $this->storePayment($request, $order, route('admin.orders.show', $order));
    }

    public function storeAppointmentPayment(Request $request, Appointment $appointment): RedirectResponse
    {
        return $this->storePayment($request, $appointment, route('admin.appointments.show', $appointment));
    }

    /**
     * @param  Order|Appointment  $payable
     */
    private function storePayment(Request $request, $payable, string $redirectTo): RedirectResponse
    {
        if ($payable->discountRequest()->where('status', 'pending')->exists()) {
            return back()->withErrors([
                'discount_request' => 'Resolve the customer discount request before recording payment.',
            ]);
        }

        $balance = $this->billing->balanceDue($payable);

        $data = $request->validate([
            // Cap at the outstanding balance so the ledger cannot exceed the invoice.
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.max(0.01, $balance)],
            'method' => ['required', 'string', Rule::in(Payment::METHODS)],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:now'],
        ], [
            'amount.max' => 'That is more than the outstanding balance of PHP '.number_format($balance, 2).'.',
        ]);

        if ($balance <= 0) {
            return back()->withErrors(['amount' => 'This record is already fully settled.']);
        }

        $payment = $this->billing->record($payable, [
            'amount' => $data['amount'],
            'method' => $data['method'],
            'reference' => $data['method'] === 'cash' ? null : ($data['reference'] ?? null),
            'notes' => $data['notes'] ?? null,
            'paid_at' => $data['paid_at'] ?? now(),
            'recorded_by' => $request->user()->id,
        ]);

        Audit::log($request, 'payment.record', $payment, null, Audit::snapshot($payment, ['payable_type', 'payable_id', 'amount', 'method', 'reference', 'paid_at']));

        $payable->refresh();
        $remaining = $this->billing->balanceDue($payable);

        $message = $remaining > 0
            ? 'Payment recorded. Balance remaining: PHP '.number_format($remaining, 2).'.'
            : 'Payment recorded. This record is now fully paid.';

        return redirect()->to($redirectTo)->with('status', $message);
    }

    /**
     * Staff can open any invoice; a customer can only open their own.
     */
    private function authorizeInvoiceView(Request $request, int $ownerId): void
    {
        $user = $request->user();

        if ($user->isStaffOrAdmin()) {
            return;
        }

        abort_unless($ownerId === (int) $user->id, 403);
    }

    /**
     * @param  Order|Appointment  $payable
     * @return array<string, mixed>
     */
    private function invoiceData($payable, string $kind): array
    {
        $isOrder = $payable instanceof Order;

        return [
            'kind' => $kind,
            'payable' => $payable,
            'invoiceNumber' => $payable->invoiceNumber(),
            'lines' => $this->invoiceLines($payable, $kind),
            'totalBilled' => $this->billing->totalBilled($payable),
            'totalPaid' => $this->billing->totalPaid($payable),
            'balanceDue' => $this->billing->balanceDue($payable),
            'discount' => $payable->activeDiscount,
            'totalRefunded' => $isOrder ? $this->billing->totalRefunded($payable) : 0.0,
            'netPaid' => $isOrder ? $this->billing->netPaid($payable) : $this->billing->totalPaid($payable),
        ];
    }

    /**
     * Normalise orders and appointments into the same line-item shape so one
     * invoice template renders both.
     *
     * @param  Order|Appointment  $payable
     * @return array<int, array{description: string, qty: int, unit: float, amount: float}>
     */
    private function invoiceLines($payable, string $kind): array
    {
        if ($kind === 'appointment') {
            // appointment_amount is reduced after approval; the application
            // retains the original gross value for a transparent invoice.
            $activeDiscount = $payable->getRelation('activeDiscount');
            $amount = (float) ($activeDiscount instanceof DiscountApplication
                ? $activeDiscount->gross_total
                : $payable->appointment_amount);

            return [[
                'description' => $payable->serviceType->name ?? 'Landscaping service',
                'qty' => 1,
                'unit' => $amount,
                'amount' => $amount,
            ]];
        }

        $lines = [];

        foreach ((array) ($payable->items ?? []) as $line) {
            $qty = (int) ($line['qty'] ?? $line['quantity'] ?? 1);
            $unit = (float) ($line['price'] ?? 0);

            $lines[] = [
                'description' => (string) ($line['name'] ?? 'Item'),
                'qty' => $qty,
                'unit' => $unit,
                'amount' => $unit * $qty,
            ];
        }

        return $lines;
    }
}
