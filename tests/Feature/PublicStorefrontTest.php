<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The storefront is the one part of the system a stranger may see. These tests
 * pin both halves of that boundary: browsing is open, and everything that
 * spends money or touches an account is not.
 */
class PublicStorefrontTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Bermuda Grass',
            'description' => 'Hardy lawn grass.',
            'category' => 'grass',
            'price' => 350,
            'stock_qty' => 12,
            'is_active' => true,
        ], $overrides));
    }

    public function test_guests_can_browse_the_catalogue_and_a_product(): void
    {
        $product = $this->product();

        $this->get('/shop')
            ->assertOk()
            ->assertSee('Bermuda Grass');

        $this->get('/shop/'.$product->id)
            ->assertOk()
            ->assertSee('Bermuda Grass');
    }

    public function test_guests_see_a_sign_in_prompt_instead_of_a_buy_control(): void
    {
        $product = $this->product();

        // The catalogue swaps the Add button; the product page swaps the whole
        // buy bar. Neither should render a control that posts to the cart.
        $this->get('/shop')
            ->assertSee('Sign in to buy')
            ->assertDontSee('addToCart(', false);

        $this->get('/shop/'.$product->id)
            ->assertDontSee('id="product-add-button"', false);
    }

    public function test_guests_get_a_landing_page_at_the_root_url(): void
    {
        $this->product();

        // The landing page is the one view a stranger sees first, so it has to
        // say what Ferosa does and offer both doors - browse, or book.
        $this->get('/')
            ->assertOk()
            ->assertSee('Ferosa')
            ->assertSee('Book a site visit')
            ->assertSee('Browse the shop')
            ->assertSee('Bermuda Grass');
    }

    public function test_public_pages_do_not_call_themselves_a_dashboard_to_guests(): void
    {
        $this->product();

        // The storefront reuses the customer layout, whose chrome is written for
        // a signed-in account. Announcing "Customer dashboard" to a stranger is
        // what made the public shop read as somebody's private account page.
        $this->get('/shop')
            ->assertOk()
            ->assertDontSee('Customer dashboard')
            ->assertSee('Plants and landscaping');

        $this->actingAs(User::factory()->create())
            ->get('/shop')
            ->assertOk()
            ->assertSee('Customer dashboard');
    }

    public function test_the_landing_page_hides_unpublished_records(): void
    {
        $this->product(['name' => 'Retired Fern', 'is_active' => false]);
        $this->product(['name' => 'Sold Out Palm', 'stock_qty' => 0]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Retired Fern')
            ->assertDontSee('Sold Out Palm');
    }

    public function test_signed_in_users_are_sent_past_the_landing_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertRedirect(route('home'));
    }

    public function test_buying_and_account_pages_stay_behind_auth(): void
    {
        foreach (['/checkout', '/orders', '/appointments', '/account', '/messages', '/estimator', '/schedule'] as $path) {
            $this->get($path)->assertRedirect(route('login'));
        }

        $this->postJson('/api/cart/items', ['product_id' => $this->product()->id, 'quantity' => 1])
            ->assertUnauthorized();
    }

    public function test_signed_in_customers_still_get_the_buy_controls(): void
    {
        $product = $this->product();

        $this->actingAs(User::factory()->create())
            ->get('/shop')
            ->assertOk()
            ->assertSee('addToCart(', false)
            ->assertDontSee('Sign in to buy');

        $this->actingAs(User::factory()->create())
            ->get('/shop/'.$product->id)
            ->assertOk()
            ->assertSee('id="product-add-button"', false);
    }

    public function test_archived_and_inactive_products_stay_hidden_from_guests(): void
    {
        $inactive = $this->product(['name' => 'Retired Fern', 'is_active' => false]);

        $this->get('/shop')->assertDontSee('Retired Fern');
        $this->get('/shop/'.$inactive->id)->assertNotFound();
    }
}
