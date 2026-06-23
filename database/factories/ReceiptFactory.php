<?php

namespace Database\Factories;

use App\Models\Receipt;
use App\Models\ReceiptCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Receipt>
 */
class ReceiptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->worker(),
            'user_name' => fake()->name(),
            'receipt_category_id' => ReceiptCategory::factory(),
            'category_name' => '식대',
            'date' => fake()->dateTimeBetween('-1 month')->format('Y-m-d'),
            'vendor' => fake()->company(),
            'amount' => fake()->numberBetween(1000, 50000),
            'notes' => null,
            'attachment' => null,
        ];
    }
}
