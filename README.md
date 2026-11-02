# Northstar People — local HRM foundation

A Laravel 12 + Vue 3 (plain JavaScript) modular monolith for local development. MySQL stores company-scoped people and workflow data; private files use Laravel's local private disk. No cloud resources or external provider connections are configured.

This delivery is an explicitly partial enterprise foundation, not a completed HR suite. The live slices are an authenticated, role-aware shell and module catalog; company-scoped dashboard and employee directory; Organization Management; Recruitment & Hiring; onboarding/offboarding checklists; attendance reporting and check-in/out; Shift & Roster; leave requests and review; employee self-service for the signed-in employee's own profile contacts, leave, attendance, and private documents; manager self-service for direct-report team data, leave review, and attendance; employee expense claims with private receipt access and scoped review; loan/advance applications with scoped review and manual repayment reconciliation; business travel requests with scoped review, itinerary notes, and post-trip reconciliation; restricted Payroll workpapers; HR/admin-only Benefits, Separation, Performance, Training, confidential Disciplinary case workflows, HR Settings, and private Document Management; Assets & Uniforms inventory with employee handover acknowledgement and return tracking; and role-scoped Reports & Analytics. Reports provide date-filtered workforce, attendance, and leave metrics plus a formula-safe attendance CSV; HR Settings maintain company profile details and leave type metadata. Benefits support configurable plan metadata, manual enrollment contributions, review decisions, and CSV exports. Separation supports offboarding-linked settlement workpapers with manual earning/recovery lines and clearance-gated review. Performance supports review cycles, manually entered narratives and ratings, HR review decisions, and scoped CSV exports. Training supports a program catalog, capacity-checked employee assignments, manually recorded completion, and CSV exports. Disciplinary supports restricted case intake, assigned investigation, manually recorded employee response, and documented resolution. These are reviewable internal records, not automated employment decisions, findings, sanctions, course delivery, legal calculations, or payment instructions. Expense approvals do not initiate reimbursement or payment; loan approval and repayment reconciliation do not issue funds or trigger payroll deductions. Attendance report windows are limited to 93 days, and worked hours use recorded punch times only. Employees can access only their own private documents; payslips and letters remain unavailable. Manager scheduling and other team workflows remain staged. Advanced planning, engagement, support, compliance, and integration modules remain explicitly staged until their dedicated data and controls exist. See [docs/DELIVERY-STATUS.md](docs/DELIVERY-STATUS.md).

Health & Safety adds restricted company/branch-scoped records for incidents, hazards, inspection findings, and PPE concerns, with manual priority, assigned follow-up, and resolution notes. It does not assess risk or regulatory compliance, store medical details, or serve as an emergency service.

Assets & Uniforms provides company/branch-scoped item inventory, HR-managed handovers, employee receipt acknowledgement or decline, return condition and loss notes, and a maintenance-release workflow. It does not manage procurement, valuation, depreciation, payroll chargebacks, fixed-asset accounting, or ERP synchronization.

Document Management stores private employee PDFs and images on the local private disk, with scoped HR/admin uploads, employee-owned downloads, retained document versions, and manually entered expiry dates. Uploads are limited to PDF/JPEG/PNG files up to 10 MB. Expiry labels are reminders only; retention schedules, renewal notifications, legal validity, and regulatory compliance are not automated.

Reports & Analytics provides role-scoped workforce/department summaries, up to 93 days of recorded attendance trends, leave request counts, and a filtered CSV of the latest 500 attendance rows. HR Settings allow HR/admin to update company name/country/currency and create or update leave type metadata; policy edits do not rewrite existing employee balances.

## Requirements

- PHP 8.2+, Composer 2
- PHP ZIP extension or an installed `unzip`/`7z` executable for Composer package downloads
- MySQL 8+ (or a compatible local MySQL server)
- Node.js and npm already installed (the project does not install or modify Node)

On Windows/XAMPP, if Composer reports that the ZIP extension and unzip/7z are unavailable, enable the bundled `php_zip.dll` extension in the active PHP `php.ini` or run Composer with that extension loaded for the command.

## First run

