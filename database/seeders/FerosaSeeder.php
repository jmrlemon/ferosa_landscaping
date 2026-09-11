<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ServiceType;
use Illuminate\Database\Seeder;

class FerosaSeeder extends Seeder
{
    private function unsplash(string $photoId): string
    {
        return 'https://images.unsplash.com/photo-'.$photoId.'?auto=format&fit=crop&w=800&q=80';
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $u = fn (string $id) => $this->unsplash($id);

        Product::insert([
            ['name' => 'Monstera Deliciosa', 'description' => 'Lush indoor foliage plant in ceramic pot.', 'image_url' => $u('1614594975525-e45190c55d0b'), 'price' => 1200, 'stock_qty' => 100, 'category' => 'plants', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        ServiceType::insert([
            ['name' => 'Garden Design Consultation', 'default_fee' => 1500, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Routine Maintenance', 'default_fee' => 2500, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Irrigation System Check', 'default_fee' => 1200, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Hardscaping Quote', 'default_fee' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
