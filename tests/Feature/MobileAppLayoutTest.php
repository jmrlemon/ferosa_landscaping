<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileAppLayoutTest extends TestCase
{
    use RefreshDatabase;

    /** Matches the marker the Android WebView appends to its User-Agent. */
    private const APP_UA = 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/120 Mobile Safari/537.36 FerosaApp/1.0';

    private function customer(): User
    {
        return User::factory()->create(['role' => 'user']);
    }

    public function test_browser_requests_keep_the_web_navigation(): void
    {
        $html = $this->actingAs($this->customer())->get('/shop')->assertOk()->getContent();

        preg_match('/<body[^>]*>/', $html, $body);
        $this->assertStringNotContainsString('in-app', $body[0]);
    }

    public function test_browser_navigation_has_a_cover_for_the_outgoing_page(): void
    {
        $html = $this->actingAs($this->customer())->get('/shop')->assertOk()->getContent();

        $this->assertStringContainsString('id="page-navigation-cover"', $html);
        $this->assertStringContainsString('cover.classList.remove(\'hidden\')', $html);
    }

    public function test_android_app_requests_render_the_in_app_layout(): void
    {
        $html = $this->actingAs($this->customer())
            ->withHeaders(['User-Agent' => self::APP_UA])
            ->get('/shop')->assertOk()->getContent();

        preg_match('/<body[^>]*>/', $html, $body);

        // The class has to be on the very first render. Relying on the injected
        // script meant the web nav bar sat under the native one until the page
        // finished loading.
        $this->assertStringContainsString('in-app', $body[0]);
    }

    public function test_android_app_uses_compiled_assets_when_desktop_vite_is_hot(): void
    {
        $hotFile = public_path('hot');
        $originalHotFile = is_file($hotFile) ? file_get_contents($hotFile) : null;

        file_put_contents($hotFile, 'http://[::1]:5173');

        try {
            $html = $this->actingAs($this->customer())
                ->withHeaders(['User-Agent' => self::APP_UA])
                ->get('/shop')->assertOk()->getContent();

            $this->assertStringContainsString('/build/assets/app-', $html);
            $this->assertStringNotContainsString('[::1]:5173', $html);
        } finally {
            if ($originalHotFile === null) {
                @unlink($hotFile);
            } else {
                file_put_contents($hotFile, $originalHotFile);
            }
        }
    }

    public function test_desktop_browser_keeps_vite_hot_reload(): void
    {
        $hotFile = public_path('hot');
        $originalHotFile = is_file($hotFile) ? file_get_contents($hotFile) : null;

        file_put_contents($hotFile, 'http://[::1]:5173');

        try {
            $html = $this->actingAs($this->customer())->get('/shop')->assertOk()->getContent();

            $this->assertStringContainsString('[::1]:5173', $html);
        } finally {
            if ($originalHotFile === null) {
                @unlink($hotFile);
            } else {
                file_put_contents($hotFile, $originalHotFile);
            }
        }
    }

    public function test_the_bottom_nav_the_in_app_css_hides_actually_exists(): void
    {
        $html = $this->actingAs($this->customer())
            ->withHeaders(['User-Agent' => self::APP_UA])
            ->get('/shop')->assertOk()->getContent();

        // `body.in-app .mobile-customer-nav { display: none }` is only useful
        // while the markup still carries that class.
        $this->assertStringContainsString('mobile-customer-nav', $html);
        $this->assertStringContainsString('body.in-app .mobile-customer-nav', $html);
    }

    public function test_shop_images_are_lazy_loaded(): void
    {
        // Product images are remote URLs; loading all of them at once is what
        // made the shop crawl on a phone.
        Product::create([
            'name' => 'Bermuda Grass',
            'description' => 'Test product',
            'image_url' => 'https://images.unsplash.com/photo-test?auto=format&fit=crop&w=800&q=80',
            'price' => 250,
            'stock_qty' => 5,
            'category' => 'Grass',
            'is_active' => true,
        ]);

        $html = $this->actingAs($this->customer())->get('/shop')->assertOk()->getContent();

        preg_match_all('/<img[^>]+>/', $html, $images);
        $productImages = array_filter($images[0], fn ($tag) => str_contains($tag, 'object-cover'));

        $this->assertNotEmpty($productImages, 'no product images rendered');

        foreach ($productImages as $tag) {
            $this->assertStringContainsString('loading="lazy"', $tag);
        }
    }
}
