# Delivery status and remaining scope

## Runnable foundation delivered

- Laravel 12, PHP 8.2+, MySQL runtime configuration, Vue 3 + JavaScript + Vite.
- Session login/logout with CSRF protection, role-aware navigation, seeded company/branch context, and company/branch-filtered server-side access.
- Persisted people directory and limited employee creation for HR/admin.
- Persisted attendance check-in/out for linked employees.
- Persisted leave request, scoped approval/rejection, and balance decrement on approval.
- Employee self-service foundation: personal profile, current-year leave balances and request history, own 30-day attendance history, check-in/out, and contact-detail updates. The API scopes records to the signed-in employee; private documents, letters, and payslips are not exposed.
- Manager self-service foundation: direct-report directory, team leave queue with confirmed approve/decline decisions, and a seven-day attendance register. Existing `PeopleScope` rules limit team records and review actions to direct reports; missing punches are not interpreted as absences.
- Persisted Organization Management foundation: company/branch-scoped departments, parent hierarchy, department leads and cost centers; position planning with headcount and employee assignment; and reporting-line updates with cycle protection.
- Persisted Recruitment & Hiring foundation: company/branch-scoped requisitions, paginated candidate pipeline with search/stage/role filters, recruiter notes and ratings, pause/close/reopen controls, and offer-to-employee conversion with requisition and position headcount enforcement.
- Persisted Onboarding & Offboarding foundation: HR/admin-only employee journeys with generated, dated task checklists, notes, progress, overdue tracking, CSV export, and controlled completion. Offboarding deactivates the employee only after every task is complete and HR explicitly confirms.
- Persisted Attendance workspace: date-range, employee-search, and shift-status reporting; daily activity trend and worked-hour metrics; pagination and filtered CSV export; and personal check-in/out status. The report endpoint preserves the legacy attendance list response.
- Persisted Shift & Roster foundation: reusable branch-aware shift patterns (including overnight hours), scoped weekly roster assignments, duplicate employee/day protection, approved-leave conflict checks, upcoming assignment removal, pattern archive guards, and CSV export.
- Persisted Payroll workflow foundation: HR/admin-only company/branch-scoped cycles, explicit employee inclusion, manual currency-specific inputs, arithmetic totals, review/return/approval history, locked submitted inputs, and CSV workpaper export.
- Persisted Benefits foundation: HR/admin-only plan settings, branch/company scope, employee enrollment records, manually entered currency-matched contribution amounts, review/return/decline/approval/end decisions, and filtered CSV workpapers. No eligibility or country-specific contribution rules are inferred.
- Persisted Separation foundation: HR/admin-only workpapers linked to offboarding cases, manual earning/recovery lines, arithmetic-only totals, clearance-gated submission, review/return/approval decisions, and CSV export. Approval does not initiate settlement or payment.
- Persisted Performance foundation: HR/admin-only review cycles with explicit employee scope, manually entered ratings and narratives, return/resubmit and approval decisions, cycle closure gates, branch scoping, and CSV export. Ratings do not produce employment recommendations.
- Persisted Training foundation: HR/admin-only program catalog, capacity and duplicate-enrollment checks, branch-scoped assignment management, constrained enrollment transitions, manually recorded completion, and CSV export. Provider integrations and course delivery are not implemented.
- Company-scoped dashboard aggregates and append-only-by-application audit event recording.
- Synthetic data only. Demo identities are documented in the root README.

## Staged — not complete and not simulated

The module catalog exposes all 24 approved core modules and 13 advanced modules. Their dedicated pages communicate delivery status and remaining scope; they do not claim that a navigation entry constitutes an implemented workflow.

1. **People and organization:** full employee profile and history, private documents and expiry, position lifecycle beyond planning/assignment, and dashboard drill-downs. Recruitment covers internal requisitions and candidate stages only; job-board publishing, email/calendar, background checks, resume storage, and employee account provisioning remain staged.
2. **Time and absence:** holiday calendars, automated rotations, weekly-off rules, swap approvals, attendance schedule enforcement, correction/missing-punch approvals, overtime/timesheets, and accrual/carry-forward. Employee self-service covers personal profile contacts, leave requests/balances, and attendance punches/history. Manager self-service covers direct-report attendance and leave decisions; manager scheduling and additional team approvals remain staged. Reusable shift patterns and manual weekly roster assignment are implemented.
3. **Compensation and finance:** approved salary structures and statutory payroll calculations, payroll processing, payslips, loans, expenses, travel, WPS/SIF generation, and accounting/bank-file contracts. Payroll and Benefits currently store manual workpapers and review decisions only; Benefits does not infer eligibility or contribution rates.
4. **Growth and employee relations:** helpdesk, engagement, discipline, health/safety, assets/uniforms, and secure document workflows. Performance reviews and training assignment tracking are implemented as manual HR workspaces only.
5. **Separation, advanced planning, reporting, and integration contracts:** legal settlement/EOSB and statutory calculations, workforce planning/productivity/succession/career, labor-cost allocation, compliance/analytics, and ERP adapters. The current Separation slice is a clearance-linked manual workpaper, not a legal calculator or payment workflow.

