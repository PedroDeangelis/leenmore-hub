<?php

namespace Database\Factories;

use App\Enums\ResultColor;
use App\Models\Project;
use App\Models\ProjectResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectResult>
 */
class ProjectResultFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->unique()->word(),
            'color' => fake()->randomElement(ResultColor::cases()),
            'contact_required' => fake()->boolean(),
            'attachment_required' => fake()->boolean(),
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }
}
