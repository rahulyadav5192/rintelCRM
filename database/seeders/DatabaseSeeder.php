<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\PublicLeadForm;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Development data. All accounts use the password "password".
     */
    public function run(): void
    {
        DB::transaction(function () {
            $campaigns = [
                'IVA' => $this->campaign('IVA', 'IVA', 'Individual Voluntary Arrangement leads.', true,
                    [['New', 'blue'], ['Contacted', 'amber'], ['Interested', 'violet'], ['Documents Pending', 'orange'], ['Completed', 'green'], ['Rejected', 'red']]),
                'TDS' => $this->campaign('TDS', 'TDS', 'Tax deduction services.', true,
                    [['New', 'blue'], ['Follow Up', 'amber'], ['Processing', 'indigo'], ['Approved', 'emerald'], ['Completed', 'green'], ['Rejected', 'red']]),
                'Solar' => $this->campaign('Solar', 'SOLAR', 'Residential solar panel installation.', true,
                    [['New', 'blue'], ['Contacted', 'amber'], ['Site Visit', 'violet'], ['Quotation Sent', 'sky'], ['Installation', 'orange'], ['Completed', 'green'], ['Rejected', 'red']]),
                'HDR' => $this->campaign('HDR', 'HDR', 'Housing disrepair claims.', false,
                    [['New', 'blue'], ['Contacted', 'amber'], ['Survey Booked', 'violet'], ['Claim Submitted', 'indigo'], ['Completed', 'green'], ['Rejected', 'red']]),
            ];

            $super = $this->user('Super Admin', 'superadmin@rintel.test', Role::SuperAdmin);
            $admins = [
                $this->user('Olivia Bennett', 'admin@rintel.test', Role::Admin, array_values($campaigns),
                    [...Permission::defaultsFor(Role::Admin), Permission::ExportLeads, Permission::DeleteLeads]),
                $this->user('Marcus Reed', 'admin2@rintel.test', Role::Admin, [$campaigns['IVA'], $campaigns['TDS']], Permission::defaultsFor(Role::Admin)),
            ];

            $managers = [
                $this->user('Priya Patel', 'manager@rintel.test', Role::Manager, [$campaigns['IVA'], $campaigns['TDS']],
                    [Permission::EditLeads, Permission::UpdateLeadStatus, Permission::UpdatePaymentStatus, Permission::ExportLeads, Permission::ViewReports, Permission::CreateLeads]),
                $this->user('Daniel Hughes', 'manager2@rintel.test', Role::Manager, [$campaigns['Solar'], $campaigns['HDR']], Permission::defaultsFor(Role::Manager)),
                $this->user('Aisha Khan', 'manager3@rintel.test', Role::Manager, [$campaigns['IVA'], $campaigns['TDS'], $campaigns['Solar']],
                    Permission::defaultsFor(Role::Manager), User::VISIBILITY_ASSIGNED),
                $this->user('Tom Wilson', 'manager4@rintel.test', Role::Manager, [$campaigns['Solar']], Permission::defaultsFor(Role::Manager)),
            ];

            $forms = [];
            foreach ($admins as $admin) {
                $forms[$admin->id] = PublicLeadForm::ensureFor($admin, $super->id);
            }

            // Sample leads spread over the last ~90 days, owned by admins that have the campaign.
            foreach ($admins as $admin) {
                foreach ($admin->campaigns as $campaign) {
                    $eligible = collect($managers)->filter(fn (User $m) => $m->campaigns->contains('id', $campaign->id))->values();
                    $statuses = $campaign->leadStatuses()->get();
                    $payments = $campaign->paymentStatuses()->get();
                    for ($i = 0; $i < 16; $i++) {
                        $lead = Lead::factory()->for_($campaign, $admin, $eligible->isNotEmpty() && $i % 5 !== 0 ? $eligible->random() : null)->make([
                            'lead_date' => now()->subDays(random_int(0, 90))->toDateString(),
                            'notes' => $i % 3 === 0 ? fake()->sentence(12) : null,
                            'follow_up_date' => $i % 4 === 0 ? now()->addDays(random_int(-3, 10))->toDateString() : null,
                        ]);
                        $lead->lead_status_id = $statuses->random()->id;
                        $lead->payment_status_id = $payments->random()->id;
                        $lead->source = $i % 6 === 0 ? 'public' : 'manual';
                        $lead->created_by = $lead->source === 'public' ? null : ($i % 2 ? $admin->id : $super->id);
                        $lead->public_lead_form_id = $lead->source === 'public' ? $forms[$admin->id]->id : null;
                        $lead->save();
                        $lead->lead_number = 'RL-'.str_pad((string) $lead->id, 6, '0', STR_PAD_LEFT);
                        $lead->saveQuietly();
                        if ($lead->source === 'public') {
                            $forms[$admin->id]->increment('submissions_count');
                        }
                        $lead->activities()->create(['type' => 'created', 'description' => $lead->source === 'public' ? 'Lead submitted via public form' : 'Lead created', 'user_id' => $lead->created_by]);
                    }
                }
            }
        });
    }

    private function campaign(string $name, string $code, string $description, bool $public, array $statuses): Campaign
    {
        $campaign = new Campaign(['name' => $name, 'code' => $code, 'description' => $description, 'is_public' => $public, 'public_show_statuses' => false]);
        $campaign->is_active = true;
        $campaign->save();
        foreach ($statuses as $i => [$s, $color]) {
            $campaign->leadStatuses()->create(['name' => $s, 'color' => $color, 'sort_order' => $i + 1, 'is_default' => $i === 0, 'is_active' => true]);
        }
        foreach ([['Pending', 'amber'], ['Partially Paid', 'sky'], ['Paid', 'green'], ['Refunded', 'violet'], ['Failed', 'red']] as $i => [$s, $color]) {
            $campaign->paymentStatuses()->create(['name' => $s, 'color' => $color, 'sort_order' => $i + 1, 'is_default' => $i === 0, 'is_active' => true]);
        }

        return $campaign;
    }

    private function user(string $name, string $email, Role $role, array $campaigns = [], array $permissions = [], string $visibility = User::VISIBILITY_CAMPAIGN): User
    {
        $user = new User(['name' => $name, 'email' => $email, 'mobile' => '07700 900'.random_int(100, 999), 'password' => Hash::make('password')]);
        $user->role = $role;
        $user->lead_visibility = $visibility;
        $user->is_active = true;
        $user->email_verified_at = now();
        $user->save();
        $user->campaigns()->sync(array_map(fn ($c) => $c->id, $campaigns));
        $user->syncPermissions(array_map(fn (Permission $p) => $p->value, $permissions));
        $user->load('campaigns');

        return $user;
    }
}
