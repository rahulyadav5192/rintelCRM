<?php

namespace App\Enums;

/**
 * Per-user grantable permissions. Super Admins implicitly hold all of them.
 */
enum Permission: string
{
    case CreateLeads = 'create_leads';
    case EditLeads = 'edit_leads';
    case AssignLeads = 'assign_leads';
    case UpdateLeadStatus = 'update_lead_status';
    case UpdatePaymentStatus = 'update_payment_status';
    case DeleteLeads = 'delete_leads';
    case ExportLeads = 'export_leads';
    case ViewReports = 'view_reports';

    public function label(): string
    {
        return match ($this) {
            self::CreateLeads => 'Create leads',
            self::EditLeads => 'Edit lead details',
            self::AssignLeads => 'Assign / reassign leads',
            self::UpdateLeadStatus => 'Update lead status',
            self::UpdatePaymentStatus => 'Update payment status',
            self::DeleteLeads => 'Delete leads',
            self::ExportLeads => 'Export leads',
            self::ViewReports => 'View reports',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CreateLeads => 'Add new leads in accessible campaigns.',
            self::EditLeads => 'Change personal details, campaign and notes of a lead.',
            self::AssignLeads => 'Change the advisor a lead is assigned to.',
            self::UpdateLeadStatus => 'Move leads between campaign lead statuses.',
            self::UpdatePaymentStatus => 'Change the payment status of leads.',
            self::DeleteLeads => 'Permanently remove leads from listings.',
            self::ExportLeads => 'Download accessible leads as CSV / Excel.',
            self::ViewReports => 'Open the reports module for accessible data.',
        };
    }

    /** Permissions that make sense for a given role (used by the UI and validation). */
    public static function forRole(Role $role): array
    {
        return match ($role) {
            Role::SuperAdmin => [],
            // Admins can only view a lead's assignment and status once it exists, so the
            // assign / status / payment-status permissions never apply to them.
            Role::Admin => [self::CreateLeads, self::EditLeads, self::DeleteLeads, self::ExportLeads, self::ViewReports],
            Role::Manager => self::cases(),
        };
    }

    public static function defaultsFor(Role $role): array
    {
        return match ($role) {
            Role::SuperAdmin => [],
            Role::Admin => [self::CreateLeads, self::EditLeads, self::ViewReports],
            Role::Manager => [self::UpdateLeadStatus, self::UpdatePaymentStatus],
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $p) => [
            'value' => $p->value,
            'label' => $p->label(),
            'description' => $p->description(),
            'roles' => array_values(array_map(fn (Role $r) => $r->value, array_filter(Role::cases(), fn ($r) => in_array($p, self::forRole($r), true)))),
        ], self::cases());
    }
}
