<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disciplinary_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('investigator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('case_type', 32);
            $table->date('incident_date');
            $table->text('summary');
            $table->string('status', 24)->default('open');
            $table->text('investigation_notes')->nullable();
            $table->text('employee_response')->nullable();
            $table->timestamp('response_recorded_at')->nullable();
            $table->text('resolution_summary')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'branch_id', 'status', 'incident_date'], 'disciplinary_cases_scope_status_date_idx');
            $table->index(['employee_id', 'status', 'incident_date'], 'disciplinary_cases_employee_status_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disciplinary_cases');
    }
};
