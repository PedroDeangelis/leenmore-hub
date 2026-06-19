<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-2 months', '+1 month');

        return [
            'title' => fake()->unique()->company().' '.fake()->year().' Campaign',
            'status' => fake()->randomElement(ProjectStatus::assignable()),
            'message' => fake()->optional()->paragraph(),
            'start_date' => $start,
            'end_date' => fake()->dateTimeBetween($start, '+3 months'),
            'shares_issued' => fake()->numberBetween(10_000, 1_000_000),
            'shares_target' => fake()->numberBetween(10_000, 1_000_000),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ProjectStatus::Draft]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ProjectStatus::Publish]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ProjectStatus::Archived]);
    }

    /**
     * Note: the model's global scope hides deleted projects, so re-read them in
     * tests with Project::withDeleted().
     */
    public function deleted(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ProjectStatus::Deleted]);
    }
}
