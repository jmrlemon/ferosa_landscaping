<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\ServiceType;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AppointmentStatusWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_appointment_form_offers_only_the_current_and_next_steps(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $appointment = $this->appointment('scheduled', 'unpaid');

        $response = $this->actingAs($admin)
            ->get(route('admin.appointments.show', $appointment))
            ->assertOk();

        $this->assertSame(
            ['scheduled', 'confirmed', 'cancelled'],
            $this->statusOptions($response)
        );
    }

    public function test_unpaid_confirmed_appointment_disables_completed_until_paid(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $appointment = $this->appointment('confirmed', 'unpaid');

        $response = $this->actingAs($admin)
            ->get(route('admin.appointments.show', $appointment))
            ->assertOk()
            ->assertSeeText('Payment must be marked Paid before this appointment can be completed')
            ->assertSee('data-appointment-payment-status-select', false);

        $completed = $this->statusOption($response, 'completed');

        $this->assertTrue($completed->hasAttribute('disabled'));
        $this->assertTrue($completed->hasAttribute('data-requires-paid'));
    }

    public function test_paid_confirmed_appointment_enables_completed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $appointment = $this->appointment('confirmed', 'paid');

        $response = $this->actingAs($admin)
            ->get(route('admin.appointments.show', $appointment))
            ->assertOk();

        $this->assertFalse($this->statusOption($response, 'completed')->hasAttribute('disabled'));
    }

    private function appointment(string $status, string $paymentStatus): Appointment
    {
        $customer = User::factory()->create(['role' => 'user']);
        $service = ServiceType::query()->create([
            'name' => 'Garden Maintenance',
            'default_fee' => 1500,
            'is_active' => true,
        ]);
        $appointmentAt = now()->addDays(4)->setTime(9, 0);

        return Appointment::query()->create([
            'user_id' => $customer->id,
            'service_type_id' => $service->id,
            'appointment_at' => $appointmentAt,
            'slot_key' => Appointment::slotKey($service->id, $appointmentAt),
            'status' => $status,
            'payment_status' => $paymentStatus,
            'appointment_amount' => 1500,
        ]);
    }

    /** @return list<string> */
    private function statusOptions(TestResponse $response): array
    {
        $xpath = $this->xpath($response);
        $options = $xpath->query('//select[@id="appointment-status-select"]/option');
        $this->assertNotFalse($options);

        $statuses = [];
        foreach ($options as $option) {
            $this->assertInstanceOf(DOMElement::class, $option);
            $statuses[] = $option->getAttribute('value');
        }

        return $statuses;
    }

    private function statusOption(TestResponse $response, string $status): DOMElement
    {
        $option = $this->xpath($response)
            ->query(sprintf('//select[@id="appointment-status-select"]/option[@value="%s"]', $status))
            ->item(0);

        $this->assertInstanceOf(DOMElement::class, $option);

        return $option;
    }

    private function xpath(TestResponse $response): DOMXPath
    {
        $document = new DOMDocument;
        $previousErrorHandling = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorHandling);

        return new DOMXPath($document);
    }
}
