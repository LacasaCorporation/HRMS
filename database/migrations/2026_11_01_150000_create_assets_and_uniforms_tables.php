<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('asset_tag', 80);
            $table->string('name', 180);
            $table->string('category', 24);
            $table->string('serial_number', 120)->nullable();
            $table->string('condition', 16)->default('new');
            $table->string('status', 32)->default('available');
            $table->text('notes')->nullable();
            $table->text('maintenance_note')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'asset_tag'], 'company_assets_company_tag_unique');
            $table->index(['company_id', 'branch_id', 'status'], 'company_assets_scope_status_idx');
        });

        Schema::create('asset_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('asset_id')->constrained('company_assets')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('declined_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('pending_acknowledgement');
            $table->string('condition_at_issue', 16);
            $table->text('acknowledgement_note')->nullable();
            $table->string('return_condition', 16)->nullable();
            $table->text('return_notes')->nullable();
            $table->timestamp('assigned_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'branch_id', 'status', 'assigned_at'], 'asset_assignments_scope_status_idx');
            $table->index(['employee_id', 'status', 'assigned_at'], 'asset_assignments_employee_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_assignments');
        Schema::dropIfExists('company_assets');
    }
};
