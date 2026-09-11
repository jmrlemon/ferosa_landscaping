<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Product;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The shop and the crew keep different calendars. An order can be placed on a
 * Sunday because it is picked and delivered on a working day, but a visit puts
 * people on the ground, so Sunday is not a bookable day.
 *
 * The rule lives in DispatchSlot, so all three ways a visit gets a time -
 * customer books, customer moves, staff moves - are covered by it.
 */
class ClosedDayTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ServiceType
    {
        return ServiceType::query()->create([
            'name' => 'Garden Maintenance',
            'default_fee' => 1500,
            'is_active' => true,
        ]);
    }

    /** The next Sunday that is also outside the 24-hour booking notice. */
    private function nextClosedDay(): Carbon
    {
        return Carbon::now()->addDays(2)->next(Carbon::SUNDAY)->setTime(9, 0);
    }

    private function nextOpenDay(): Carbon
    {
        return Carbon::now()->addDays(2)->next(Carbon::TUESDAY)->setTime(9, 0);
    }

    public function test_a_customer_cannot_book_a_visit_on_a_closed_day(): void
    {
        Mail::fake();
        Notification::fake();
        $customer = User::factory()->create(['role' => 'user']);
        $service = $this->service();
        $sunday = $this->nextClosedDay();

        $this->assertTrue(Appointment::isClosedOn($sunday));

        $this->actingAs($customer)
            ->post(route('schedule.store'), [
                'service_type_id' => $service->id,
                'appointment_at' => $sunday->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHasErrors('appointment_at');

        $this->assertSame(0, Appointment::query()->count());
    }

    public function test_the_same_time_on_a_working_day_is_accepted(): void
    {
        Mail::fake();
        Notification::fake();
        $customer = User::factory()->create(['role' => 'user']);
        $service = $this->service();
        $tuesday = $this->nextOpenDay();

        $this->assertFalse(Appointment::isClosedOn($tuesday));

        $this->actingAs($customer)
            ->post(route('schedule.store'), [
                'service_type_id' => $service->id,
                'appointment_at' => $tuesday->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Appointment::query()->count());
    }

    public function test_a_visit_cannot_be_moved_onto_a_closed_day_by_either_side(): void
    {
        Mail::fake();
        Notification::fake();
        $customer = User::factory()->create(['role' => 'user']);
        $staff = User::factory()->create(['role' => 'staff']);
        $service = $this->service();

        $bookedAt = $this->nextOpenDay();
        $appointment = Appointment::query()->create([
            'user_id' => $customer->id,
            'service_type_id' => $service->id,
            'appointment_at' => $bookedAt,
            'slot_key' => Appointment::slotKey($service->id, $bookedAt),
            'appointment_amount' => 1500,
            'status' => 'scheduled',
        ]);

        $sunday = $this->nextClosedDay();

        $this->actingAs($customer)
            ->put(route('appointments.reschedule', $appointment), [
                'appointment_at' => $sunday->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHasErrors('appointment_at');

        // Staff are exempt from the notice window, but not from the crew's
        // calendar - nobody is working that day.
        $this->actingAs($staff)
            ->put(route('admin.appointments.reschedule', $appointment), [
                'move_date' => $sunday->format('Y-m-d'),
                'move_time' => '09:00',
            ])
            ->assertSessionHasErrors('appointment_at');

        $this->assertTrue($bookedAt->equalTo($appointment->refresh()->appointment_at));
    }

    public function test_the_shop_stays_open_on_a_closed_day(): void
    {
        Mail::fake();
        Notification::fake();
        $customer = User::factory()->create(['role' => 'user']);
        $product = Product::query()->create([
            'name' => 'Garden Soil',
            'price' => 250,
            'stock_qty' => 10,
            'category' => 'Materials',
            'is_active' => true,
        ]);

        // Sunday closes the crew's calendar, not the shop: an order placed then
        // is picked and delivered on a working day.
        Carbon::setTestNow($this->nextClosedDay());

        $this->actingAs($customer)
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])
            ->assertSuccessful();

        $this->actingAs($customer)
            ->post(route('checkout.store'), [
                'delivery_method' => 'pickup',
                'payment_method' => 'cod',
                'delivery_name' => 'Maria Santos',
                'delivery_phone' => '09171234567',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('orders', 1);

        Carbon::setTestNow();
    }

    public function test_the_booking_page_tells_the_browser_which_days_are_closed(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        $this->service();

        // The calendar greys the days out client-side; the server rejects them
        // regardless, but the list has to reach the page for that to happen.
        $this->actingAs($customer)
            ->get(route('schedule'))
            ->assertOk()
            ->assertSee('CLOSED_WEEKDAYS', false)
            ->assertSee('const CLOSED_WEEKDAYS = [0]', false);
    }
}
