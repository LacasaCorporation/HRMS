# Integration hand-off — Northstar People

## Runtime checklist
- Backend project: workspace root (`.`), Laravel 12 / PHP 8.2+.
- Backend run: `php artisan serve --host=127.0.0.1 --port=8000`; backend build/check: `composer install` and `php artisan test`; health: `GET /up`.
- Frontend project: Laravel Vite frontend in workspace root (`resources/js`, `resources/css`); dev: `npm run dev`; build: `npm run build`.
- Frontend API seam: `resources/js/api/index.js` (session-authenticated `fetch`; `setCsrfToken()` handles logout token rotation). No mock client, mock dataset, duplicate shared types, or mock-state switcher exists.
- API routes: `GET /api/csrf-token`; `POST /api/login`; `POST /api/logout`; `GET /api/me`; `GET /api/modules`; `GET /api/dashboard`; `GET /api/employees`; `POST /api/employees`; `GET /api/organization`; `POST /api/organization/departments`; `PATCH /api/organization/departments/{department}`; `POST /api/organization/positions`; `POST /api/organization/positions/{position}/assign`; `PATCH /api/organization/employees/{employee}/reporting-line`; `GET /api/recruitment` (search, stage, requisition, and page filters); `POST /api/recruitment/requisitions`; `PATCH /api/recruitment/requisitions/{requisition}/status`; `POST /api/recruitment/requisitions/{requisition}/candidates`; `PATCH /api/recruitment/candidates/{candidate}`; `POST /api/recruitment/candidates/{candidate}/hire`; `GET /api/lifecycle`; `POST /api/lifecycle/cases`; `PATCH /api/lifecycle/tasks/{task}`; `POST /api/lifecycle/cases/{case}/complete`; `GET /api/attendance`; `GET /api/attendance/report` (date range, employee search, shift status, pagination); `POST /api/attendance/check-in`; `POST /api/attendance/{entry}/check-out`; `GET /api/shifts-rosters` (31-day maximum); `POST /api/shifts-rosters/patterns`; `PATCH /api/shifts-rosters/patterns/{pattern}`; `POST /api/shifts-rosters/assignments`; `DELETE /api/shifts-rosters/assignments/{assignment}`; `GET /api/leave`; `POST /api/leave`; `POST /api/leave/{leaveRequest}/review`.
- The frontend retries a CSRF-rejected unsafe request once after refreshing the session-bound token.
- Database: MySQL; schema migrations: `database/migrations`; connection: `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` in `.env`.
- Migration requirement: migrations must not add demo fixtures. The organization migration normalizes existing employee department values. `DatabaseSeeder` contains synthetic local demo fixtures (including organization links) and must only be run explicitly for local development, never as part of production schema migration.
- Shared types: none (plain JavaScript); there is no shared typed-client package or alias.
- Private file store: Laravel private local disk (`storage/app/private`); no upload endpoint is exposed yet.

## Service classification
- Essential: Laravel web/API runtime, MySQL, Vue/Vite frontend.
- Enhancement: private local file storage (foundation only; upload workflow staged), queue/cache tables (Laravel defaults; no HR integrations configured).
- External ERP, bank/WPS transmission, biometrics/GPS, and document-management systems: inactive contract boundaries only; no live service connection.

## Workflow status
- Foundation slices: dashboard, employee directory/create, Organization Management (departments, position planning/assignment, reporting lines), Recruitment & Hiring (requisitions, paginated candidate pipeline, notes/ratings, and hire conversion), onboarding/offboarding checklists with controlled exit closure, attendance check-in/out and scoped date/search/status reporting with export, reusable shift patterns and scoped weekly roster assignment/export, leave request/review/balance, and an HR/admin-only manual payroll workpaper workflow with review/approval and CSV export.
- Shift patterns and roster assignments persist in MySQL. HR/admin can manage patterns and assignments; managers and employees receive data limited to PeopleScope. Duplicate employee/day assignments and scheduling during approved leave are rejected. Calendar rules, automatic rotations, roster swaps, and attendance enforcement are not connected.
- Recruitment records are restricted to HR/admin and company/branch scoped. Job-board publishing, email sequences, interview scheduling, background checks, resume storage, and candidate-to-login provisioning are not connected.
- Lifecycle records are restricted to HR/admin and company/branch scoped. Team labels do not provision task assignees; checklist completion does not upload documents, revoke accounts, or execute payroll settlement. Offboarding sets the employee status inactive only after all checklist items are complete and HR confirms closure.
- Payroll cycles and employee inputs are HR/admin-only and company/branch scoped. Pay amounts are entered manually and totals are arithmetic only; statutory calculations, payslips, WPS/SIF, payment processing, and financial integrations are not implemented.
- All remaining core and advanced modules are explicitly staged in `docs/DELIVERY-STATUS.md` and the in-app module catalog; do not claim completion.
- No Azure resource or infrastructure is required.
