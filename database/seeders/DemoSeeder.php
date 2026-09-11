<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Conversation;
use App\Models\Feedback;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Presentation data: enough customers, orders, visits, chat and reviews that
 * every screen has something real on it.
 *
 * Deliberately NOT called from DatabaseSeeder. Run it by name:
 *
 *     php artisan db:seed --class=DemoSeeder
 *
 * Everything it writes is keyed on a `demo.ferosa` email or an FRS-DEMO order
 * number, so running it twice updates rather than duplicates, and you can find
 * and remove all of it later:
 *
 *     User::where('email', 'like', '%@demo.ferosa')->delete();
 *     Order::where('order_number', 'like', 'FRS-DEMO-%')->delete();
 *
 * Orders and appointments are written with their status set directly rather
 * than through the state machine. That is correct here and nowhere else: this
 * is fixture data being placed into a state, not a booking being moved through
 * one.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $customers = $this->customers();
        $service = $this->service();
        $product = $this->product();

        $this->orders($customers, $product);
        $this->visits($customers, $service);
        $this->conversations($customers);

        $this->command->info('Demo data ready. Customers sign in with: password');
    }

    /** @return array<int, User> */
    private function customers(): array
    {
        $people = [
            ['Maria Santos', 'maria@demo.ferosa', '09171234567'],
            ['Ramon Dela Cruz', 'ramon@demo.ferosa', '09181234568'],
            ['Liwayway Bautista', 'liwayway@demo.ferosa', '09191234569'],
            ['Eduardo Mercado', 'eduardo@demo.ferosa', '09201234570'],
            ['Cristina Villanueva', 'cristina@demo.ferosa', '09211234571'],
        ];

        $customers = [];
        foreach ($people as [$name, $email, $phone]) {
            $customers[] = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'phone_number' => $phone,
                    'password' => Hash::make('password'),
                    'role' => 'user',
                    'account_type' => 'Customer',
                ]
            );
        }

        // One staff account, so the workspace has someone other than the admin
        // acting in it and the audit log is not all one name.
        User::updateOrCreate(
            ['email' => 'crew@demo.ferosa'],
            [
                'name' => 'Ana Reyes (Crew Lead)',
                'password' => Hash::make('password'),
                'role' => 'staff',
                'account_type' => 'Business',
            ]
        );

        return $customers;
    }

    private function service(): ServiceType
    {
        return ServiceType::query()->where('is_active', true)->whereNull('archived_at')->first()
            ?? ServiceType::query()->create([
                'name' => 'Garden Maintenance',
                'default_fee' => 1500,
                'is_active' => true,
            ]);
    }

    private function product(): ?Product
    {
        return Product::query()->where('is_active', true)->whereNull('archived_at')->first();
    }

    /** @param array<int, User> $customers */
    private function orders(array $customers, ?Product $product): void
    {
        $name = $product->name ?? 'Garden Soil';
        $price = (float) ($product->price ?? 250);

        // One order sitting in each state, so the admin Orders tab shows the
        // whole pipeline at once instead of five rows saying "pending".
        // The cancellation reason travels with the row rather than being
        // derived from the status further down: the table is the description
        // of the fixture, so anything that varies by row belongs in it.
        $states = [
            ['pending', 'unpaid', 'cod', 0, null],
            ['confirmed', 'paid', 'gcash', 1, null],
            ['out_for_delivery', 'paid', 'gcash', 2, null],
            ['delivered', 'paid', 'cod', 3, null],
            ['completed', 'paid', 'gcash', 4, null],
            ['cancelled', 'unpaid', 'cod', 1, 'Customer changed their mind.'],
        ];

        foreach ($states as $i => [$status, $paymentStatus, $method, $customerIndex, $cancelReason]) {
            $customer = $customers[$customerIndex];
            $qty = 1 + ($i % 3);

            Order::updateOrCreate(
                ['order_number' => 'FRS-DEMO-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT)],
                [
                    'user_id' => $customer->id,
                    'status' => $status,
                    'payment_status' => $paymentStatus,
                    'total_amount' => $price * $qty,
                    'items' => [['name' => $name, 'price' => $price, 'qty' => $qty]],
                    'delivery_method' => $i % 2 === 0 ? 'delivery' : 'pickup',
                    'delivery_name' => $customer->name,
                    'delivery_phone' => $customer->phone_number,
                    'delivery_address' => 'Purok '.($i + 1).', Barangay Bayan',
                    'delivery_city' => 'Orani, Bataan',
                    'payment_method' => $method,
                    'payment_reference' => $method === 'gcash' ? '90'.str_pad((string) (11111 * ($i + 1)), 9, '0', STR_PAD_LEFT) : null,
                    'cancel_reason' => $cancelReason,
                    'cancelled_at' => $cancelReason ? now()->subDays(4) : null,
                    'created_at' => now()->subDays(12 - $i),
                ]
            );
        }
    }

    /** @param array<int, User> $customers */
    private function visits(array $customers, ServiceType $service): void
    {
        // Two upcoming (one already confirmed), one finished and reviewed, one
        // cancelled - every badge and every action on the page has a subject.
        /** @var list<array{status: string, at: Carbon, customer: int, rating: int|null, cancelled: string|null}> $rows */
        $rows = [
            ['status' => 'scheduled', 'at' => Carbon::now()->addDays(3)->setTime(9, 0), 'customer' => 0, 'rating' => null, 'cancelled' => null],
            ['status' => 'confirmed', 'at' => Carbon::now()->addDays(5)->setTime(13, 0), 'customer' => 1, 'rating' => null, 'cancelled' => null],
            ['status' => 'completed', 'at' => Carbon::now()->subDays(6)->setTime(10, 30), 'customer' => 2, 'rating' => 5, 'cancelled' => null],
            ['status' => 'completed', 'at' => Carbon::now()->subDays(14)->setTime(14, 30), 'customer' => 3, 'rating' => 4, 'cancelled' => null],
            ['status' => 'cancelled', 'at' => Carbon::now()->subDays(2)->setTime(16, 0), 'customer' => 4, 'rating' => null, 'cancelled' => 'Rain forecast for that day.'],
        ];

        foreach ($rows as $row) {
            ['status' => $status, 'at' => $at, 'rating' => $rating, 'cancelled' => $cancelReason] = $row;
            $customerIndex = $row['customer'];
            $customer = $customers[$customerIndex];
            $isLive = in_array($status, ['scheduled', 'confirmed'], true);

            $appointment = Appointment::updateOrCreate(
                [
                    'user_id' => $customer->id,
                    'appointment_at' => $at,
                ],
                [
                    'service_type_id' => $service->id,
                    // Only a live visit holds its slot; a cancelled or finished
                    // one releases it, exactly as the app does.
                    'slot_key' => $isLive ? Appointment::slotKey($service->id, $at) : null,
                    'status' => $status,
                    'payment_status' => $status === 'completed' ? 'paid' : 'unpaid',
                    'appointment_amount' => $service->default_fee,
                    'notes' => 'Front lawn and the hedge along the driveway.',
                    'cancel_reason' => $cancelReason,
                    'cancelled_at' => $cancelReason ? now()->subDays(3) : null,
                ]
            );

            if ($rating !== null && ! $appointment->feedback()->exists()) {
                Feedback::query()->create([
                    'user_id' => $customer->id,
                    'appointment_id' => $appointment->id,
                    'service_type_id' => $service->id,
                    'rating' => $rating,
                    'comment' => $rating === 5
                        ? 'The crew arrived on time and cleaned up after themselves.'
                        : 'Good work overall, and they explained the plant care to us.',
                ]);
            }
        }
    }

    /** @param array<int, User> $customers */
    private function conversations(array $customers): void
    {
        $admin = User::query()->where('role', 'admin')->first();
        if (! $admin) {
            return;
        }

        $threads = [
            [0, 'Good morning! Do you deliver to Barangay Calero?', 'Yes, we cover all of Orani. Delivery is free for orders over PHP 1,000.'],
            [2, 'Can the crew come earlier than 9am next visit?', 'Our first dispatch slot is 9:00 AM, but we can put you first on the route that day.'],
        ];

        foreach ($threads as [$customerIndex, $question, $answer]) {
            $customer = $customers[$customerIndex];

            $conversation = Conversation::updateOrCreate(
                ['customer_id' => $customer->id],
                ['last_message_at' => now()->subHours(2)]
            );

            if ($conversation->messages()->exists()) {
                continue;
            }

            Message::query()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $customer->id,
                'body' => $question,
                'created_at' => now()->subHours(3),
            ]);

            Message::query()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $admin->id,
                'body' => $answer,
                'read_at' => now()->subHours(2),
                'created_at' => now()->subHours(2),
            ]);
        }
    }
}
