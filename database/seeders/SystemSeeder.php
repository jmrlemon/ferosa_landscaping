<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SystemSeeder extends Seeder
{
    /** Unsplash CDN (Unsplash License) — each ID is chosen to match the product. */
    private function unsplash(string $photoId): string
    {
        return 'https://images.unsplash.com/photo-'.$photoId.'?auto=format&fit=crop&w=800&q=80';
    }

    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@cblandscaping.com'],
            [
                'name' => 'Admin (CB Landscaping)',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'account_type' => 'Business',
            ]
        );

        $demoUser = User::updateOrCreate(
            ['email' => 'user@cblandscaping.com'],
            [
                'name' => 'Demo User',
                'password' => Hash::make('password'),
                'role' => 'user',
                'account_type' => 'Customer',
            ]
        );

        $u = fn (string $photoId) => $this->unsplash($photoId);

        $products = [
            // Original system list — images match product type (Unsplash).
            ['name' => 'Garden Soil', 'category' => 'soil', 'price' => 150, 'image_url' => $u('1653398241881-b26e7cebfcf5')], // pile of soil
            ['name' => 'Gravel', 'category' => 'aggregates', 'price' => 500, 'image_url' => $u('1604178449672-3e7d72d5a09a')], // crushed stone / gravel
            ['name' => 'Plants (Various)', 'category' => 'plants', 'price' => 300, 'image_url' => $u('1598902108854-10e335adac99')], // mixed garden plants
            ['name' => 'Natural Stones (Pebbles, River Rocks, Boulder)', 'category' => 'stones', 'price' => 800, 'image_url' => $u('1567921706527-ac8e2f08b053')], // assorted pebbles
            ['name' => 'Carabao Grass', 'category' => 'grass', 'price' => 250, 'image_url' => $u('1526392587392-d1627b6c134a')], // lawn grass
            ['name' => 'Bermuda Grass', 'category' => 'grass', 'price' => 350, 'image_url' => $u('1571955184611-592f592e5ac6')], // green turf
            ['name' => 'Frog Grass', 'category' => 'grass', 'price' => 400, 'image_url' => $u('1628340981113-fe1949fe5cc0')], // grass field
            ['name' => 'Grass Paver', 'category' => 'stones', 'price' => 1100, 'image_url' => $u('1759745063503-921e354f5f55')], // paver patio / hardscape
            ['name' => 'Decorative Stone/Claddings', 'category' => 'stones', 'price' => 900, 'image_url' => $u('1629747218925-d829bf8d85a8')], // stone wall / cladding texture
            ['name' => 'Fruit bearing plants', 'category' => 'plants', 'price' => 600, 'image_url' => $u('1661371089074-f7f642f0c323')], // fruit on tree

            // Ferosa demo products
            [
                'name' => 'Monstera Deliciosa',
                'description' => 'Lush indoor foliage plant in ceramic pot.',
                'price' => 1200,
                'category' => 'plants',
                'is_active' => true,
                'image_url' => $u('1614594975525-e45190c55d0b'),
            ],
        ];

        foreach ($products as $p) {
            Product::updateOrCreate(
                ['name' => $p['name']],
                [
                    'description' => $p['description'] ?? null,
                    'price' => $p['price'] ?? 0,
                    'category' => $p['category'] ?? 'plants',
                    'is_active' => $p['is_active'] ?? true,
                    'image_url' => $p['image_url'] ?? null,
                    // Above dashboard low-stock threshold (5 or fewer); migration defaults stock_qty to 0.
                    'stock_qty' => $p['stock_qty'] ?? 100,
                ]
            );
        }

        $services = [
            // Original system list
            ['name' => 'Landscape Design and Planning', 'default_fee' => 1500],
            ['name' => 'Planting', 'default_fee' => 500],
            ['name' => 'Hardscaping', 'default_fee' => 3000],
            ['name' => 'Irrigation System', 'default_fee' => 2500],
            ['name' => 'Lighting', 'default_fee' => 1800],
            ['name' => 'Lawn Care', 'default_fee' => 800],
            ['name' => 'Pruning & Trimming', 'default_fee' => 600],
            ['name' => 'Pest & Disease Control', 'default_fee' => 900],
            ['name' => 'Fertilization & Soil Amendments', 'default_fee' => 700],
            ['name' => 'Water Features', 'default_fee' => 4000],
            ['name' => 'Outdoor Living Spaces', 'default_fee' => 5000],
            ['name' => 'Sustainable Landscaping', 'default_fee' => 2000],
            ['name' => 'Erosion Control', 'default_fee' => 3500],

            // Schedule dropdown options (must exist for bookings)
            ['name' => 'Garden Design Consultation', 'default_fee' => 1500],
            ['name' => 'Routine Maintenance', 'default_fee' => 2500],
            ['name' => 'Irrigation System Check', 'default_fee' => 1200],
            ['name' => 'Hardscaping Quote', 'default_fee' => 0],
        ];

        foreach ($services as $s) {
            ServiceType::updateOrCreate(
                ['name' => $s['name']],
                [
                    'default_fee' => $s['default_fee'] ?? 0,
                    'is_active' => $s['is_active'] ?? true,
                ]
            );
        }

        // Demo orders (idempotent). Names must match the product list seeded
        // above, otherwise the whole block below is silently skipped.
        $productSoil = Product::where('name', 'Garden Soil')->first();
        $productGravel = Product::where('name', 'Gravel')->first();

        if ($productSoil && $productGravel) {
            $order1 = Order::updateOrCreate(
                ['order_number' => 'ORD-123456'],
                [
                    'user_id' => $demoUser->id,
                    'status' => 'pending',
                    'total_amount' => ($productSoil->price * 2) + $productGravel->price,
                    'items' => [
                        ['product_id' => $productSoil->id, 'name' => $productSoil->name, 'price' => (string) $productSoil->price, 'qty' => 2],
                        ['product_id' => $productGravel->id, 'name' => $productGravel->name, 'price' => (string) $productGravel->price, 'qty' => 1],
                    ],
                ]
            );

            // Seed order_items for order 1
            if ($order1->orderItems()->count() === 0) {
                $order1->orderItems()->createMany([
                    ['product_id' => $productSoil->id, 'name' => $productSoil->name, 'price' => $productSoil->price, 'qty' => 2],
                    ['product_id' => $productGravel->id, 'name' => $productGravel->name, 'price' => $productGravel->price, 'qty' => 1],
                ]);
            }

            $order2 = Order::updateOrCreate(
                ['order_number' => 'ORD-789012'],
                [
                    'user_id' => $demoUser->id,
                    'status' => 'delivered',
                    'total_amount' => $productSoil->price * 5,
                    'items' => [
                        ['product_id' => $productSoil->id, 'name' => $productSoil->name, 'price' => (string) $productSoil->price, 'qty' => 5],
                    ],
                ]
            );

            // Seed order_items for order 2
            if ($order2->orderItems()->count() === 0) {
                $order2->orderItems()->create([
                    'product_id' => $productSoil->id, 'name' => $productSoil->name, 'price' => $productSoil->price, 'qty' => 5,
                ]);
            }
        }

        // Demo appointments (idempotent)
        $serviceDesign = ServiceType::where('name', 'Garden Design Consultation')->first()
            ?? ServiceType::where('name', 'Landscape Design and Planning')->first();

        if ($serviceDesign) {
            Appointment::updateOrCreate(
                [
                    'user_id' => $demoUser->id,
                    'service_type_id' => $serviceDesign->id,
                    'status' => 'scheduled',
                ],
                [
                    'appointment_at' => now()->addDays(7),
                    'notes' => 'Looking to redesign my front yard layout.',
                ]
            );
        }

        $serviceLawn = ServiceType::where('name', 'Routine Maintenance')->first()
            ?? ServiceType::where('name', 'Lawn Care')->first();

        if ($serviceLawn) {
            Appointment::updateOrCreate(
                [
                    'user_id' => $demoUser->id,
                    'service_type_id' => $serviceLawn->id,
                    'status' => 'completed',
                ],
                [
                    'appointment_at' => now()->subDays(2),
                    'notes' => 'Routine lawn mowing and fertilizing done.',
                ]
            );
        }
    }
}
