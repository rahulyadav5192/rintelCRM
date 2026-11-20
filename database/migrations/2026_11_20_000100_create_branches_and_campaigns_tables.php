<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
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

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 30)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            // Whether the campaign can be chosen on public lead forms.
            $table->boolean('is_public')->default(false);
            // Whether public forms expose lead/payment status selectors for this campaign.
            $table->boolean('public_show_statuses')->default(false);
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

        Schema::create('campaign_user', function (Blueprint $table) {
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['campaign_id', 'user_id']);
            $table->index('user_id');
        });

        foreach (['campaign_lead_statuses', 'campaign_payment_statuses'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
                $table->string('name', 80);
                $table->string('color', 20)->default('gray');
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['campaign_id', 'name']);
                // Target of the composite FK on leads that guarantees status ↔ campaign consistency.
                $table->unique(['campaign_id', 'id']);
                $table->index(['campaign_id', 'sort_order']);
            });
        }

        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('permission', 50);
            $table->timestamps();
            $table->unique(['user_id', 'permission']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('campaign_payment_statuses');
        Schema::dropIfExists('campaign_lead_statuses');
        Schema::dropIfExists('campaign_user');
        Schema::dropIfExists('branch_campaign');
        Schema::dropIfExists('campaigns');
        Schema::table('users', fn (Blueprint $table) => $table->dropForeign(['branch_id']));
        Schema::dropIfExists('branches');
    }
};
