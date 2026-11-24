# Rintel Lead CRM

Multi-campaign lead management CRM built with **Laravel 13**, **Inertia.js v3**, **React 19**, **Tailwind CSS 4**, **Lucide** icons and **MySQL**.

## Features

- **Roles**:
  - **Super Admin**: unrestricted. The only role that manages users, campaigns, statuses, public forms and settings.
  - **Admin**: sees and manages only the leads they **own** (created by them, received through their public form, or assigned to them as owner by a Super Admin) in the campaigns assigned to them.
  - **Manager**: global (not tied to any admin). Works leads in their assigned campaigns, with per-user visibility of *all leads in their campaigns* or *only leads assigned to them*.
- **Per-user permissions**: create, edit details, assign, update lead status, update payment status, delete, export, view reports.
- **Campaigns and per-campaign statuses**, all stored in the database. The **Status Management** page creates a lead or payment status once and adds it to any number of campaigns, and edits (rename, recolour, activate or deactivate) it across campaigns in one step. Per-campaign ordering and defaults are still configured on each campaign.
- **Leads**: backend-generated IDs (`RL-000123`, prefix configurable), dynamic branch → campaign → advisor/status dropdowns, notes, full activity timeline, follow-up dates, soft delete.
- **Public lead forms**: every Admin gets one secure URL automatically (256-bit token stored as a SHA-256 hash plus an encrypted copy). Leads submitted through it are owned by that Admin, and visitors can only pick that Admin's active, public campaigns. Super Admins can reset or deactivate any URL; Admins can view and copy their own. Submissions are rate-limited, with honeypot and timing checks against spam.
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
| admin@rintel.test | Admin | Own leads in IVA, TDS, Solar, HDR (can export and delete) |
| admin2@rintel.test | Admin | Own leads in IVA, TDS |
| manager@rintel.test | Manager | IVA, TDS (edit, export, reports, create) |
| manager2@rintel.test | Manager | Solar, HDR (status/payment only) |
| manager3@rintel.test | Manager | IVA, TDS, Solar (sees only leads assigned to them) |
| manager4@rintel.test | Manager | Solar |

Every admin's public form URL is listed under **Public Lead Forms** (Super Admin) or **My Public Form** (that admin).

## Tests

Tests run against a MySQL database named `rintel_crm_test` (see `phpunit.xml`):

```bash
php artisan test
```

They cover role scoping (admins see only their own leads), IDOR attempts, Super-Admin-only user management, edit/assign/export permissions, lead ownership rules, campaign-specific status validation, cross-campaign status management, public form ownership, token reset, inactive admin/campaign handling, spam protection and rate limiting.

## Project layout

```
app/Enums             Role, Permission
app/Models            Eloquent models + access scopes
app/Policies          LeadPolicy, CampaignPolicy, UserPolicy
app/Services          LeadService, LeadFilters, LeadStats, ActivityLogger
app/Http/Controllers  One controller per module
resources/js/components  Reusable UI (DataTable, FormInput, Modal, StatusBadge, …)
resources/js/layouts     AppLayout, Sidebar, Header
resources/js/pages       Inertia pages per module
```
