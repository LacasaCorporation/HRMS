<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 140);
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_date')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 24)->default('open');
            $table->timestamps();
            $table->index(['company_id', 'branch_id', 'status'], 'performance_cycles_scope_status_idx');
        });

        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('performance_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_code', 32);
            $table->string('employee_name', 180);
            $table->string('department')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('goals')->nullable();
            $table->text('results')->nullable();
            $table->text('strengths')->nullable();
            $table->text('development_focus')->nullable();
            $table->string('status', 24)->default('draft');
            $table->text('review_note')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['performance_cycle_id', 'employee_code'], 'performance_review_cycle_employee_unique');
            $table->index(['company_id', 'branch_id', 'status'], 'performance_reviews_scope_status_idx');
        });

        Schema::create('training_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 160);
            $table->string('category', 32);
            $table->string('provider', 180)->nullable();
            $table->string('delivery_mode', 32);
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->index(['company_id', 'branch_id', 'status'], 'training_programs_scope_status_idx');
        });

        Schema::create('training_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('training_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_code', 32);
            $table->string('employee_name', 180);
            $table->string('department')->nullable();
            $table->string('status', 24)->default('assigned');
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['training_program_id', 'employee_code'], 'training_enrollment_program_employee_unique');
            $table->index(['company_id', 'branch_id', 'status'], 'training_enrollments_scope_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_enrollments');
        Schema::dropIfExists('training_programs');
        Schema::dropIfExists('performance_reviews');
        Schema::dropIfExists('performance_cycles');
    }
};
