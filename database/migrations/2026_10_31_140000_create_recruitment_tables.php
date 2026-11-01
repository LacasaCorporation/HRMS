<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_requisitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 40);
            $table->string('title', 150);
            $table->string('employment_type', 32);
            $table->string('work_mode', 24)->default('On-site');
            $table->string('location', 120)->nullable();
            $table->unsignedSmallInteger('openings')->default(1);
            $table->string('priority', 16)->default('normal');
            $table->string('status', 20)->default('open');
            $table->date('target_date')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'branch_id', 'status']);
        });

        Schema::create('recruitment_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requisition_id')->constrained('recruitment_requisitions')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('hired_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 190);
            $table->string('phone', 40)->nullable();
            $table->string('source', 40)->default('Direct');
            $table->string('stage', 24)->default('new');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('applied_at')->useCurrent();
            $table->timestamp('hired_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'requisition_id', 'email']);
            $table->index(['company_id', 'stage', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_candidates');
        Schema::dropIfExists('recruitment_requisitions');
    }
};