1. Create a local database, for example `hrm_local` with `utf8mb4` and `utf8mb4_unicode_ci`.
2. Copy `.env.example` to `.env`; set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` for your local MySQL.
3. Install and initialize:

   ```powershell
   composer install
   php artisan key:generate
   php artisan migrate
   npm install
   npm run build
   ```

   Schema migrations do not insert demo rows. To populate this private local demo with the synthetic accounts and records listed below, run `php artisan db:seed` explicitly after migration. Do not use the demo seeder in shared or production environments.

4. Start Laravel in one terminal:

   ```powershell
   php artisan serve --host=127.0.0.1 --port=8000
   ```

5. Start Vite in another terminal:

   ```powershell
   npm run dev
   ```

Open `http://127.0.0.1:8000`. Vite is used only for local UI development; the built frontend is in `public/build`.

## Synthetic demo accounts

Seeder-created users share the local-only password `password`:

| Role | Email |
|---|---|
| HR | `maya.hr@example.test` |
| Manager | `omar.manager@example.test` |
| Employee | `lina.employee@example.test` |

These are synthetic local demo identities, not real employee data. Do not reuse the sample password outside a private local environment.

## Checks

```powershell
php artisan test
npm run build
```

Feature tests use an in-memory SQLite database so they do not depend on a running MySQL service. The application runtime and migrations are configured for MySQL.

The API integration test creates its own minimal company, users, and employee records and exercises all API routes, including authentication, validation, authorization, error, and persisted workflow responses. It does not depend on the local demo seeder.

## API and authorization foundation

The browser uses Laravel session authentication and CSRF protection. If a long-lived tab submits an expired token, the API client fetches a fresh session-bound token from `GET /api/csrf-token` and retries the request once. API endpoints are served from `routes/web.php` under `/api`:

- `GET /api/csrf-token`
- `POST /api/login`, `POST /api/logout`, `GET /api/me`
- `GET /api/modules`, `GET /api/dashboard`
- `GET /api/employees?search=…`, `POST /api/employees`
- `GET /api/self-service`, `PATCH /api/self-service/profile` (own employee profile, leave, and attendance only)
- `GET /api/organization`
- `POST /api/organization/departments`, `PATCH /api/organization/departments/{department}`
- `POST /api/organization/positions`, `POST /api/organization/positions/{position}/assign`
- `PATCH /api/organization/employees/{employee}/reporting-line`
- `GET /api/recruitment?search=…&stage=…&requisition_id=…&page=…`
- `POST /api/recruitment/requisitions`, `PATCH /api/recruitment/requisitions/{requisition}/status`
- `POST /api/recruitment/requisitions/{requisition}/candidates`
- `PATCH /api/recruitment/candidates/{candidate}`, `POST /api/recruitment/candidates/{candidate}/hire`
- `GET /api/lifecycle`, `POST /api/lifecycle/cases`
- `PATCH /api/lifecycle/tasks/{task}`, `POST /api/lifecycle/cases/{case}/complete`
- `GET /api/attendance` (recent records), `GET /api/attendance/report?from=…&to=…&search=…&status=…&page=…&per_page=…`
- `GET /api/reports?from=…&to=…` (role-scoped people, attendance, and leave analytics; maximum 93 days)
- `GET /api/settings`, `PATCH /api/settings/company`, `POST /api/settings/leave-types`, `PATCH /api/settings/leave-types/{leaveType}` (HR/admin)
- `POST /api/attendance/check-in`, `POST /api/attendance/{entry}/check-out`
- `GET /api/shifts-rosters?from=…&to=…` (31-day maximum, role/branch-scoped assignments, shift patterns, and employee choices)
- `POST /api/shifts-rosters/patterns`, `PATCH /api/shifts-rosters/patterns/{pattern}`
- `POST /api/shifts-rosters/assignments`, `DELETE /api/shifts-rosters/assignments/{assignment}`
- `GET /api/payroll`, `POST /api/payroll/cycles`
- `PATCH /api/payroll/items/{item}`, `POST /api/payroll/cycles/{cycle}/submit`, `POST /api/payroll/cycles/{cycle}/review`
- `GET /api/benefits`, `POST /api/benefits/plans`, `PATCH /api/benefits/plans/{plan}`
- `POST /api/benefits/enrollments`, `PATCH /api/benefits/enrollments/{enrollment}`
- `POST /api/benefits/enrollments/{enrollment}/submit`, `/api/benefits/enrollments/{enrollment}/review`, `/api/benefits/enrollments/{enrollment}/end`
- `GET /api/separations`, `POST /api/separations/settlements`
- `PUT /api/separations/settlements/{settlement}/items`, `POST /api/separations/settlements/{settlement}/submit`, `/api/separations/settlements/{settlement}/review`
- `GET /api/performance`, `POST /api/performance/cycles`, `PATCH /api/performance/cycles/{cycle}/status`
- `PATCH /api/performance/reviews/{review}`, `POST /api/performance/reviews/{review}/submit`, `/api/performance/reviews/{review}/review`
- `GET /api/training`, `POST /api/training/programs`, `PATCH /api/training/programs/{program}`
- `POST /api/training/enrollments`, `PATCH /api/training/enrollments/{enrollment}`
- `GET /api/leave`, `POST /api/leave`, `POST /api/leave/{leaveRequest}/review`
- `GET /api/loans-advances`, `POST /api/loans-advances`, `POST /api/loans-advances/{loan}/review`, `POST /api/loans-advances/installments/{installment}/repayments`
- `GET /api/disciplinary`, `POST /api/disciplinary/cases`, `POST /api/disciplinary/cases/{case}/start-review`, `POST /api/disciplinary/cases/{case}/employee-response`, and `POST /api/disciplinary/cases/{case}/resolve`
- `GET /api/health-safety`, `POST /api/health-safety/records`, `POST /api/health-safety/records/{record}/assign`, and `POST /api/health-safety/records/{record}/resolve`
- `GET /api/assets-uniforms`, `POST /api/assets-uniforms/assets`, `POST /api/assets-uniforms/assets/{asset}/assign`, `POST /api/assets-uniforms/assets/{asset}/maintenance-complete`, and employee handover acknowledgement / return routes under `/api/assets-uniforms/assignments/{assignment}`
- `GET /api/documents`, `POST /api/documents`, `POST /api/documents/{document}/versions`, and authorized private downloads at `/api/documents/{document}/versions/{version}/download`

