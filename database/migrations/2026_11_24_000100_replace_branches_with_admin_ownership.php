<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes branches entirely. Leads are now owned by an Admin (leads.admin_id) and public lead
 * forms belong to an Admin (public_lead_forms.user_id) instead of a branch. Managers are global
 * and managed only by Super Admins.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'branch_admin')->update(['role' => 'admin']);
        DB::table('user_permissions')->where('permission', 'manage_users')->delete();

        // Leads: branch → owning admin. Existing leads go to the first admin of their old branch
        // who has the lead's campaign.
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('admin_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->index(['admin_id', 'campaign_id']);
        });
        DB::statement("UPDATE leads l SET admin_id = (
            SELECT u.id FROM users u JOIN campaign_user cu ON cu.user_id = u.id
            WHERE u.role = 'admin' AND u.branch_id = l.branch_id AND cu.campaign_id = l.campaign_id
            ORDER BY u.id LIMIT 1)");
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropIndex(['branch_id', 'campaign_id']);
            $table->dropColumn('branch_id');
        });

        // Public forms: branch → admin. Forms whose branch had no admin cannot be mapped.
        Schema::table('public_lead_forms', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id');
        });
        DB::statement("UPDATE public_lead_forms f SET user_id = (
            SELECT u.id FROM users u WHERE u.role = 'admin' AND u.branch_id = f.branch_id ORDER BY u.id LIMIT 1)");
        DB::table('public_lead_forms')->whereNull('user_id')->delete();
        Schema::table('public_lead_forms', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropUnique(['branch_id']);
            $table->dropColumn('branch_id');
            $table->unique('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });

        Schema::dropIfExists('branch_campaign');
        Schema::dropIfExists('branches');
    }

    /** Restores the branch structure (empty); ownership data cannot be converted back. */
    public function down(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 30)->unique();
            $table->text('address')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_number', 30)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('branch_campaign', function (Blueprint $table) {
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['branch_id', 'campaign_id']);
            $table->index('campaign_id');
        });
        Schema::table('users', fn (Blueprint $table) => $table->foreignId('branch_id')->nullable()->after('role')->constrained()->restrictOnDelete());

        DB::table('public_lead_forms')->delete();
        Schema::table('public_lead_forms', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id']);
            $table->dropColumn('user_id');
            $table->foreignId('branch_id')->unique()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('post_code')->constrained()->restrictOnDelete();
            $table->index(['branch_id', 'campaign_id']);
            $table->dropForeign(['admin_id']);
            $table->dropIndex(['admin_id', 'campaign_id']);
            $table->dropColumn('admin_id');
        });

        DB::table('users')->where('role', 'admin')->update(['role' => 'branch_admin']);
    }
};
