<?php

namespace Tests\Feature;

use App\Mail\AppointmentBooked;
use App\Mail\OrderPlaced;
use App\Models\Conversation;
use App\Models\Product;
use App\Models\Project;
use App\Models\ServiceType;
use App\Models\User;
use App\Notifications\WorkCreatedNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TrustWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_customers_only_see_published_projects(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        $published = Project::query()->create([
            'title' => 'Courtyard Renewal',
            'slug' => 'courtyard-renewal',
            'summary' => 'A completed courtyard landscaping project.',
            'is_published' => true,
        ]);
        $draft = Project::query()->create([
            'title' => 'Private Draft',
            'slug' => 'private-draft',
            'summary' => 'This project is not ready for customers.',
            'is_published' => false,
        ]);

        $this->actingAs($customer)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee($published->title)
            ->assertDontSee($draft->title);

        $this->actingAs($customer)
            ->get(route('projects.show', $draft))
            ->assertNotFound();
    }

    public function test_staff_can_create_a_publishable_project_but_customers_cannot_access_admin_portfolio(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $customer = User::factory()->create(['role' => 'user']);

        $this->actingAs($staff)
            ->post(route('admin.projects.store'), [
                'title' => 'Front Garden Upgrade',
                'service_name' => 'Garden redesign',
                'location' => 'Orani, Bataan',
                'summary' => 'Improved circulation, planting, and shade for a residential front garden.',
                'completed_at' => now()->subMonth()->toDateString(),
                'is_published' => '1',
                'is_featured' => '1',
            ])
            ->assertRedirect();

        $project = Project::query()->where('title', 'Front Garden Upgrade')->firstOrFail();
        $this->assertTrue($project->is_published);
        $this->assertTrue($project->is_featured);
        $this->assertSame('front-garden-upgrade', $project->slug);

        foreach ([
            route('admin.projects.index'),
            route('admin.projects.create'),
            route('admin.projects.edit', $project),
        ] as $portfolioUrl) {
            $this->actingAs($staff)
                ->get($portfolioUrl)
                ->assertOk()
                ->assertSee('id="admin-sidebar"', false)
                ->assertSee('aria-current="page"', false)
                ->assertSeeText('Project Portfolio');
        }

        $this->actingAs($customer)
            ->get(route('admin.projects.index'))
            ->assertForbidden();
    }

    public function test_only_admins_can_update_business_trust_details(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        $admin = User::factory()->create(['role' => 'admin']);
        $payload = [
            'business_name' => 'Ferosa Landscaping',
            'business_address' => 'A. Arellano Ave. Mulawin, Orani, Bataan 2112',
            'business_phone' => '0917 000 0000',
            'business_email' => 'hello@example.test',
            'business_hours' => 'Monday to Saturday, 8:00 AM–5:00 PM',
            'service_area' => 'Orani, Bataan',
            'booking_notice' => 'Book at least 24 hours before the visit.',
            'service_guarantee' => null,
            'cancellation_policy' => 'Contact the team as early as possible to reschedule.',
        ];

        $this->actingAs($admin)
            ->get(route('admin.business-profile.edit'))
            ->assertOk()
            ->assertSee('id="admin-sidebar"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSeeText('Business Profile');

        $this->actingAs($customer)
            ->put(route('admin.business-profile.update'), $payload)
            ->assertForbidden();

        $this->actingAs($admin)
            ->put(route('admin.business-profile.update'), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('app_settings', [
            'key' => 'business_phone',
            'value' => '0917 000 0000',
        ]);
    }

    public function test_staff_accounts_stay_in_the_protected_admin_account_workspace(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        $staff = User::factory()->create([
            'role' => 'staff',
            'name' => 'Operations Staff',
            'email' => 'operations@example.test',
        ]);

        $this->actingAs($customer)
            ->get(route('admin.account.edit'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->get(route('account'))
            ->assertRedirect(route('admin.account.edit'));

        $this->actingAs($staff)
            ->get(route('admin.account.edit'))
            ->assertOk()
            ->assertSee('Admin workspace account')
            ->assertSee('Operations Staff');

        $this->actingAs($staff)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Appointments')
            ->assertSee('Orders &amp; Delivery', false)
            ->assertSee('admin-chat-bubble--mine');

        $this->actingAs($staff)
            ->put(route('admin.account.update'), [
                'name' => 'Ferosa Operations',
                'email' => 'operations@example.test',
                'phone_number' => '0917 123 4567',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'name' => 'Ferosa Operations',
            'phone_number' => '0917 123 4567',
            'role' => 'staff',
        ]);
    }

    public function test_admins_cannot_create_or_reply_to_a_conversation_with_themselves(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Self']);
        $selfConversation = Conversation::query()->create([
            'customer_id' => $admin->id,
            'last_message_at' => now(),
        ]);
        $selfConversation->messages()->create([
            'sender_id' => $admin->id,
            'body' => 'SELF_ONLY_MESSAGE',
        ]);

        $this->actingAs($admin)
            ->get(route('messages'))
            ->assertRedirect(route('admin.dashboard', ['tab' => 'messages']));

        $this->actingAs($admin)
            ->post(route('messages.store'), ['body' => 'Trying to message myself'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.conversations.show', $selfConversation))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('admin.conversations.reply', $selfConversation), ['body' => 'Self reply'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.dashboard', ['tab' => 'messages']))
            ->assertOk()
            ->assertDontSee('SELF_ONLY_MESSAGE');

        $this->assertSame(1, $selfConversation->messages()->count());
    }

    public function test_customers_can_still_message_the_admin_team(): void
    {
        $customer = User::factory()->create(['role' => 'user']);

        $this->actingAs($customer)
            ->get(route('messages'))
            ->assertOk();

        $this->actingAs($customer)
            ->post(route('messages.store'), ['body' => 'Can you help with my garden?'])
            ->assertRedirect();

        $conversation = Conversation::query()->where('customer_id', $customer->id)->firstOrFail();
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $customer->id,
            'body' => 'Can you help with my garden?',
        ]);
    }

    public function test_product_details_hide_inactive_inventory_and_use_server_stock_data(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        $active = Product::query()->create([
            'name' => 'Native Fern',
            'description' => 'A shade-friendly garden plant.',
            'price' => 480,
            'stock_qty' => 6,
            'category' => 'Plants',
            'is_active' => true,
        ]);
        $inactive = Product::query()->create([
            'name' => 'Hidden Product',
            'price' => 100,
            'stock_qty' => 1,
            'category' => 'Plants',
            'is_active' => false,
        ]);

        $this->actingAs($customer)
            ->get(route('products.show', $active))
            ->assertOk()
            ->assertSee('Native Fern')
            ->assertSee('6 available');

        $this->actingAs($customer)
            ->get(route('products.show', $inactive))
            ->assertNotFound();
    }

    public function test_booking_and_checkout_notify_staff_and_preserve_server_side_values(): void
    {
        Carbon::setTestNow('2026-07-13 09:00:00');
        Mail::fake();
        Notification::fake();

        $customer = User::factory()->create(['role' => 'user']);
        $staff = User::factory()->create(['role' => 'staff']);
        $service = ServiceType::query()->create([
            'name' => 'Garden Consultation',
            'default_fee' => 750,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'name' => 'Garden Soil Mix',
            'price' => 320,
            'stock_qty' => 8,
            'category' => 'Materials',
            'is_active' => true,
        ]);

        $appointmentAt = Carbon::now()->addDays(3)->setTime(10, 30);
        $this->actingAs($customer)
            ->post(route('schedule.store'), [
                'service_type_id' => $service->id,
                'appointment_at' => $appointmentAt->format('Y-m-d H:i:s'),
                'notes' => 'Please assess the front garden.',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('appointments', [
            'user_id' => $customer->id,
            'service_type_id' => $service->id,
            'appointment_amount' => 750,
            'status' => 'scheduled',
        ]);
        // Mailables are queued so a slow SMTP host cannot stall the booking.
        Mail::assertQueued(AppointmentBooked::class);
        Notification::assertSentTo($staff, WorkCreatedNotice::class);

        Notification::fake();
        $cart = json_encode([[
            'id' => $product->id,
            'name' => 'Tampered name',
            'price' => 1,
            'qty' => 2,
        ]], JSON_THROW_ON_ERROR);

        $this->actingAs($customer)
            ->post(route('checkout.store'), [
                'cart_data' => $cart,
                'delivery_method' => 'pickup',
                'payment_method' => 'cod',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'user_id' => $customer->id,
            'total_amount' => 640,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'name' => 'Garden Soil Mix',
            'price' => 320,
            'qty' => 2,
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_qty' => 6,
        ]);
        Mail::assertQueued(OrderPlaced::class);
        Notification::assertSentTo($staff, WorkCreatedNotice::class);

        Carbon::setTestNow();
    }
}