Tenant/branch scoping is enforced in `App\Support\PeopleScope` and each domain controller, not only in the Vue navigation. HR/admin can access their company; managers are limited to their own record and direct reports; employees are limited to their own record. Payroll, benefits, separation, performance, training, disciplinary, health and safety, and document-management administration are restricted to HR/admin; employees can access only their own documents. Expense claims can be submitted by active employees; review is limited to direct-report managers and scoped HR/admin, with submitter self-review prevented. Receipts and employee documents stay on private storage and are downloaded through authorized routes. Loan requests are employee-owned; manager and HR/admin review follows people scope, and only scoped HR/admin can reconcile external repayment entries. Legal identity verification, statutory payroll calculations, UAE legal calculations, automated performance decisions, course delivery, risk/compliance assessments, and external integrations are not implemented or represented as active.

## Important limitations

- Leave requests count weekdays only and must stay within one calendar year. Holiday calendars, accrual/carry-forward, policy exceptions, and UAE statutory rules remain to be delivered and validated.
- Attendance is a manual employee check-in/out foundation with scoped reporting and exports. No devices, GPS, biometrics, schedule validation, correction approvals, or automatic overtime calculation are connected.
- Shift & Roster supports reusable working-hour patterns, overnight shifts, weekly assignment views, CSV export, and HR/admin roster management. A person cannot have duplicate assignments for a day or be scheduled on approved leave. Holiday calendars, automatic rotations, shift swaps, schedule enforcement, overtime, and payroll links are not implemented.
- Payroll is an HR/admin-only workflow for creating company/branch-scoped, single-currency cycles, selecting active employees, entering base pay/other earnings/manual deductions, recording review or requested changes, and exporting a CSV workpaper. Gross/net are arithmetic only. No statutory tax, pension, insurance, overtime, EOSB, payslip, WPS/SIF, accounting/bank output, payment processing, or employee self-service is implemented. Validate all inputs with approved local guidance; approval is not payment.
- Benefits are an HR/admin-only workflow for configuring plan metadata and recording enrollment dates, manually entered employee/employer contributions, review decisions, and CSV workpapers. The system does not determine eligibility, rates, tax treatment, insurance coverage, pension/statutory contributions, deductions, provider updates, or payments. Currency must match the employee profile.
- Separation is an HR/admin-only settlement workpaper linked to an offboarding case. Submission requires completed exit clearances and saved manual earning/recovery lines; net is arithmetic only. No EOSB/gratuity, leave encashment, notice, tax, loan, statutory formula, payment instruction, or payroll settlement is calculated or initiated.
- Performance is an HR/admin-only review workflow. Ratings, goals, results, strengths, and development focus are manually entered; returned reviews can be edited and resubmitted, and cycles close only after all reviews are approved. Ratings do not rank employees or recommend pay, promotion, discipline, or termination.
- Training is an HR/admin-only program catalog and assignment tracker. Capacity and duplicate assignments are checked; enrollment transitions are constrained and completion is recorded manually. No provider registration, course delivery, attendance inference, certificate issuance, or credential verification is connected.
- Expenses support submitted claims with optional private PDF/JPG/PNG receipts, scoped manager/HR approval or reasoned rejection, and audited status history. Approval does not arrange reimbursement or payment; there is no payout integration or accounting export.
- Loans and advances support one open request per employee, scoped manager/HR decisions, an approved principal-only equal-installment schedule, and HR/admin reconciliation of repayments recorded outside the app. No interest or statutory terms are calculated, and approval/reconciliation does not disburse funds, collect payments, or deduct payroll.
- Travel requests capture the trip route, purpose, dates, estimate, and itinerary notes; scoped managers/HR approve a budget ceiling or reject with a reason. Travelers submit post-trip actual totals and explanations after return, and scoped HR/admin records reconciliation. No reservations, allowance calculations, visa/insurance services, or reimbursement payments are connected.
- Disciplinary case intake, investigation, employee-response summaries, and resolution records are restricted to HR/admin and company/branch scope. The tracker does not determine findings, recommend sanctions or termination, send employee notices, or provide evidence/document storage; use approved procedures and legal guidance, and define retention and employee-access controls before production use.
- Health & Safety records cover incident, hazard, inspection, and PPE observations with manually selected priority, HR/admin-scoped assignment, and manual closure. This is not an emergency service, medical records system, risk assessment, or compliance certification; keep medical and sensitive personal details out, and use approved procedures and local guidance.
- Assets & Uniforms tracks individually tagged inventory, HR/admin assignment, employee-owned receipt acknowledgement or decline, return condition and loss, and documented maintenance release within company/branch scope. It does not perform procurement, asset valuation/depreciation, payroll deductions, fixed-asset accounting, or ERP updates.
- Document Management gives HR/admin company- or branch-scoped uploads and employees access only to their own documents. Files are stored on Laravel's local private disk and streamed as attachment downloads; new uploads create retained versions with a content hash and upload audit event. Supported file types are PDF, JPEG, and PNG, up to 10 MB. Expiry flags are based on manually supplied dates and do not send alerts or determine legal validity. Configure and test backups, encryption at rest, access logging, retention, and deletion policies before production use.
- Loan schedules use only principal division and are an internal tracker, not a financial agreement or legal eligibility decision. Reconciliation records external repayment entries; it does not collect funds or feed payroll.
- Employee master data is intentionally minimal. Lifecycle checklists and employee deactivation on completed offboarding are persisted, but private document uploads/access, account provisioning/revocation, and external clearance integrations are not connected. Recruitment data is HR/admin-only; job-board publishing, email, calendar scheduling, background checks, and resume attachments are not connected.
- Laravel's local private filesystem stores the supported expense receipt uploads. Other employee document uploads and access controls are not yet implemented.
- The scaffold seeder creates synthetic local demo fixtures. For non-demo environments, review/remove those fixtures and replace sample credentials before use.
