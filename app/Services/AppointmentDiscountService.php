<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\DiscountApplication;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentDiscountService
{
    public function void(Appointment $appointment, DiscountApplication $discount, int $voidedBy, string $reason): void
    {
        DB::transaction(function () use ($appointment, $discount, $voidedBy, $reason): void {
            /** @var Appointment $lockedAppointment */
            $lockedAppointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            /** @var DiscountApplication $lockedDiscount */
            $lockedDiscount = DiscountApplication::query()->lockForUpdate()->findOrFail($discount->id);

            abort_unless(
                $lockedDiscount->discountable_type === Appointment::class
                && (int) $lockedDiscount->discountable_id === (int) $lockedAppointment->id,
                404,
            );

            if ($lockedDiscount->status !== DiscountApplication::STATUS_APPROVED) {
                throw ValidationException::withMessages([
                    'discount' => 'This discount has already been voided.',
                ]);
            }

            if (! in_array($lockedAppointment->status, ['scheduled', 'confirmed'], true)) {
                throw ValidationException::withMessages([
                    'discount' => 'An appointment discount cannot be changed after the visit is completed or cancelled.',
                ]);
            }

            if ($lockedAppointment->payment_status !== 'unpaid'
                || $lockedAppointment->activePayments()->exists()) {
                throw ValidationException::withMessages([
                    'discount' => 'A discount cannot be voided after a payment has been recorded.',
                ]);
            }

            $lockedDiscount->forceFill([
                'status' => DiscountApplication::STATUS_VOIDED,
                'voided_by' => $voidedBy,
                'voided_at' => now(),
                'void_reason' => $reason,
            ])->save();

            $lockedAppointment->forceFill([
                'appointment_amount' => $lockedDiscount->gross_total,
            ])->save();
        }, 3);
    }
}
