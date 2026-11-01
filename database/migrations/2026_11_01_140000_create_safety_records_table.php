<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('safety_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('record_type', 24);
            $table->date('occurred_on');
            $table->string('location', 180)->nullable();
            $table->string('priority', 16)->default('normal');
            $table->text('summary');
            $table->text('action_plan')->nullable();
            $table->string('status', 24)->default('open');
            $table->text('resolution_summary')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'branch_id', 'status', 'occurred_on'], 'safety_records_scope_status_date_idx');
            $table->index(['employee_id', 'status', 'occurred_on'], 'safety_records_employee_status_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('safety_records');
    }
};
