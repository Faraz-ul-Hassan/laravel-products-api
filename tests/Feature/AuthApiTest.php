<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    private function registerPayload(array $overrides = []): array
    {
        return array_merge([
            'name'                  => 'Faraz',
            'email'                 => 'faraz@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    // ---------- REGISTER ----------

    public function test_it_registers_a_user_and_returns_a_token(): void
    {
        $this->postJson('/api/register', $this->registerPayload())
            ->assertCreated()
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token'])
            ->assertJsonMissingPath('user.password');

        $user = User::where('email', 'faraz@example.com')->firstOrFail();

        // password must be stored hashed, never as plain text
        $this->assertNotSame('password123', $user->password);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_it_rejects_an_empty_registration(): void
    {
        $this->postJson('/api/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_it_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'faraz@example.com']);

        $this->postJson('/api/register', $this->registerPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_it_rejects_a_mismatched_password_confirmation(): void
    {
        $this->postJson('/api/register', $this->registerPayload(['password_confirmation' => 'different']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    // ---------- LOGIN ----------

    public function test_it_logs_in_with_correct_credentials(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);
    }

    public function test_it_rejects_a_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertUnauthorized()
            ->assertJson(['message' => 'Invalid credentials.']);
    }

    public function test_it_rejects_an_unknown_email(): void
    {
        $this->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'password'])
            ->assertUnauthorized();
    }

    // ---------- ME / LOGOUT ----------

    public function test_me_requires_a_token(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_me_returns_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->json('token');

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->json('token');

        $this->withToken($token)->postJson('/api/logout')->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    // ---------- PRODUCT ROUTE PROTECTION ----------

    public function test_guests_can_read_products(): void
    {
        $product = Product::factory()->create();

        $this->getJson('/api/products')->assertOk();
        $this->getJson("/api/products/{$product->id}")->assertOk();
    }

    public function test_guests_cannot_write_products(): void
    {
        $product = Product::factory()->create();

        $this->postJson('/api/products', ['name' => 'X', 'sku' => 'X-1', 'price' => 1, 'stock' => 1])
            ->assertUnauthorized();
        $this->putJson("/api/products/{$product->id}", ['price' => 1])
            ->assertUnauthorized();
        $this->deleteJson("/api/products/{$product->id}")
            ->assertUnauthorized();
    }

    public function test_unauthenticated_requests_get_json_even_without_accept_header(): void
    {
        $this->post('/api/products', [])
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }
}
