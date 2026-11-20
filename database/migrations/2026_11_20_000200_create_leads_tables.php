<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_lead_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->unique()->constrained()->cascadeOnDelete();
            // SHA-256 of the token, used for lookup. The token itself is only kept encrypted (APP_KEY)
            // so Super Admins can copy the URL again; it is never stored in plain text.
            $table->char('token_hash', 64)->unique();
            $table->text('token_encrypted');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('submissions_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_reset_at')->nullable();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('lead_number', 30)->nullable()->unique();
            $table->date('lead_date')->index();
            $table->string('title', 10)->nullable();
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('mobile', 30)->index();
            $table->string('email')->nullable()->index();
            $table->string('house_number', 50)->nullable();
            $table->string('post_code', 20)->nullable();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('campaign_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('lead_status_id');
            $table->unsignedBigInteger('payment_status_id')->nullable();
            $table->date('follow_up_date')->nullable()->index();
            $table->text('notes')->nullable();
            $table->string('source', 20)->default('manual')->index();
            $table->foreignId('public_lead_form_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'campaign_id']);
            $table->index(['campaign_id', 'assigned_to']);
            $table->index('created_at');

            // A lead's statuses must belong to the lead's own campaign — enforced by the database.
            $table->foreign(['campaign_id', 'lead_status_id'])
                ->references(['campaign_id', 'id'])->on('campaign_lead_statuses')->restrictOnDelete();
            $table->foreign(['campaign_id', 'payment_status_id'])
                ->references(['campaign_id', 'id'])->on('campaign_payment_statuses')->restrictOnDelete();
        });

        Schema::create('lead_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('lead_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40)->index();
            $table->string('description');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60)->index();
            $table->string('module', 40)->index();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['module', 'subject_id']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('lead_activities');
        Schema::dropIfExists('lead_notes');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('public_lead_forms');
    }
};
