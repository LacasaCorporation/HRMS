<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentRequisition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $company = Company::firstOrCreate(
            ['code' => 'GULF-TRD'],
            ['name' => 'Gulf Trading & Manufacturing', 'country' => 'AE', 'currency' => 'AED'],
        );
        $branch = Branch::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'DXB-HQ'],
            ['name' => 'Dubai Head Office', 'city' => 'Dubai'],
        );

        $users = collect([
            ['name' => 'Maya Haddad', 'email' => 'maya.hr@example.test', 'role' => 'hr', 'code' => 'GTM-0001', 'title' => 'People Operations Lead'],
            ['name' => 'Omar Nasser', 'email' => 'omar.manager@example.test', 'role' => 'manager', 'code' => 'GTM-0002', 'title' => 'Showroom Manager'],
            ['name' => 'Lina Faris', 'email' => 'lina.employee@example.test', 'role' => 'employee', 'code' => 'GTM-0003', 'title' => 'People Coordinator'],
            ['name' => 'Rashid Karim', 'email' => 'rashid.employee@example.test', 'role' => 'employee', 'code' => 'GTM-0004', 'title' => 'Production Supervisor'],
            ['name' => 'Noor Saleh', 'email' => 'noor.employee@example.test', 'role' => 'employee', 'code' => 'GTM-0005', 'title' => 'Procurement Specialist'],
        ]);

        foreach ($users as $index => $record) {
            $user = User::updateOrCreate(
                ['email' => $record['email']],
                [
                    'name' => $record['name'],
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'role' => $record['role'],
                    'password' => Hash::make('password'),
                ],
            );

            $departmentName = $index === 3 ? 'Manufacturing' : ($index === 1 ? 'Retail Operations' : 'People & Culture');
            $employee = Employee::updateOrCreate(
                ['employee_code' => $record['code'], 'company_id' => $company->id],
                [
                    'user_id' => $user->id,
                    'branch_id' => $branch->id,
                    'first_name' => explode(' ', $record['name'])[0],
                    'last_name' => explode(' ', $record['name'], 2)[1] ?? '',
                    'work_email' => $record['email'],
                    'job_title' => $record['title'],
                    'department' => $departmentName,
                    'employment_type' => 'Full-time',
                    'status' => 'active',
                    'join_date' => now()->subMonths(6 + $index)->toDateString(),
                    'currency' => 'AED',
                    'monthly_salary' => 6500 + ($index * 1250),
                    'manager_id' => $index > 1 ? Employee::where('employee_code', 'GTM-0002')->value('id') : null,
                ],
            );

            $departmentCode = match ($departmentName) {
                'Manufacturing' => 'MFG',
                'Retail Operations' => 'RETAIL',
                default => 'PEOPLE',
            };
            $department = Department::query()
                ->where('company_id', $company->id)
                ->where('name', $departmentName)
                ->first();

            if (! $department) {
                $department = Department::create([
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'name' => $departmentName,
                    'code' => $departmentCode,
                    'manager_employee_id' => $employee->id,
                    'status' => 'active',
                ]);
            } elseif (! $department->manager_employee_id) {
                $department->update([
                    'branch_id' => $branch->id,
                    'manager_employee_id' => $employee->id,
                ]);
            }

            $employee->update(['department_id' => $department->id]);

            foreach ([['Annual leave', 24], ['Sick leave', 10]] as [$typeName, $days]) {
                $type = LeaveType::firstOrCreate(
                    ['company_id' => $company->id, 'name' => $typeName],
                    ['annual_entitlement' => $days, 'is_paid' => true],
                );
                LeaveBalance::firstOrCreate(
                    ['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => now()->year],
                    ['entitled_days' => $days, 'used_days' => 0],
                );
            }
        }

        $this->seedRecruitmentDemo($company, $branch);
    }

    private function seedRecruitmentDemo(Company $company, Branch $branch): void
    {
        $roles = [
            [
                'code' => 'REC-MFG-2026',
                'title' => 'Production planner',
                'department' => 'Manufacturing',
                'employment_type' => 'Full-time',
                'work_mode' => 'On-site',
                'location' => 'Dubai',
                'openings' => 2,
                'priority' => 'high',
                'target_date' => now()->addWeeks(6)->toDateString(),
                'description' => 'Coordinate production plans, material availability, and delivery commitments across the manufacturing team.',
                'candidates' => [
                    ['Salma', 'Nasser', 'salma.nasser@example.test', 'Referral', 'interview', 5, 5, 'Strong planning background; interview panel feedback is positive.'],
                    ['Yusuf', 'Haddad', 'yusuf.haddad@example.test', 'LinkedIn', 'screening', 3, 3, 'Review ERP and capacity-planning experience.'],
                    ['Mariam', 'Khalil', 'mariam.khalil@example.test', 'Agency', 'new', null, 1, null],
                    ['Tariq', 'Saeed', 'tariq.saeed@example.test', 'Direct', 'offer', 5, 10, 'Offer details shared; awaiting final confirmation.'],
                ],
            ],
            [
                'code' => 'REC-PEO-2026',
                'title' => 'People operations partner',
                'department' => 'People & Culture',
                'employment_type' => 'Full-time',
                'work_mode' => 'Hybrid',
                'location' => 'Dubai',
                'openings' => 1,
                'priority' => 'normal',
                'target_date' => now()->addWeeks(8)->toDateString(),
                'description' => 'Support employee lifecycle operations, manager enablement, and a consistent people experience.',
                'candidates' => [
                    ['Nadia', 'Omar', 'nadia.omar@example.test', 'Referral', 'interview', 4, 4, 'Experienced HR generalist; explore employee relations depth.'],
                    ['Kareem', 'Farouk', 'kareem.farouk@example.test', 'Job board', 'screening', 4, 2, 'Good systems experience; validate stakeholder communication.'],
                    ['Hala', 'Younis', 'hala.younis@example.test', 'Direct', 'new', null, 1, null],
                ],
            ],
            [
                'code' => 'REC-RTL-2026',
                'title' => 'Retail team lead',
                'department' => 'Retail Operations',
                'employment_type' => 'Full-time',
                'work_mode' => 'On-site',
                'location' => 'Dubai',
                'openings' => 1,
                'priority' => 'normal',
                'target_date' => now()->addWeeks(10)->toDateString(),
                'description' => 'Lead daily showroom operations and coach a customer-focused retail team.',
                'candidates' => [
                    ['Omar', 'Khalid', 'omar.khalid@example.test', 'LinkedIn', 'offer', 4, 7, 'Final discussion complete; candidate reviewing the offer.'],
                    ['Leila', 'Rashid', 'leila.rashid@example.test', 'Referral', 'new', null, 2, null],
                ],
            ],
        ];

        foreach ($roles as $role) {
            $department = \App\Models\Department::query()
                ->where('company_id', $company->id)
                ->where('name', $role['department'])
                ->first();

            if (! $department) {
                continue;
            }

            $requisition = RecruitmentRequisition::updateOrCreate(
                ['company_id' => $company->id, 'code' => $role['code']],
                [
                    'branch_id' => $branch->id,
                    'department_id' => $department->id,
                    'title' => $role['title'],
                    'employment_type' => $role['employment_type'],
                    'work_mode' => $role['work_mode'],
                    'location' => $role['location'],
                    'openings' => $role['openings'],
                    'priority' => $role['priority'],
                    'status' => 'open',
                    'target_date' => $role['target_date'],
                    'description' => $role['description'],
                ],
            );

            foreach ($role['candidates'] as [$firstName, $lastName, $email, $source, $stage, $rating, $daysAgo, $notes]) {
                RecruitmentCandidate::updateOrCreate(
                    ['company_id' => $company->id, 'requisition_id' => $requisition->id, 'email' => $email],
                    [
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'source' => $source,
                        'stage' => $stage,
                        'rating' => $rating,
                        'notes' => $notes,
                        'applied_at' => now()->subDays($daysAgo),
                    ],
                );
            }
        }
    }
}
