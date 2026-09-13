<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_public_product_image_is_served_without_a_storage_symlink(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/agave.webp', 'product-image');

        $this->get('/storage/products/agave.webp')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=604800, public')
            ->assertStreamedContent('product-image');
    }

    public function test_a_missing_product_image_returns_not_found(): void
    {
        Storage::fake('public');

        $this->get('/storage/products/missing.webp')->assertNotFound();
    }

    public function test_product_image_delivery_does_not_expose_other_storage_paths(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('private.txt', 'not-public');

        $this->get('/storage/products/%2e%2e%2fprivate.txt')->assertNotFound();
        $this->get('/storage/private.txt')->assertNotFound();
    }

    public function test_replacing_a_product_image_publishes_the_new_file_and_removes_the_old_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/old.webp', 'old-image');

        $product = Product::query()->create([
            'name' => 'Agave',
            'description' => 'Outdoor plant',
            'image_url' => '/storage/products/old.webp',
            'price' => 150,
            'stock_qty' => 10,
            'category' => 'plants',
            'is_active' => true,
        ]);

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->put(route('admin.products.update', $product), [
                'name' => 'Agave',
                'description' => 'Outdoor plant',
                'price' => 150,
                'category' => 'plants',
                'is_active' => '1',
                'redirect_to' => 'edit',
                'image' => UploadedFile::fake()->image('replacement.webp', 600, 400),
            ]);

        $response->assertRedirect(route('admin.products.edit', $product));

        $imageUrl = $product->fresh()->image_url;
        $this->assertNotSame('/storage/products/old.webp', $imageUrl);
        $this->assertStringStartsWith('/storage/products/', $imageUrl);

        $newPath = str_replace('/storage/', '', $imageUrl);
        Storage::disk('public')->assertExists($newPath);
        Storage::disk('public')->assertMissing('products/old.webp');

        $this->get(parse_url($imageUrl, PHP_URL_PATH))->assertOk();
    }
}
