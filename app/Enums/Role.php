<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case BranchAdmin = 'branch_admin';
    case Manager = 'manager';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::BranchAdmin => 'Branch Admin',
            self::Manager => 'Manager',
        };
    }

    /** Roles that are scoped to a branch and campaign assignments. */
    public function isScoped(): bool
    {
        return $this !== self::SuperAdmin;
    }

    public static function options(): array
    {
        return array_map(fn (self $r) => ['value' => $r->value, 'label' => $r->label()], self::cases());
    }
}
