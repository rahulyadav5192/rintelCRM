<?php

namespace Database\Factories;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'mobile' => '07'.fake()->numerify('#########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function configure(): static
    {
        // role/branch/is_active are not fillable, so apply them after making the model.
        return $this->afterMaking(function (User $user) {
            $user->role ??= Role::Manager;
            $user->is_active ??= true;
        });
    }

    public function superAdmin(): static
    {
        return $this->afterMaking(fn (User $u) => $u->role = Role::SuperAdmin);
    }

    public function branchAdmin(Branch $branch): static
    {
        return $this->afterMaking(function (User $u) use ($branch) {
            $u->role = Role::BranchAdmin;
            $u->branch_id = $branch->id;
        });
    }

    public function manager(Branch $branch, string $visibility = User::VISIBILITY_CAMPAIGN): static
    {
        return $this->afterMaking(function (User $u) use ($branch, $visibility) {
            $u->role = Role::Manager;
            $u->branch_id = $branch->id;
            $u->lead_visibility = $visibility;
        });
    }

    public function inactive(): static
    {
        return $this->afterMaking(fn (User $u) => $u->is_active = false);
    }

    /** @param array<Campaign|int> $campaigns */
    public function withCampaigns(array $campaigns): static
    {
        return $this->afterCreating(fn (User $u) => $u->campaigns()->sync(array_map(fn ($c) => $c instanceof Campaign ? $c->id : $c, $campaigns)));
    }

    /** @param array<Permission> $permissions */
    public function withPermissions(array $permissions): static
    {
        return $this->afterCreating(fn (User $u) => $u->syncPermissions(array_map(fn (Permission $p) => $p->value, $permissions)));
    }

    public function withDefaultPermissions(): static
    {
        return $this->afterCreating(fn (User $u) => $u->syncPermissions(array_map(fn (Permission $p) => $p->value, Permission::defaultsFor($u->role))));
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
