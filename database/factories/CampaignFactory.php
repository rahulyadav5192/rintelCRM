<?php

namespace Database\Factories;

use App\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()).' Campaign',
            'code' => strtoupper(fake()->unique()->bothify('C###??')),
            'description' => fake()->sentence(),
            'is_public' => true,
            'public_show_statuses' => false,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(fn (Campaign $c) => $c->is_active ??= true)
            ->afterCreating(function (Campaign $c) {
                foreach ([['New', 'blue'], ['Contacted', 'amber'], ['Completed', 'green'], ['Rejected', 'red']] as $i => [$name, $color]) {
                    $c->leadStatuses()->create(['name' => $name, 'color' => $color, 'sort_order' => $i + 1, 'is_default' => $i === 0, 'is_active' => true]);
                }
                foreach ([['Pending', 'amber'], ['Paid', 'green'], ['Failed', 'red']] as $i => [$name, $color]) {
                    $c->paymentStatuses()->create(['name' => $name, 'color' => $color, 'sort_order' => $i + 1, 'is_default' => $i === 0, 'is_active' => true]);
                }
            });
    }

    public function inactive(): static
    {
        return $this->afterMaking(fn (Campaign $c) => $c->is_active = false);
    }
}
