<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductImagePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_create_form_includes_an_accessible_local_image_preview(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $html = $this->actingAs($admin)
            ->get(route('admin.products.create'))
            ->assertOk()
            ->getContent();

        $document = new \DOMDocument;
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

        $input = $document->getElementById('product-image-input');
        $preview = $document->getElementById('product-image-preview');
        $placeholder = $document->getElementById('product-image-preview-placeholder');
        $status = $document->getElementById('product-image-preview-status');

        $this->assertNotNull($input, 'The product image input must be addressable by the preview script.');
        $this->assertSame('file', $input->getAttribute('type'));
        $this->assertSame('image/*', $input->getAttribute('accept'));
        $this->assertSame('product-image-preview-status', $input->getAttribute('aria-describedby'));

        $this->assertNotNull($preview, 'The selected product image needs a preview element.');
        $this->assertStringContainsString('hidden', $preview->getAttribute('class'));
        $this->assertSame('true', $preview->getAttribute('aria-hidden'));

        $this->assertNotNull($placeholder, 'The empty preview state needs to remain available before selection.');
        $this->assertNotNull($status, 'Screen-reader users need feedback when the preview changes.');
        $this->assertSame('status', $status->getAttribute('role'));
        $this->assertSame('polite', $status->getAttribute('aria-live'));

        $this->assertStringContainsString('URL.createObjectURL(file)', $html);
        $this->assertStringContainsString('URL.revokeObjectURL', $html);
    }

    public function test_product_edit_form_previews_a_replacement_image_and_keeps_the_current_image_as_fallback(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::query()->create([
            'name' => 'Agave',
            'description' => 'Outdoor plant',
            'image_url' => '/storage/products/agave.webp',
            'price' => 150,
            'stock_qty' => 10,
            'category' => 'plants',
            'is_active' => true,
        ]);

        $html = $this->actingAs($admin)
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->getContent();

        $document = new \DOMDocument;
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

        $input = $document->getElementById('product-image-input');
        $preview = $document->getElementById('product-image-preview');
        $placeholder = $document->getElementById('product-image-preview-placeholder');
        $status = $document->getElementById('product-image-preview-status');

        $this->assertNotNull($input);
        $this->assertSame('product-image-preview-status', $input->getAttribute('aria-describedby'));
        $this->assertNotNull($preview);
        $this->assertSame('/storage/products/agave.webp', $preview->getAttribute('src'));
        $this->assertSame('Agave', $preview->getAttribute('alt'));
        $this->assertNotNull($placeholder);
        $this->assertStringContainsString('hidden', $placeholder->getAttribute('class'));
        $this->assertNotNull($status);
        $this->assertSame('polite', $status->getAttribute('aria-live'));
        $this->assertStringContainsString('URL.createObjectURL(file)', $html);
        $this->assertStringContainsString('URL.revokeObjectURL', $html);
        $this->assertStringContainsString('currentImageUrl', $html);
    }
}
