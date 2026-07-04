<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $cost = fake()->numberBetween(500, 8000) / 100;
        $retail = round($cost * fake()->randomFloat(2, 1.4, 2.5), 2);

        return [
            'name' => Str::title(fake()->words(3, true)),
            'sku' => strtoupper(Str::random(3)).'-'.fake()->unique()->numberBetween(1000, 9999),
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'description' => fake()->sentence(12),
            'cost_price' => $cost,
            'retail_price' => $retail,
            'partner_discount' => null,
            'stock' => fake()->numberBetween(0, 250),
            'is_active' => true,
        ];
    }
}
