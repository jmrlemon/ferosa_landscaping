<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Product;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EstimatorBookingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_schedule_access_requires_a_prepared_estimate(): void
    {
        $customer = User::factory()->create(['role' => 'user']);

        $this->actingAs($customer)
            ->get(route('schedule'))
            ->assertRedirect(route('estimator'))
            ->assertSessionHasErrors('estimate');

        $this->actingAs($customer)
            ->post(route('schedule.store'), [
                'appointment_at' => now()->addDays(3)->setTime(9, 0)->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('estimator'))
            ->assertSessionHasErrors('estimate');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_book_consultation_prepares_a_server_calculated_estimate_for_schedule(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        $service = $this->mappedService('Garden Design Consultation', 1500);
        $product = Product::query()->create([
            'name' => 'Garden Soil',
            'price' => 250,
            'stock_qty' => 20,
            'category' => 'materials',
            'is_active' => true,
        ]);

        $this->actingAs($customer)
            ->post(route('estimator.prepare'), [
                'project_type' => 'design',
                'size' => 100,
                'tier' => 'premium',
                'addons' => ['lighting'],
                'products' => [
                    ['id' => $product->id, 'qty' => 2],
                ],
                'service_type_id' => 999999,
                'total' => 1,
            ])
            ->assertRedirect(route('schedule'))
            ->assertSessionHas('estimator_booking');

        $this->actingAs($customer)
            ->get(route('schedule'))
            ->assertOk()
            ->assertSeeText('Your estimate is connected')
            ->assertSeeText('Garden Design')
            ->assertSeeText('100 sq m')
            ->assertSeeText('Premium')
            ->assertSeeText('PHP 33,500.00')
            ->assertSeeText($service->name)
            ->assertDontSee('id="service-type-select"', false)
            ->assertDontSeeText('Choose a service');
    }

    public function test_booking_uses_the_estimate_service_and_keeps_the_consultation_fee(): void
    {
        Mail::fake();
        Notification::fake();
        $customer = User::factory()->create(['role' => 'user']);
        $mappedService = $this->mappedService('Hardscaping Quote', 900);
        $forgedService = $this->mappedService('Unrelated Service', 9999);

        $this->actingAs($customer)
            ->post(route('estimator.prepare'), [
                'project_type' => 'hardscaping',
                'size' => 50,
                'tier' => 'standard',
                'addons' => [],
                'products' => [],
            ])
            ->assertRedirect(route('schedule'));

        $appointmentAt = now()->addDays(3)->setTime(9, 0)->format('Y-m-d H:i:s');
        $this->actingAs($customer)
            ->post(route('schedule.store'), [
                'service_type_id' => $forgedService->id,
                'appointment_at' => $appointmentAt,
                'notes' => 'Please inspect the back garden.',
            ])
            ->assertRedirect(route('appointments'))
            ->assertSessionMissing('estimator_booking');

        $appointment = Appointment::query()->sole();
        $this->assertSame($mappedService->id, $appointment->service_type_id);
        $this->assertSame(900.0, (float) $appointment->appointment_amount);
        $this->assertSame('hardscaping', $appointment->estimate_snapshot['project_type']);
        $this->assertSame(6000.0, (float) $appointment->estimate_snapshot['total']);
        $this->assertSame('Please inspect the back garden.', $appointment->notes);
    }

    public function test_unavailable_mapped_service_prevents_estimate_handoff(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        $this->mappedService('Routine Maintenance', 2500, false);

        $this->actingAs($customer)
            ->from(route('estimator'))
            ->post(route('estimator.prepare'), [
                'project_type' => 'maintenance',
                'size' => 100,
                'tier' => 'standard',
                'addons' => [],
                'products' => [],
            ])
            ->assertRedirect(route('estimator'))
            ->assertSessionHasErrors('project_type')
            ->assertSessionMissing('estimator_booking');
    }

    public function test_rescheduling_keeps_the_existing_service_without_a_new_estimate(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        $service = $this->mappedService('Garden Design Consultation', 1500);
        $appointment = Appointment::query()->create([
            'user_id' => $customer->id,
            'service_type_id' => $service->id,
            'appointment_at' => now()->addDays(4)->setTime(9, 0),
            'slot_key' => Appointment::slotKey($service->id, now()->addDays(4)->setTime(9, 0)),
            'appointment_amount' => 1500,
            'status' => 'scheduled',
            'payment_status' => 'unpaid',
        ]);

        $this->actingAs($customer)
            ->get(route('schedule', ['reschedule' => $appointment->id]))
            ->assertOk()
            ->assertSeeText('Service stays as booked')
            ->assertSeeText($service->name)
            ->assertDontSeeText('Choose a service');
    }

    private function mappedService(string $name, float $fee, bool $active = true): ServiceType
    {
        return ServiceType::query()->create([
            'name' => $name,
            'default_fee' => $fee,
            'is_active' => $active,
        ]);
    }
}
