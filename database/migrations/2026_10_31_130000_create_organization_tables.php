<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('manager_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('name', 120);
            $table->string('code', 32);
            $table->string('cost_center', 32)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'branch_id', 'status']);
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('reports_to_position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->string('title', 150);
            $table->string('code', 32);
            $table->string('grade', 40)->nullable();
            $table->string('employment_type', 32)->default('Full-time');
            $table->unsignedSmallInteger('headcount')->default(1);
            $table->string('status', 20)->default('open');
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'branch_id', 'status']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            $table->foreignId('position_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
        });

        $now = now();
        $departments = DB::table('employees')
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->select('company_id', 'department')
            ->distinct()
            ->get();

        foreach ($departments as $department) {
            $name = trim($department->department);
            $code = 'LEGACY-'.strtoupper(substr(sha1($department->company_id.'|'.mb_strtolower($name)), 0, 8));

            DB::table('departments')->insertOrIgnore([
                'company_id' => $department->company_id,
                'name' => $name,
                'code' => $code,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $departmentId = DB::table('departments')
                ->where('company_id', $department->company_id)
                ->where('code', $code)
                ->value('id');

            DB::table('employees')
                ->where('company_id', $department->company_id)
                ->where('department', $name)
                ->update(['department_id' => $departmentId]);
        }
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('position_id');
            $table->dropConstrainedForeignId('department_id');
        });

        Schema::dropIfExists('positions');
        Schema::dropIfExists('departments');
    }
};
