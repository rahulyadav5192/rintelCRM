<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Admins can no longer change a lead's assignment or statuses (view only), so these
 * permissions are removed from existing Admin accounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('user_permissions')
            ->whereIn('permission', ['assign_leads', 'update_lead_status', 'update_payment_status'])
            ->whereIn('user_id', DB::table('users')->where('role', 'admin')->select('id'))
            ->delete();
    }

    public function down(): void
    {
        // Not restorable: the removed grants were not recorded.
    }
};
