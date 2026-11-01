# Enterprise HRM for Trading and Manufacturing

**Status:** Approved by user — 2026-10-31  
**Workspace:** `C:\CODES\TODO\HRM`  
**Reference:** [Frappe HR](https://frappe.io/hr)  
**Requirements source:** User-provided HRM module specification (24 core modules, advanced modules, UAE needs, and ERP integrations)

## Product goal

Build a polished, responsive, multi-company HRM application for a trading company, furniture manufacturer, showrooms, and branches. The product covers the employee lifecycle and provides role-appropriate HR, manager, payroll/finance, and employee self-service experiences.

The user requests the full enterprise scope. Implementation will be delivered in coherent increments, with each module's completion made explicit; a navigation item or screen alone does not count as a working module.

## Confirmed decisions and assumptions

- **Backend:** PHP and Laravel, implemented as a modular monolith.
- **Frontend:** Vue 3 with JavaScript and Vite.
- **Data:** MySQL for relational data. Employee documents and uploads use Laravel's private local filesystem during local development.
- **Initial runtime:** Local development. Hosting, cloud provider, and production topology have not been selected; no Azure resources or deployment are in scope.
- **Authentication:** Required, with role-based access and company/branch data scoping.
- **Reference use:** Use the public Frappe HR product page as high-level visual inspiration. Do not copy its brand assets or claim behavior from an inaccessible/private product instance.
- **UAE-specific workflows:** Include configurable records, rules, and exports for WPS/SIF, visa and identity documents, Emiratisation reporting, and gratuity/EOSB. Do not present calculations or exports as legally certified; rules require customer/legal verification before production.
- **External integrations:** Build explicit integration boundaries for accounting, cost centers, manufacturing/work orders, projects, inventory/assets, banking/WPS, document management, and approvals. Do not claim a live third-party connection without credentials, a contract, and an integration test environment.
- **Biometrics, face recognition, GPS/geofencing, banking transmission, and other device/provider integrations:** Model configuration and adapter boundaries; leave external execution disabled until an authorized provider, privacy/legal review, and test environment are supplied.
- **Demo data:** Use synthetic records only. Do not import real employee data or credentials.

## Application architecture

- One Laravel application owns domain rules, REST API, authentication, authorization, file access, and scheduled/background work.
- Vue 3 is served as the application UI and consumes the Laravel API. Use Laravel Sanctum for first-party session authentication unless repository constraints require an equivalent Laravel-native approach.
- Keep HR domains separated into Laravel modules/namespaces with explicit migrations, policies, requests, services, and tests; avoid one giant controller or an unstructured generic CRUD layer.
- Enforce tenant/company and branch scoping in server-side queries and authorization, not only in the UI.
- Store sensitive employee identifiers and uploaded files behind authorized endpoints; keep documents private, validate upload type/size, and audit sensitive reads and changes where appropriate.
- Use database transactions and immutable audit events for approvals, payroll finalization/reversal, employee status changes, and settlement actions.
- All monetary values use explicit currency and decimal-safe calculations. Payroll, leave, gratuity, overtime, and other rules are configurable and covered by boundary tests.
- Build exports (including WPS/SIF) as validated downloadable files with format/version metadata. No direct bank submission is assumed.

## Functional scope

Every listed core module is in scope:

1. **HR dashboard:** workforce, attendance, leave, payroll, overtime, expiry, probation, hiring/separation, and labor-cost summaries with filters and drill-downs.
2. **Employee management:** employee master/profile, personal/contact/emergency details, identity/visa/work-permit records, contracts, salary structures, bank/WPS details, dependents, qualifications, skills, history, status, photographs, and ID cards.
3. **Organization management:** companies, branches, business units, departments, sections, teams, designations, grades, reporting hierarchy, organization chart, locations, cost/profit centers, positions, manpower plans, and employee allocation.
4. **Recruitment and hiring:** manpower requisitions/approvals, vacancies, descriptions, candidates/resumes, screening, interview schedules/evaluations, stages, comparisons, selection and offer approvals, recruiting costs/agencies, and candidate-to-employee conversion.
5. **Onboarding and offboarding:** joining/exit checklists, offers, document collection, contracts, assignments, system-access requests, assets, orientation, probation/confirmation, and clearances.
6. **Attendance:** daily register, check-in/out, manual entry, corrections and missing punches, late/early/absence tracking, timesheets, work-hour/overtime calculation, approvals, and period closing. Device/mobile/GPS integrations remain adapter-based.
7. **Leave:** leave types/policies, entitlements, accrual/carry-forward/encashment, balances, applications/approvals/calendar/history, and leave salary.
8. **Shift and roster:** shift templates, fixed/rotating/day/night/split/flexible schedules, employee/department rosters, weekly offs, holidays, Ramadan schedules, swaps, approvals, and allowances.
9. **Payroll:** payroll periods, salary structures/components, earnings/deductions, overtime, unpaid leave/absence, loans, arrears/retroactive adjustments, calculations/review/approval/processing, payslips, WPS/SIF and bank-file exports, journal/reconciliation records, reversals, and final payroll.
10. **Benefits and compensation:** compensation, allowances, insurance, air tickets, gratuity eligibility, benefit rules/enrollment/claims, employer contributions, and cost reports.
11. **Performance:** annual/probation reviews, KPIs/OKRs/goals, department and employee targets, 360-degree feedback, self/manager evaluations, ratings, promotion/increment recommendations, and performance-improvement plans.
12. **Training and development:** needs analysis, calendars/programs/providers, enrollment/attendance/cost/evaluation, skills/competencies, certifications and expiry alerts, and career plans.
13. **Employee self-service:** profile/documents/payslips/attendance/timesheets/leave balances, leave and correction requests, overtime, advance/expense requests, loans, announcements, HR letters, personal-data update requests, and request tracking.
14. **Manager self-service:** team directory/attendance/leave, approvals, requisitions, performance reviews/reports, shift scheduling, and transfer requests.
15. **Expenses and reimbursement:** categories/policies, claims and receipts, travel/fuel/accommodation/food/petty cash, approvals, reimbursements, accounting integration boundaries, and reports.
16. **Loans and advances:** requests/applications, approvals, disbursements, repayment schedules/installments/balances, settlement/cancellation, payroll and accounting boundaries.
17. **Travel:** requests/approvals, itinerary/booking details, allowance/per-diem rules, expenses/advances/settlement, policies, and history.
18. **Disciplinary management:** complaints/incidents, investigations, warnings/notices/hearings, penalties/suspensions/corrective actions, and restricted history.
19. **Health and safety:** incidents/accidents/injuries, inspections, risk assessments, PPE, safety training, medical fitness/occupational health records, investigations, actions, and compliance reports.
20. **Assets and uniforms:** assignment/acknowledgment/return, asset condition/loss/damage, handover, uniforms/PPE, and inventory/fixed-asset integration boundaries.
21. **Document management:** private employee repository, contracts/identity/visa/labor documents/certificates/letters/templates, version history, expiry alerts, and permission checks.
22. **Separation and final settlement:** resignation/termination, notice, exit interviews, clearance, asset/loan recovery, leave/unpaid-salary/notice/gratuity/repatriation components, settlement approval/payslip, closure, and journal boundary.
23. **Reports and analytics:** employee/org/attendance/leave/payroll/overtime/recruitment/performance/training/compliance/labor-cost/settlement/audit reporting with appropriate filters and export permissions.
24. **HR settings:** company/branch/department/designation/grade/status/employment/contract masters; numbering; probation; salary/overtime/leave/attendance/shift/holiday rules; approval workflows; templates; notifications; permissions; and audit settings.

Advanced modules are also in scope: workforce planning, labor productivity, skills/competency matrix, succession planning, career paths, employee engagement/surveys, HR helpdesk, multi-company HR, UAE labor-compliance controls, Emiratisation tracking, gratuity provision, labor-cost allocation, and workforce analytics.

ERP integration boundaries must support accounting journals/advances/gratuity, cost centers, manufacturing labor and work orders, project timesheets/labor costing, inventory/assets/uniforms/PPE, bank/WPS exports, document management, approval workflows, and shared dashboards.

## User experience

- Create an enterprise application shell with responsive navigation, company/branch context, global search, notifications, user menu, and role-aware shortcuts.
- Provide consistent list/detail/create/edit patterns, filters, pagination, status badges, confirmation for consequential actions, empty/loading/error states, and accessible keyboard/focus behavior.
- Build dashboards, employee directory/profile, approval inbox, attendance and leave calendars, payroll period workflow, document center, reports, and settings as first-class screens.
- Use a refined visual system (clear hierarchy, restrained color, typography, spacing, charts, and responsive layouts) informed by the public reference without copying its identity or unverified screens.
- Ensure all module navigation routes resolve to a coherent, permission-aware page; avoid dead links and success-shaped placeholder actions.

## Delivery increments

1. **Foundation:** Laravel/Vue project structure, MySQL setup, authenticated shell, roles/permissions, company/branch scoping, audit foundation, shared UI/API patterns, synthetic demo fixtures, and automated checks.
2. **People and organization:** organization masters, employee records/profiles/documents, recruitment, onboarding/offboarding, and dashboard data.
3. **Time and absence:** shifts/rosters, attendance/timesheets/overtime, leave policies/balances/approvals, and manager/employee self-service.
4. **Compensation and finance:** salary structures, payroll review/approval/processing, payslips, loans/advances, benefits, expenses, travel, WPS/SIF and accounting/banking export boundaries.
5. **Growth and employee relations:** performance, training, helpdesk, engagement, discipline, health/safety, assets/uniforms, and document workflows.
6. **Separation, advanced planning, reporting, and integrations:** final settlement, workforce planning/productivity/succession/career, labor-cost allocation, audit/compliance analytics, and integration adapters/contract tests.

Each increment must leave the app runnable. Integrations that require unavailable external systems are delivered as tested contracts and explicit configuration/status surfaces, not simulated as live services.

## Acceptance criteria

- A new developer can follow the repository documentation to install dependencies, configure MySQL, run migrations, start the Laravel and Vue app, and execute tests.
- Authentication, role permissions, and company/branch boundaries are enforced by backend policies and covered by tests, including cross-company denial cases.
- All 24 core modules and advanced modules appear in role-appropriate navigation, with a coherent route and useful screen for each; every in-scope implemented action persists through the backend.
- Core end-to-end flows work across modules: candidate-to-employee onboarding; employee document expiry tracking; attendance-to-overtime approval; leave request/approval/balance update; payroll preview/review/approval/payslip/export; expense/loan approvals and deductions; and separation-to-final-settlement review.
- Payroll, leave, overtime, gratuity, and settlement calculations have deterministic unit tests for normal and boundary cases, and are clearly marked configurable pending legal/customer validation.
- Documents are private by default and access-controlled; upload, download, expiry, and authorization behaviors have tests.
- Reports use persisted data and correct company/branch filters; export output is permission-controlled.
- Integration adapters expose documented contract tests and fail visibly when not configured. No unsupported biometric, geolocation, bank, or ERP connection is represented as active.
- The UI is responsive and keyboard accessible for primary flows, with deliberate loading, empty, validation, and error states.
- No production employee data, credentials, or legal certification claims are included.

## Implementation progress

- **Foundation increment delivered:** Laravel/Vue/MySQL application shell, session authentication, role/company/branch scoping, module catalog, dashboard, employee directory/create, attendance check-in/out, and leave request/review with persisted balance updates.
- **Verification:** 8 tests passed (75 assertions), Vite production build passed, Composer validation passed, and local HTTP/browser smoke checks succeeded.
- **Remaining:** The other core and advanced workflows, policy editors, private document uploads, advanced payroll/UAE calculations, and external ERP/provider integrations remain staged. See [docs/DELIVERY-STATUS.md](../docs/DELIVERY-STATUS.md) for precise implementation status and known limitations.

## Approval gate

The user approved this plan on 2026-10-31. Continue implementing the staged scope incrementally and validate each delivery against the acceptance criteria. The initial local-development deployment assumption can be revised before any production hosting work.
