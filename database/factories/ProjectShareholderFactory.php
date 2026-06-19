<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectShareholder;
use App\Models\Shareholder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectShareholder>
 */
class ProjectShareholderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'shareholder_id' => Shareholder::factory(),
            'shares' => fake()->numberBetween(1, 100_000),
            'shares_total' => fake()->numberBetween(1, 100_000),
            // Overrides default to null so the fallback-to-person path is exercised.
            'contact_info' => null,
            'contact_info_2' => null,
            'address' => null,
            'contact_worker' => null,
            'result_id' => null,
            'electronic_voting' => fake()->boolean(),
            'no' => fake()->numberBetween(1, 500),
            'row_no' => fake()->numberBetween(1, 500),
        ];
    }

    /**
     * Give the assignment its own per-project contact (overriding the person's).
     */
    public function withOwnContact(string $contact): static
    {
        return $this->state(fn (array $attributes) => ['contact_info' => $contact]);
    }
}
