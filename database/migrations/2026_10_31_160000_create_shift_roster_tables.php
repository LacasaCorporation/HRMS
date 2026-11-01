<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_patterns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('code', 32);
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'branch_id', 'status']);
        });

        Schema::create('roster_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_pattern_id')->constrained()->restrictOnDelete();
            $table->date('shift_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['employee_id', 'shift_date']);
            $table->index(['company_id', 'shift_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roster_assignments');
        Schema::dropIfExists('shift_patterns');
    }
};
