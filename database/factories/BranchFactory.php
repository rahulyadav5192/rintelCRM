<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->city().' Branch',
            'code' => strtoupper(fake()->unique()->bothify('BR-###??')),
            'address' => fake()->address(),
            'contact_person' => fake()->name(),
            'contact_number' => '0'.fake()->numerify('##########'),
            'email' => fake()->safeEmail(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(fn (Branch $b) => $b->is_active ??= true);
    }

    public function inactive(): static
    {
        return $this->afterMaking(fn (Branch $b) => $b->is_active = false);
    }
}
