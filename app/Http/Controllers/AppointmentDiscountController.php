<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\DiscountApplication;
use App\Services\AppointmentDiscountService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AppointmentDiscountController extends Controller
{
    public function destroy(
        Request $request,
        Appointment $appointment,
        DiscountApplication $discount,
        AppointmentDiscountService $discounts
    ): RedirectResponse {
        $data = $request->validate([
            'void_reason' => ['required', 'string', 'max:255'],
        ]);

        $before = Audit::snapshot($discount, ['status', 'voided_by', 'voided_at', 'void_reason']);

        $discounts->void(
            $appointment,
            $discount,
            (int) $request->user()->id,
            $data['void_reason'],
        );

        $discount->refresh();
        Audit::log($request, 'appointment.discount.void', $discount, $before, Audit::snapshot($discount, [
            'status', 'voided_by', 'voided_at', 'void_reason',
        ]));

        return redirect()->route('admin.appointments.show', $appointment)
            ->with('status', 'Discount voided and the original appointment fee restored.');
    }
}
