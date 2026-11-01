<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_code', 32);
            $table->string('employee_name', 180);
            $table->string('department')->nullable();
            $table->string('origin', 140);
            $table->string('destination', 140);
            $table->text('purpose');
            $table->date('departure_date');
            $table->date('return_date');
            $table->string('currency', 3);
            $table->decimal('estimated_budget', 12, 2);
            $table->decimal('approved_budget', 12, 2)->nullable();
            $table->text('itinerary_notes')->nullable();
            $table->string('status', 24)->default('pending');
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->decimal('actual_total', 12, 2)->nullable();
            $table->text('post_trip_note')->nullable();
            $table->text('reconciliation_note')->nullable();
            $table->timestamp('report_submitted_at')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'branch_id', 'status', 'departure_date'], 'travel_request_scope_status_date_idx');
            $table->index(['employee_id', 'status', 'departure_date'], 'travel_request_employee_status_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_requests');
    }
};
