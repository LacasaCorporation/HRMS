<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('benefit_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 140);
            $table->string('category', 32);
            $table->string('currency', 3);
            $table->string('provider', 180)->nullable();
            $table->text('description')->nullable();
            $table->text('eligibility_notes')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->index(['company_id', 'branch_id', 'status', 'category']);
        });

        Schema::create('benefit_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('benefit_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_code', 32);
            $table->string('employee_name', 180);
            $table->string('department')->nullable();
            $table->date('effective_start');
            $table->date('effective_end')->nullable();
            $table->decimal('employee_contribution', 12, 2)->nullable();
            $table->decimal('employer_contribution', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 24)->default('draft');
            $table->text('review_note')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['benefit_plan_id', 'employee_code', 'effective_start'], 'benefit_enrollment_start_unique');
            $table->index(['company_id', 'branch_id', 'status', 'effective_start'], 'benefit_enrollments_scope_status_start_idx');
        });

        Schema::create('separation_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lifecycle_case_id')->unique()->constrained('lifecycle_cases')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_code', 32);
            $table->string('employee_name', 180);
            $table->string('department')->nullable();
            $table->string('currency', 3);
            $table->string('status', 24)->default('draft');
            $table->text('notes')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'branch_id', 'status']);
        });

        Schema::create('separation_settlement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('separation_settlement_id')->constrained()->cascadeOnDelete();
            $table->string('category', 24);
            $table->string('description', 180);
            $table->decimal('amount', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['separation_settlement_id', 'category'], 'separation_items_settlement_category_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('separation_settlement_items');
        Schema::dropIfExists('separation_settlements');
        Schema::dropIfExists('benefit_enrollments');
        Schema::dropIfExists('benefit_plans');
    }
};
