<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_code', 32);
            $table->string('employee_name', 180);
            $table->string('department')->nullable();
            $table->string('category', 32);
            $table->string('merchant', 140);
            $table->date('expense_date');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->text('description');
            $table->string('receipt_path')->nullable();
            $table->string('receipt_original_name')->nullable();
            $table->string('receipt_mime', 100)->nullable();
            $table->unsignedInteger('receipt_size')->nullable();
            $table->string('status', 24)->default('pending');
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'branch_id', 'status', 'expense_date'], 'expense_claim_scope_status_date_idx');
            $table->index(['employee_id', 'status', 'expense_date'], 'expense_claim_employee_status_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_claims');
    }
};
