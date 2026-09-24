<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\DiscountApplication;
use App\Models\DiscountRequest;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class DiscountRequestReviewService
{
    public function __construct(
        private readonly OrderDiscountService $orderDiscounts,
        private readonly PhilippineDiscountCalculator $calculator,
        private readonly PhilippineDiscountRules $rules,
    ) {}

    /**
     * @param  array{scheme:string}  $attributes
     */
    public function approve(DiscountRequest $discountRequest, array $attributes, int $adminId): DiscountApplication
    {
        if (! Storage::disk('local')->exists($discountRequest->evidence_path)) {
            throw ValidationException::withMessages([
                'discount_evidence' => 'The submitted ID copy is no longer available. Ask the customer to submit it again.',
            ]);
        }

        return DB::transaction(function () use ($discountRequest, $attributes, $adminId): DiscountApplication {
            $lockedRequest = DiscountRequest::query()
                ->with('requestable')
                ->lockForUpdate()
                ->findOrFail($discountRequest->id);

            if ($lockedRequest->status !== DiscountRequest::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'discount_request' => 'This discount request has already been reviewed.',
                ]);
            }

            $payable = $lockedRequest->requestable;

            if ($payable instanceof Order) {
                $application = $this->orderDiscounts->apply($payable, [
                    'scheme' => $attributes['scheme'],
                    'beneficiary_type' => $lockedRequest->beneficiary_type,
                ], $adminId);
            } elseif ($payable instanceof Appointment) {
                $application = $this->applyToAppointment(
                    $payable,
                    $lockedRequest,
                    $attributes,
                    $adminId,
                );
            } else {
                abort(404);
            }

            $application->forceFill(['discount_request_id' => $lockedRequest->id])->save();

            $lockedRequest->forceFill([
                'status' => DiscountRequest::STATUS_APPROVED,
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ])->save();

            return $application;
        }, 3);
    }

    public function reject(DiscountRequest $discountRequest, string $reason, int $adminId): void
    {
        DB::transaction(function () use ($discountRequest, $reason, $adminId): void {
            $lockedRequest = DiscountRequest::query()->lockForUpdate()->findOrFail($discountRequest->id);

            if ($lockedRequest->status !== DiscountRequest::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'discount_request' => 'This discount request has already been reviewed.',
                ]);
            }

            $lockedRequest->forceFill([
                'status' => DiscountRequest::STATUS_REJECTED,
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ])->save();
        }, 3);
    }

    /**
     * @param  array{scheme:string}  $attributes
     */
    private function applyToAppointment(
        Appointment $appointment,
        DiscountRequest $discountRequest,
        array $attributes,
        int $adminId,
    ): DiscountApplication {
        $scheme = $attributes['scheme'];
        $this->rules->assertSchemeEnabled($scheme);

        if ($scheme !== PhilippineDiscountCalculator::SCHEME_STATUTORY_20
            || $appointment->discount_scheme !== $scheme) {
            throw ValidationException::withMessages([
                'scheme' => 'Only an appointment service specifically marked for the 20% statutory discount is eligible.',
            ]);
        }

        $lockedAppointment = Appointment::query()
            ->with('serviceType')
            ->lockForUpdate()
            ->findOrFail($appointment->id);

        if (! in_array($lockedAppointment->status, ['scheduled', 'confirmed'], true)) {
            throw ValidationException::withMessages([
                'discount_request' => 'Discounts can only be applied to scheduled or confirmed appointments.',
            ]);
        }

        if ($lockedAppointment->payment_status !== 'unpaid'
            || $lockedAppointment->activePayments()->exists()) {
            throw ValidationException::withMessages([
                'discount_request' => 'A discount cannot be applied after a payment has been recorded.',
            ]);
        }

        if ($lockedAppointment->activeDiscount()->exists()) {
            throw ValidationException::withMessages([
                'discount_request' => 'This appointment already has an active discount.',
            ]);
        }

        try {
            $calculation = $this->calculator->calculate(
                $scheme,
                [[
                    'price' => $lockedAppointment->appointment_amount,
                    'qty' => 1,
                    'discount_scheme' => $lockedAppointment->discount_scheme,
                ]],
                vatRegistered: (bool) config('discounts.vat_registered', false),
                pricesIncludeVat: (bool) config('discounts.prices_include_vat', true),
                vatRatePercent: (int) config('discounts.vat_rate_percent', 12),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'discount_request' => $exception->getMessage(),
            ]);
        }

        if ((float) $calculation['discount_amount'] <= 0) {
            throw ValidationException::withMessages([
                'discount_request' => 'No eligible appointment fee remains for this discount.',
            ]);
        }

        $application = $lockedAppointment->discountApplications()->create([
            ...$calculation,
            'discount_request_id' => $discountRequest->id,
            'beneficiary_type' => $discountRequest->beneficiary_type,
            'status' => DiscountApplication::STATUS_APPROVED,
            'legal_basis_version' => 'RA 9994 / RA 10754 / RR 5-2017',
            'metadata' => [
                'vat_registered' => (bool) config('discounts.vat_registered', false),
                'prices_include_vat' => (bool) config('discounts.prices_include_vat', true),
                'vat_rate_percent' => (int) config('discounts.vat_rate_percent', 12),
                'service_type_id' => $lockedAppointment->service_type_id,
            ],
            'verified_by' => $adminId,
            'verified_at' => now(),
        ]);

        $lockedAppointment->forceFill([
            'appointment_amount' => $calculation['net_total'],
        ])->save();

        return $application;
    }
}
