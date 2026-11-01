<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_code', 32);
            $table->string('employee_name', 180);
            $table->string('department')->nullable();
            $table->string('currency', 3);
            $table->decimal('requested_amount', 12, 2);
            $table->unsignedSmallInteger('requested_installments');
            $table->text('purpose');
            $table->string('status', 24)->default('pending');
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->unsignedSmallInteger('repayment_installments')->nullable();
            $table->date('first_due_date')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'branch_id', 'status', 'created_at'], 'loan_advance_scope_status_created_idx');
            $table->index(['employee_id', 'status'], 'loan_advance_employee_status_idx');
        });

        Schema::create('loan_advance_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_advance_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->date('due_date');
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->unique(['loan_advance_id', 'sequence']);
            $table->index(['loan_advance_id', 'due_date']);
        });

        Schema::create('loan_advance_repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_advance_installment_id')->constrained('loan_advance_installments')->cascadeOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('recorded_date');
            $table->string('reference', 120)->nullable();
            $table->timestamps();
            $table->index(['loan_advance_installment_id', 'recorded_date'], 'loan_repayment_installment_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_advance_repayments');
        Schema::dropIfExists('loan_advance_installments');
        Schema::dropIfExists('loan_advances');
    }
};
