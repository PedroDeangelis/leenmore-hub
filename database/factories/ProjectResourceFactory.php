<?php

namespace Database\Factories;

use App\Enums\ResourceType;
use App\Models\Project;
use App\Models\ProjectResource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectResource>
 */
class ProjectResourceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'type' => ResourceType::Link,
            'title' => fake()->sentence(3),
            'url' => fake()->url(),
            'file_path' => null,
            'file_name' => null,
            'sort_order' => 0,
        ];
    }

    /**
     * A link resource (url + title).
     */
    public function link(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ResourceType::Link,
            'url' => fake()->url(),
            'file_path' => null,
            'file_name' => null,
        ]);
    }

    /**
     * A file resource (stored path + original filename).
     */
    public function file(): static
    {
        $name = fake()->word().'.pdf';

        return $this->state(fn (array $attributes) => [
            'type' => ResourceType::File,
            'url' => null,
            'file_path' => 'resources/1/'.fake()->uuid().'.pdf',
            'file_name' => $name,
            'title' => $name,
        ]);
    }
}
