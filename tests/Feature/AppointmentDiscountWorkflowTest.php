<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\DiscountApplication;
use App\Models\DiscountRequest;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\PhilippineDiscountCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppointmentDiscountWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'discounts.enabled' => true,
            'discounts.statutory_20_enabled' => true,
            'discounts.vat_registered' => true,
            'discounts.prices_include_vat' => true,
            'discounts.vat_rate_percent' => 12,
        ]);
    }

    public function test_admin_can_approve_an_appointment_request_without_recording_an_id_reference(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $appointment = $this->appointment();
        $evidencePath = 'discount-id-evidence/appointment-id.png';
        Storage::disk('local')->put($evidencePath, 'private-id-image');
        $request = $appointment->discountRequest()->create([
            'beneficiary_type' => 'senior',
            'evidence_path' => $evidencePath,
            'status' => DiscountRequest::STATUS_PENDING,
            'requested_by' => $appointment->user_id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.appointments.show', $appointment))
            ->assertOk()
            ->assertDontSee('name="id_reference_last4"', false);

        $this->actingAs($admin)
            ->post(route('admin.discount-requests.approve', $request), [
                'scheme' => PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
                'eligibility_confirmed' => '1',
            ])
            ->assertRedirect(route('admin.appointments.show', $appointment));

        $application = DiscountApplication::query()->firstOrFail();
        $this->assertNull($application->id_reference_last4);
        $this->assertSame('800.00', $appointment->refresh()->appointment_amount);
        $this->assertSame(DiscountRequest::STATUS_APPROVED, $request->refresh()->status);
    }

    public function test_admin_can_void_an_unpaid_appointment_discount_and_restore_the_fee(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $appointment = $this->appointment();
        $discount = $this->approvedDiscount($appointment, $admin);
        $appointment->forceFill(['appointment_amount' => '800.00'])->save();

        $this->actingAs($admin)
            ->get(route('admin.appointments.show', $appointment))
            ->assertOk()
            ->assertDontSee('Void discount');

        $this->actingAs($admin)
            ->delete(route('admin.appointments.discounts.destroy', [$appointment, $discount]), [
                'void_reason' => 'The customer will not use the discount.',
            ])
            ->assertRedirect(route('admin.appointments.show', $appointment))
            ->assertSessionHasNoErrors();

        $this->assertSame('voided', $discount->refresh()->status);
        $this->assertSame('1120.00', $appointment->refresh()->appointment_amount);
        $this->assertNull($appointment->activeDiscount()->first());
    }

    public function test_appointment_discount_cannot_be_voided_after_payment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $appointment = $this->appointment();
        $discount = $this->approvedDiscount($appointment, $admin);
        $appointment->forceFill(['appointment_amount' => '800.00'])->save();
        $appointment->payments()->create([
            'amount' => '100.00',
            'method' => 'cash',
            'recorded_by' => $admin->id,
            'paid_at' => now(),
        ]);
        $appointment->forceFill(['payment_status' => 'partial'])->save();

        $this->actingAs($admin)
            ->from(route('admin.appointments.show', $appointment))
            ->delete(route('admin.appointments.discounts.destroy', [$appointment, $discount]), [
                'void_reason' => 'Attempted after payment.',
            ])
            ->assertRedirect(route('admin.appointments.show', $appointment))
            ->assertSessionHasErrors('discount');

        $this->assertSame('approved', $discount->refresh()->status);
        $this->assertSame('800.00', $appointment->refresh()->appointment_amount);
    }

    public function test_staff_cannot_void_an_appointment_discount(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $appointment = $this->appointment();
        $discount = $this->approvedDiscount($appointment, $staff);
        $appointment->forceFill(['appointment_amount' => '800.00'])->save();

        $this->actingAs($staff)
            ->delete(route('admin.appointments.discounts.destroy', [$appointment, $discount]), [
                'void_reason' => 'Unauthorized attempt.',
            ])
            ->assertForbidden();

        $this->assertSame('approved', $discount->refresh()->status);
        $this->assertSame('800.00', $appointment->refresh()->appointment_amount);
    }

    private function appointment(): Appointment
    {
        $customer = User::factory()->create(['role' => 'user']);
        $service = ServiceType::query()->create([
            'name' => 'Discountable Service',
            'default_fee' => '1120.00',
            'discount_scheme' => PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
            'is_active' => true,
        ]);

        return Appointment::query()->create([
            'user_id' => $customer->id,
            'service_type_id' => $service->id,
            'appointment_at' => '2026-09-26 09:00:00',
            'slot_key' => Appointment::slotKey($service->id, '2026-09-26 09:00:00'),
            'appointment_amount' => '1120.00',
            'discount_scheme' => PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
            'status' => 'scheduled',
            'payment_status' => 'unpaid',
        ]);
    }

    private function approvedDiscount(Appointment $appointment, User $admin): DiscountApplication
    {
        return $appointment->discountApplications()->create([
            'scheme' => PhilippineDiscountCalculator::SCHEME_STATUTORY_20,
            'beneficiary_type' => 'senior',
            'status' => DiscountApplication::STATUS_APPROVED,
            'gross_total' => '1120.00',
            'eligible_gross' => '1120.00',
            'vat_removed' => '120.00',
            'discount_base' => '1000.00',
            'discount_rate' => '20.00',
            'discount_amount' => '200.00',
            'net_total' => '800.00',
            'legal_basis_version' => 'RA 9994 / RA 10754 / RR 5-2017',
            'metadata' => ['vat_registered' => true, 'prices_include_vat' => true],
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);
    }
}
