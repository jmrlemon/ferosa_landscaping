<?php

namespace App\Services;

use App\Models\DiscountApplication;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class OrderDiscountService
{
    public function __construct(
        private readonly PhilippineDiscountCalculator $calculator,
        private readonly PhilippineDiscountRules $rules,
    ) {}

    /**
     * @param  array{scheme: string, beneficiary_type: string}  $attributes
     */
    public function apply(Order $order, array $attributes, int $verifiedBy): DiscountApplication
    {
        $this->rules->assertSchemeEnabled($attributes['scheme']);

        return DB::transaction(function () use ($order, $attributes, $verifiedBy): DiscountApplication {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()
                ->with('orderItems')
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($lockedOrder->status !== 'pending') {
                throw ValidationException::withMessages([
                    'discount' => 'Discounts can only be applied while the order is pending.',
                ]);
            }

            if ($lockedOrder->payment_status !== 'unpaid') {
                throw ValidationException::withMessages([
                    'discount' => 'Resolve or reset the current payment review before changing the invoice total.',
                ]);
            }

            if ($lockedOrder->activePayments()->exists()) {
                throw ValidationException::withMessages([
                    'discount' => 'A discount cannot be applied after a payment has been recorded.',
                ]);
            }

            if ($lockedOrder->activeDiscount()->exists()) {
                throw ValidationException::withMessages([
                    'discount' => 'This order already has an active discount.',
                ]);
            }

            $lines = $lockedOrder->orderItems->map(static fn ($item): array => [
                'price' => $item->price,
                'qty' => $item->qty,
                'discount_scheme' => $item->discount_scheme,
            ])->values()->all();

            if ($lines === []) {
                $lines = array_values((array) ($lockedOrder->items ?? []));
            }

            $weeklyLimit = (string) config('discounts.bnpc_weekly_limit', '2500.00');
            if ($attributes['scheme'] === PhilippineDiscountCalculator::SCHEME_BNPC_5) {
                // Serialize cap consumption per customer so two simultaneous
                // orders cannot both spend the same remaining weekly amount.
                User::query()->lockForUpdate()->findOrFail($lockedOrder->user_id);
            }

            $priorBnpcBase = $attributes['scheme'] === PhilippineDiscountCalculator::SCHEME_BNPC_5
                ? $this->bnpcBaseUsedThisWeek($lockedOrder)
                : 0.0;
            $weeklyRemaining = number_format(max(0, (float) $weeklyLimit - $priorBnpcBase), 2, '.', '');

            try {
                $calculation = $this->calculator->calculate(
                    $attributes['scheme'],
                    $lines,
                    vatRegistered: (bool) config('discounts.vat_registered', false),
                    pricesIncludeVat: (bool) config('discounts.prices_include_vat', true),
                    bnpcWeeklyRemaining: $weeklyRemaining,
                    vatRatePercent: (int) config('discounts.vat_rate_percent', 12),
                );
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages([
                    'discount' => $exception->getMessage(),
                ]);
            }

            if ((float) $calculation['discount_amount'] <= 0) {
                throw ValidationException::withMessages([
                    'discount' => 'No discount remains available for this order.',
                ]);
            }

            $discount = $lockedOrder->discountApplications()->create([
                ...$calculation,
                'beneficiary_type' => $attributes['beneficiary_type'],
                'status' => DiscountApplication::STATUS_APPROVED,
                'legal_basis_version' => $this->legalBasis($attributes['scheme']),
                'metadata' => [
                    'vat_registered' => (bool) config('discounts.vat_registered', false),
                    'prices_include_vat' => (bool) config('discounts.prices_include_vat', true),
                    'vat_rate_percent' => (int) config('discounts.vat_rate_percent', 12),
                    'bnpc_weekly_limit' => $weeklyLimit,
                    'bnpc_prior_base' => number_format($priorBnpcBase, 2, '.', ''),
                ],
                'verified_by' => $verifiedBy,
                'verified_at' => now(),
            ]);

            $lockedOrder->forceFill(['total_amount' => $calculation['net_total']])->save();

            return $discount;
        }, 3);
    }

    public function void(Order $order, DiscountApplication $discount, int $voidedBy, string $reason): void
    {
        DB::transaction(function () use ($order, $discount, $voidedBy, $reason): void {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            /** @var DiscountApplication $lockedDiscount */
            $lockedDiscount = DiscountApplication::query()->lockForUpdate()->findOrFail($discount->id);

            abort_unless(
                $lockedDiscount->discountable_type === Order::class
                && (int) $lockedDiscount->discountable_id === (int) $lockedOrder->id,
                404,
            );

            if ($lockedDiscount->status !== DiscountApplication::STATUS_APPROVED) {
                throw ValidationException::withMessages([
                    'discount' => 'This discount has already been voided.',
                ]);
            }

            if ($lockedOrder->status !== 'pending') {
                throw ValidationException::withMessages([
                    'discount' => 'A discount cannot be voided after the order is confirmed.',
                ]);
            }

            if ($lockedOrder->activePayments()->exists()) {
                throw ValidationException::withMessages([
                    'discount' => 'A discount cannot be voided after a payment has been recorded.',
                ]);
            }

            if ($lockedOrder->payment_status !== 'unpaid') {
                throw ValidationException::withMessages([
                    'discount' => 'Resolve or reset the current payment review before changing the invoice total.',
                ]);
            }

            $lockedDiscount->forceFill([
                'status' => DiscountApplication::STATUS_VOIDED,
                'voided_by' => $voidedBy,
                'voided_at' => now(),
                'void_reason' => $reason,
            ])->save();

            $lockedOrder->forceFill(['total_amount' => $lockedDiscount->gross_total])->save();
        }, 3);
    }

    private function bnpcBaseUsedThisWeek(Order $order): float
    {
        $timezone = (string) config('discounts.timezone', 'Asia/Manila');
        $purchaseAt = $order->created_at === null
            ? CarbonImmutable::now($timezone)
            : CarbonImmutable::instance($order->created_at)->setTimezone($timezone);
        $weekStart = $purchaseAt->startOfWeek();
        $weekEnd = $purchaseAt->endOfWeek();

        return (float) DiscountApplication::query()
            ->where('scheme', PhilippineDiscountCalculator::SCHEME_BNPC_5)
            ->where('status', DiscountApplication::STATUS_APPROVED)
            // Delayed reviews still consume the cap for the week the customer placed the order.
            ->whereHasMorph('discountable', [Order::class], function ($query) use ($order, $weekStart, $weekEnd): void {
                $query->where('user_id', $order->user_id)
                    ->whereBetween('created_at', [$weekStart, $weekEnd]);
            })
            ->sum('discount_base');
    }

    private function legalBasis(string $scheme): string
    {
        return $scheme === PhilippineDiscountCalculator::SCHEME_BNPC_5
            ? 'JAO 24-02 / JMC 01-2022'
            : 'RA 9994 / RA 10754 / RR 5-2017';
    }
}
