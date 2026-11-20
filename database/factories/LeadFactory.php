<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lead_date' => fake()->dateTimeBetween('-60 days')->format('Y-m-d'),
            'title' => fake()->randomElement(Lead::TITLES),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'mobile' => '07'.fake()->numerify('#########'),
            'email' => fake()->safeEmail(),
            'house_number' => (string) fake()->buildingNumber(),
            'post_code' => strtoupper(fake()->bothify('??# #??')),
        ];
    }

    /** Places the lead in a branch + campaign with the campaign's default statuses. */
    public function for_(Branch $branch, Campaign $campaign, ?User $assignee = null): static
    {
        return $this->afterMaking(function (Lead $lead) use ($branch, $campaign, $assignee) {
            $lead->branch_id = $branch->id;
            $lead->campaign_id = $campaign->id;
            $lead->assigned_to = $assignee?->id;
            $lead->lead_status_id = $campaign->defaultLeadStatus()->id;
            $lead->payment_status_id = $campaign->defaultPaymentStatus()?->id;
        })->afterCreating(function (Lead $lead) {
            $lead->lead_number = 'RL-'.str_pad((string) $lead->id, 6, '0', STR_PAD_LEFT);
            $lead->saveQuietly();
        });
    }
}
