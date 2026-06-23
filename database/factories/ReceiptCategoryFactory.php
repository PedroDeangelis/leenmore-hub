<?php

namespace Database\Factories;

use App\Models\ReceiptCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceiptCategory>
 */
class ReceiptCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'position' => 0,
        ];
    }
}
