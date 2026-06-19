<?php

namespace Database\Factories;

use App\Enums\PersonType;
use App\Models\Shareholder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shareholder>
 */
class ShareholderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dob = fake()->dateTimeBetween('-80 years', '-20 years');

        return [
            'name' => fake()->name(),
            'registration' => fake()->unique()->numerify('######-#######'),
            'sex' => fake()->randomElement(['M', 'F']),
            'person_type' => fake()->randomElement(PersonType::cases()),
            'date_of_birth' => $dob,
            'date_of_birth_code' => $dob->format('ymd'),
            'code' => fake()->optional()->bothify('CODE-####'),
            'contact_info' => fake()->numerify('010-####-####'),
            'address' => fake()->address(),
        ];
    }

    public function individual(): static
    {
        return $this->state(fn (array $attributes) => ['person_type' => PersonType::Individual]);
    }

    public function corporation(): static
    {
        return $this->state(fn (array $attributes) => ['person_type' => PersonType::Corporation]);
    }
}
