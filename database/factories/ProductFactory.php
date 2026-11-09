<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####-??')),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 5, 500),
            'stock' => fake()->numberBetween(0, 200),
            'is_active' => fake()->boolean(90),
        ];
    }
}