All legal calculations (including UAE leave, payroll, gratuity/EOSB, and WPS formats) require customer/legal validation before production. External integrations remain inactive pending authorized credentials, privacy review, and a test environment.

## Known gaps in this increment

- No full permission matrix/policy editor, user provisioning, password reset UI, or MFA.
- Recruitment data is restricted to HR/admin; candidate consent, retention/erasure workflows, resume storage, interview scheduling, and external sourcing/communications are not implemented. Review applicable privacy requirements before production use.
- Lifecycle workflows are HR/admin-only and company/branch scoped. Checklist ownership is a team label rather than a user assignment; document upload, account provisioning/revocation, final payroll settlement, and external clearance integrations are not implemented. Closing an offboarding case marks the employee inactive only after all steps are complete and an explicit confirmation is submitted.
- No complete branch manager hierarchy management; manager data access is restricted to their current employee record and direct reports. Organization writes are limited to HR/admin.
- Leave weekday counting excludes holidays; requests spanning calendar years are rejected rather than split across annual balances.
- Attendance has no correction approval, schedule validation, late/early policy, or overtime computation.
- Shift & Roster is a manual planning workspace. It does not enforce attendance against schedules and has no holiday calendars, weekly-off rules, automated rotations, swap approvals, or overtime calculations.
- Payroll stores manually entered base pay, other earnings, and manual deductions for each selected employee. Gross/net are arithmetic only; no statutory tax, pension, insurance, overtime, EOSB, payslip, WPS/SIF, payment, or bank/accounting export is implemented. Approval records a review decision and does not mark the cycle as paid. Country rules require customer/legal validation before production use.
- Benefits stores configurable plan metadata and manually supplied contributions. It does not determine employee eligibility, legal contribution rates, tax treatment, deductions, provider coverage, or payment status. Plan currency cannot change once enrollments exist; the selected employee currency must match the plan.
- Separation stores manually entered earning/recovery lines and their arithmetic total, linked to a completed offboarding checklist. It does not calculate EOSB/gratuity, leave encashment, notice, tax, loans, statutory deductions, or final pay; approval is not a payment instruction.
- Performance records are confidential manual review workpapers. No employee ranking or pay, promotion, disciplinary, or termination recommendation is generated.
- Training tracks programs, assignments, and HR-entered completion only; it does not register learners with providers, deliver courses, infer attendance, issue certificates, or verify credentials.
- Attendance reports allow date windows of up to 93 days and paginate up to 500 rows per page; worked hours are calculated only from recorded punch times.
- Audit events are insert-only through the application service; database-level immutability and audit export controls are not yet configured.
- The synthetic demo seeder is idempotent and intended for local development only.

## Integration verification

- Organization, recruitment, and lifecycle schema migrations create workflow tables without inserting demo fixtures. The explicitly invoked local demo seeder supplies synthetic recruiting examples.
- `php artisan test`: **34 passed, 493 assertions**. Coverage includes growth review creation, return/resubmit/approval/closure, branch isolation, training capacity, duplicate enrollment, status transitions, and role access alongside existing workflow tests.
- `npm run build`: **passed** with Vite 7.3.6, including the responsive Performance and Training workspaces and final-status confirmation dialog.
- The organization, lifecycle, shift/roster, payroll, benefits, separation, and growth migrations are applied to the configured local MySQL database. The legacy department text was normalized; all 6 existing employees are linked to a department, 4 department records exist, and positions are ready to be planned. `php artisan migrate:status` reports every migration as `Ran`.
- Local browser verification on `127.0.0.1:8000`: signed in with the synthetic HR account and verified the Performance cycle form and employee picker, plus the Training overview, at desktop and mobile viewport sizes. No growth records were created in the browser; workflow writes and review paths are covered by feature tests.
- This was local-only verification; no Azure resources or external integrations were created or used.
