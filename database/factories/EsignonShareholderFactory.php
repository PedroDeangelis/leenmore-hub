<?php

namespace Database\Factories;

use App\Models\EsignonShareholder;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EsignonShareholder>
 */
class EsignonShareholderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'link_manage_id' => (string) fake()->numberBetween(1000, 9999),
            'link_id' => (string) Str::uuid(),
            'identifier' => fake()->numerify('######'),
            'name' => fake()->name(),
            'contact' => fake()->numerify('010-####-####'),
            'completed_date' => fake()->dateTimeBetween('-2 weeks', 'now'),
            'data_hash' => hash('sha256', (string) Str::uuid()),
            'needs_sync' => true,
            'synced_at' => null,
        ];
    }

    /**
     * A row that has already been matched to a roster assignment.
     */
    public function synced(): static
    {
        return $this->state(fn (array $attributes): array => [
            'needs_sync' => false,
            'synced_at' => now(),
        ]);
    }
}
