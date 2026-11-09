<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Override;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name'        => 'Keyboard',
            'sku'         => 'SKU-KEY-100',
            'description' => 'Mechanical keyboard',
            'price'       => 49.5,
            'stock'       => 20,
            'is_active'   => true,
        ], $overrides);
    }

    // ---------- READ ----------

    public function test_it_lists_products_with_pagination(): void
    {
        Product::factory()->count(15)->create();

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 15);
    }

    public function test_it_respects_per_page_and_caps_it(): void
    {
        Product::factory()->count(15)->create();

        $this->getJson('/api/products?per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'data');

        $this->getJson('/api/products?per_page=5000')
            ->assertOk()
            ->assertJsonCount(15, 'data');
    }

    public function test_it_shows_one_product_with_numeric_price(): void
    {
        $product = Product::factory()->create(['price' => 54.99]);

        $response = $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $product->id);

        $this->assertIsFloat($response->json('data.price'));
    }

    public function test_it_returns_json_404_for_missing_product(): void
    {
        $this->getJson('/api/products/9999')
            ->assertNotFound()
            ->assertJson(['message' => 'Resource not found.']);
    }

    public function test_it_returns_json_404_for_unknown_route(): void
    {
        $this->getJson('/api/nothing-here')
            ->assertNotFound()
            ->assertJson(['message' => 'Resource not found.']);
    }

    // ---------- SEARCH / FILTER / SORT ----------

    public function test_it_searches_by_name_or_sku(): void
    {
        Product::factory()->create(['name' => 'Wireless Mouse', 'sku' => 'SKU-M-1']);
        Product::factory()->create(['name' => 'Desk Lamp', 'sku' => 'SKU-L-1']);

        $this->getJson('/api/products?search=mouse')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'SKU-M-1');
    }

    public function test_it_filters_by_price_range_and_active_flag(): void
    {
        Product::factory()->create(['price' => 10, 'is_active' => true]);
        Product::factory()->create(['price' => 200, 'is_active' => true]);
        Product::factory()->create(['price' => 250, 'is_active' => false]);

        $this->getJson('/api/products?min_price=100&max_price=300')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/products?is_active=false')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_it_sorts_by_price_ascending(): void
    {
        Product::factory()->create(['price' => 30]);
        Product::factory()->create(['price' => 10]);
        Product::factory()->create(['price' => 20]);

        $response = $this->getJson('/api/products?sort_by=price&sort_dir=asc')
            ->assertOk();

        $this->assertEquals([10, 20, 30], $response->json('data.*.price'));
    }

    public function test_it_ignores_non_whitelisted_sort_column(): void
    {
        Product::factory()->count(3)->create();

        $this->getJson('/api/products?sort_by=password')->assertOk();
    }

    // ---------- CREATE ----------

    public function test_it_creates_a_product(): void
    {
        $this->postJson('/api/products', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.sku', 'SKU-KEY-100');

        $this->assertDatabaseHas('products', ['sku' => 'SKU-KEY-100']);
    }

    public function test_it_rejects_an_empty_create_request(): void
    {
        $this->postJson('/api/products', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'sku', 'price', 'stock']);
    }

    public function test_it_rejects_wrong_types_on_create(): void
    {
        $this->postJson('/api/products', $this->payload(['price' => 'abc', 'stock' => -5]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['price', 'stock']);
    }

    public function test_it_rejects_a_duplicate_sku(): void
    {
        Product::factory()->create(['sku' => 'SKU-KEY-100']);

        $this->postJson('/api/products', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sku']);
    }

    // ---------- UPDATE ----------

    public function test_it_partially_updates_a_product(): void
    {
        $product = Product::factory()->create(['name' => 'Old', 'price' => 10]);

        $this->putJson("/api/products/{$product->id}", ['price' => 10.5])
            ->assertOk()
            ->assertJsonPath('data.price', 10.5)
            ->assertJsonPath('data.name', 'Old');
    }

    public function test_it_allows_updating_a_product_with_its_own_sku(): void
    {
        $product = Product::factory()->create(['sku' => 'SKU-OWN-1']);

        $this->putJson("/api/products/{$product->id}", ['sku' => 'SKU-OWN-1', 'stock' => 15])
            ->assertOk();
    }

    public function test_it_rejects_updating_to_another_products_sku(): void
    {
        Product::factory()->create(['sku' => 'SKU-TAKEN']);
        $product = Product::factory()->create(['sku' => 'SKU-MINE']);

        $this->putJson("/api/products/{$product->id}", ['sku' => 'SKU-TAKEN'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sku']);
    }

    public function test_it_rejects_invalid_stock_on_update(): void
    {
        $product = Product::factory()->create();

        $this->patchJson("/api/products/{$product->id}", ['stock' => 'many'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['stock']);
    }

    // ---------- DELETE ----------

    public function test_it_deletes_a_product(): void
    {
        $product = Product::factory()->create();

        $this->deleteJson("/api/products/{$product->id}")->assertNoContent();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->getJson("/api/products/{$product->id}")->assertNotFound();
    }
}
