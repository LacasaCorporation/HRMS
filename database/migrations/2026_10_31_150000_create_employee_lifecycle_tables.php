<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lifecycle_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 24);
            $table->string('status', 24)->default('in_progress');
            $table->date('effective_date');
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'type']);
            $table->index(['company_id', 'branch_id', 'type', 'status']);
        });

        Schema::create('lifecycle_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lifecycle_case_id')->constrained('lifecycle_cases')->cascadeOnDelete();
            $table->string('title', 180);
            $table->string('owner_team', 80);
            $table->date('due_date')->nullable();
            $table->string('status', 24)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['lifecycle_case_id', 'status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lifecycle_tasks');
        Schema::dropIfExists('lifecycle_cases');
    }
};
