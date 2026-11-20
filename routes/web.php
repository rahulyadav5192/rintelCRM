<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CampaignStatusController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadExportController;
use App\Http\Controllers\LeadNoteController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicLeadController;
use App\Http\Controllers\PublicLeadFormController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

// ---- Public lead submission (no authentication) ------------------------------------------
Route::prefix('public/leads/{token}')->name('public-leads.')->where(['token' => '[A-Za-z0-9]{32,128}'])->group(function () {
    Route::get('/', [PublicLeadController::class, 'show'])->name('show');
    Route::get('/campaigns/{campaign}', [PublicLeadController::class, 'options'])->whereNumber('campaign')->middleware('throttle:60,1')->name('options');
    Route::post('/', [PublicLeadController::class, 'store'])->middleware('throttle:public-leads')->name('store');
});

// ---- Authentication -----------------------------------------------------------------------
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Leads
    Route::get('leads/export', LeadExportController::class)->name('leads.export');
    Route::resource('leads', LeadController::class);
    Route::post('leads/{lead}/notes', [LeadNoteController::class, 'store'])->name('leads.notes.store');

    // Dynamic form options (JSON, authorization-scoped)
    Route::get('lookup/branches/{branch}/campaigns', [LookupController::class, 'branchCampaigns'])->name('lookup.branch-campaigns');
    Route::get('lookup/branches/{branch}/campaigns/{campaign}', [LookupController::class, 'campaignOptions'])->name('lookup.campaign-options');

    // Branches (Super Admin)
    Route::patch('branches/{branch}/toggle', [BranchController::class, 'toggle'])->name('branches.toggle');
    Route::resource('branches', BranchController::class)->except('destroy');

    // Campaigns
    Route::patch('campaigns/{campaign}/toggle', [CampaignController::class, 'toggle'])->name('campaigns.toggle');
    Route::resource('campaigns', CampaignController::class)->except('destroy');
    Route::get('campaigns/{campaign}/statuses', [CampaignStatusController::class, 'index'])->name('campaigns.statuses');
    Route::post('campaigns/{campaign}/statuses/{type}', [CampaignStatusController::class, 'store'])->whereIn('type', ['lead', 'payment'])->name('campaigns.statuses.store');
    Route::put('campaigns/{campaign}/statuses/{type}/{status}', [CampaignStatusController::class, 'update'])->whereIn('type', ['lead', 'payment'])->name('campaigns.statuses.update');
    Route::post('campaigns/{campaign}/statuses/{type}/reorder', [CampaignStatusController::class, 'reorder'])->whereIn('type', ['lead', 'payment'])->name('campaigns.statuses.reorder');

    // Users
    Route::patch('users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
    Route::put('users/{user}/password', [UserController::class, 'resetPassword'])->name('users.password');
    Route::resource('users', UserController::class)->except('destroy');

    // Public lead forms (Super Admin)
    Route::get('public-forms', [PublicLeadFormController::class, 'index'])->name('public-forms.index');
    Route::post('public-forms/{branch}', [PublicLeadFormController::class, 'store'])->name('public-forms.store');
    Route::post('public-forms/{publicForm}/regenerate', [PublicLeadFormController::class, 'regenerate'])->name('public-forms.regenerate');
    Route::patch('public-forms/{publicForm}/toggle', [PublicLeadFormController::class, 'toggle'])->name('public-forms.toggle');

    // Reports, logs, notifications, settings, profile
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'password'])->name('profile.password');
});
