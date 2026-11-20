# Rintel Lead CRM

Multi-branch, multi-campaign lead management CRM built with **Laravel 13**, **Inertia.js v3**, **React 19**, **Tailwind CSS 4**, **Lucide** icons and **MySQL**.

## Features

- **Roles**: Super Admin (unrestricted), Branch Admin (own branch + assigned campaigns), Manager (assigned campaigns; per-user lead visibility of *all branch leads in campaign* or *only assigned leads*).
- **Per-user permissions**: create, edit details, assign, update lead status, update payment status, delete, export, view reports, manage branch managers.
- **Branches, campaigns and per-campaign statuses** — all database-driven: lead and payment statuses are created, renamed, recoloured, reordered, defaulted and deactivated per campaign.
- **Leads**: backend-generated IDs (`RL-000123`, prefix configurable), dynamic branch → campaign → advisor/status dropdowns, notes, full activity timeline, follow-up dates, soft delete.
- **Public lead forms**: one secure URL per branch (256-bit token stored as SHA-256 hash + encrypted copy), reset/deactivate, rate limiting, honeypot + timing spam protection.
- **Dashboards** per role, **reports** (branch / campaign / manager / status / payment / date-wise), **CSV & Excel export**, **activity log**, in-app notifications.

## Security model

All authorization is server-side:

- `Lead::scopeVisibleTo($user)` (`app/Models/Lead.php`) is the single lead-visibility rule. Listing, detail pages, exports, dashboards, reports, notifications and lookups all go through it, so changing an ID in a URL or request never widens access.
- Policies (`app/Policies`) and gates (`AppServiceProvider`) protect every route.
- `LeadService` validates every branch ↔ campaign ↔ advisor ↔ status combination and enforces per-field permissions on update.
- The database enforces that a lead's lead/payment status belongs to the lead's campaign (composite foreign keys).
- Sensitive attributes (role, branch, lead number, assignment, statuses) are not mass assignable.

## Setup

Requirements: PHP 8.3+, Composer, Node 20+, MySQL 8 / MariaDB 10.6+.

```bash
composer install
cp .env.example .env      # set DB_DATABASE / DB_USERNAME / DB_PASSWORD
php artisan key:generate
php artisan migrate --seed
npm install
npm run build             # or: npm run dev
php artisan serve
```

### Sample accounts (password: `password`)

| Email | Role | Scope |
|---|---|---|
| superadmin@rintel.test | Super Admin | Everything |
| branchadmin@rintel.test | Branch Admin | Main Branch — IVA, TDS, Solar, HDR (can export, delete, manage managers) |
| branch2admin@rintel.test | Branch Admin | Branch 2 — IVA, TDS |
| manager@rintel.test | Manager | Main Branch — IVA, TDS (edit, export, reports, create) |
| manager2@rintel.test | Manager | Main Branch — Solar, HDR (status/payment only) |
| manager3@rintel.test | Manager | Branch 2 — IVA, TDS, Solar (sees only assigned leads) |
| manager4@rintel.test | Manager | Branch 2 — Solar |

Public form URLs for each branch are listed under **Public Lead Forms** when signed in as Super Admin.

## Tests

Tests run against a MySQL database named `rintel_crm_test` (see `phpunit.xml`):

```bash
php artisan test
```

They cover role scoping, IDOR attempts, edit/assign/export permissions, campaign-specific status validation, public form branch binding, token reset, inactive branch/campaign handling, spam protection and rate limiting.

## Project layout

```
app/Enums             Role, Permission
app/Models            Eloquent models + access scopes
app/Policies          LeadPolicy, BranchPolicy, CampaignPolicy, UserPolicy
app/Services          LeadService, LeadFilters, LeadStats, ActivityLogger
app/Http/Controllers  One controller per module
resources/js/components  Reusable UI (DataTable, FormInput, Modal, StatusBadge, …)
resources/js/layouts     AppLayout, Sidebar, Header
resources/js/pages       Inertia pages per module
```
