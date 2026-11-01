<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('branch_scope_key')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('pay_date')->nullable();
            $table->string('currency', 3);
            $table->string('status', 24)->default('draft');
            $table->text('review_note')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'branch_scope_key', 'period_start', 'period_end', 'currency'], 'payroll_cycle_period_unique');
            $table->index(['company_id', 'branch_id', 'status', 'period_start']);
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_code', 32);
            $table->string('employee_name', 180);
            $table->string('department')->nullable();
            $table->decimal('base_pay', 12, 2)->nullable();
            $table->decimal('other_earnings', 12, 2)->nullable();
            $table->decimal('manual_deductions', 12, 2)->nullable();
            $table->decimal('gross_pay', 12, 2)->nullable();
            $table->decimal('net_pay', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['payroll_cycle_id', 'employee_code']);
            $table->index(['payroll_cycle_id', 'employee_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_cycles');
    }
};
