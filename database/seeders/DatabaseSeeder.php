<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Branch;
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
            $main = $this->branch('Main Branch', 'MAIN', 'Sarah Thompson');
            $second = $this->branch('Branch 2', 'BR2', 'James Carter');

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

            $main->campaigns()->sync(collect($campaigns)->pluck('id'));
            $second->campaigns()->sync([$campaigns['IVA']->id, $campaigns['TDS']->id, $campaigns['Solar']->id]);

            $this->user('Super Admin', 'superadmin@rintel.test', Role::SuperAdmin);
            $this->user('Main Branch Admin', 'branchadmin@rintel.test', Role::BranchAdmin, $main, [$campaigns['IVA'], $campaigns['TDS'], $campaigns['Solar'], $campaigns['HDR']],
                [...Permission::defaultsFor(Role::BranchAdmin), Permission::ExportLeads, Permission::ManageUsers, Permission::DeleteLeads]);
            $this->user('Branch 2 Admin', 'branch2admin@rintel.test', Role::BranchAdmin, $second, [$campaigns['IVA'], $campaigns['TDS']], Permission::defaultsFor(Role::BranchAdmin));

            $managers = [
                $this->user('Priya Patel', 'manager@rintel.test', Role::Manager, $main, [$campaigns['IVA'], $campaigns['TDS']],
                    [Permission::EditLeads, Permission::UpdateLeadStatus, Permission::UpdatePaymentStatus, Permission::ExportLeads, Permission::ViewReports, Permission::CreateLeads]),
                $this->user('Daniel Hughes', 'manager2@rintel.test', Role::Manager, $main, [$campaigns['Solar'], $campaigns['HDR']],
                    Permission::defaultsFor(Role::Manager)),
                $this->user('Aisha Khan', 'manager3@rintel.test', Role::Manager, $second, [$campaigns['IVA'], $campaigns['TDS'], $campaigns['Solar']],
                    Permission::defaultsFor(Role::Manager), User::VISIBILITY_ASSIGNED),
                $this->user('Tom Wilson', 'manager4@rintel.test', Role::Manager, $second, [$campaigns['Solar']],
                    Permission::defaultsFor(Role::Manager)),
            ];

            foreach ([$main, $second] as $branch) {
                $form = new PublicLeadForm(['is_active' => true]);
                $form->branch_id = $branch->id;
                $form->rotateToken();
                $form->save();
            }

            // Sample leads spread over the last ~90 days.
            $super = User::where('email', 'superadmin@rintel.test')->first();
            foreach ([$main, $second] as $branch) {
                foreach ($branch->campaigns()->get() as $campaign) {
                    $eligible = collect($managers)->filter(fn (User $m) => $m->branch_id === $branch->id && $m->campaigns->contains('id', $campaign->id))->values();
                    $statuses = $campaign->leadStatuses()->get();
                    $payments = $campaign->paymentStatuses()->get();
                    for ($i = 0; $i < 18; $i++) {
                        $lead = Lead::factory()->for_($branch, $campaign, $eligible->isNotEmpty() && $i % 5 !== 0 ? $eligible->random() : null)->make([
                            'lead_date' => now()->subDays(random_int(0, 90))->toDateString(),
                            'notes' => $i % 3 === 0 ? fake()->sentence(12) : null,
                            'follow_up_date' => $i % 4 === 0 ? now()->addDays(random_int(-3, 10))->toDateString() : null,
                        ]);
                        $lead->lead_status_id = $statuses->random()->id;
                        $lead->payment_status_id = $payments->random()->id;
                        $lead->source = $i % 6 === 0 ? 'public' : 'manual';
                        $lead->created_by = $lead->source === 'public' ? null : $super->id;
                        $lead->save();
                        $lead->lead_number = 'RL-'.str_pad((string) $lead->id, 6, '0', STR_PAD_LEFT);
                        $lead->saveQuietly();
                        $lead->activities()->create(['type' => 'created', 'description' => $lead->source === 'public' ? 'Lead submitted via public form' : 'Lead created', 'user_id' => $lead->source === 'public' ? null : $super->id]);
                    }
                }
            }
        });
    }

    private function branch(string $name, string $code, string $contact): Branch
    {
        $branch = new Branch(['name' => $name, 'code' => $code, 'contact_person' => $contact, 'contact_number' => '020 7946 0'.random_int(100, 999),
            'email' => strtolower($code).'@rintel.test', 'address' => fake()->streetAddress().', London']);
        $branch->is_active = true;
        $branch->save();

        return $branch;
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

    private function user(string $name, string $email, Role $role, ?Branch $branch = null, array $campaigns = [], array $permissions = [], string $visibility = User::VISIBILITY_CAMPAIGN): User
    {
        $user = new User(['name' => $name, 'email' => $email, 'mobile' => '07700 900'.random_int(100, 999), 'password' => Hash::make('password')]);
        $user->role = $role;
        $user->branch_id = $branch?->id;
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
