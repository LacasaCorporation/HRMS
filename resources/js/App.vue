<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { api, setCsrfToken } from './api/index.js';

const user = ref(null);
const activeSlug = ref('dashboard');
const modules = ref([]);
const dashboard = ref(null);
const employees = ref([]);
const attendance = ref([]);
const attendanceToday = ref(null);
const attendanceReport = ref({
    meta: { current_page: 1, last_page: 1, per_page: 50, total: 0 },
    metrics: { entries: 0, completed: 0, working: 0, hours: 0, average_hours: 0 },
    trend: [],
});
const attendanceFrom = ref(localDateInput(new Date(Date.now() - 6 * 86400000)));
const attendanceTo = ref(localDateInput(new Date()));
const attendanceSearch = ref('');
const attendanceStatusFilter = ref('all');
const attendanceExporting = ref(false);
const leaveData = ref({ requests: [], balances: [], types: [] });
const selfServiceData = ref({
    employee: null, balances: [], leave_requests: [], leave_types: [], attendance: [], today_attendance: null,
});
const managerServiceData = ref({ employees: [], leave_requests: [], attendance: [] });
const expenseData = ref({ claims: [], meta: { total: 0, pending: 0, approved: 0, rejected: 0, approved_amounts: [], truncated: false }, categories: [] });
const expenseTab = ref('all');
const expenseSearch = ref('');
const expenseStatusFilter = ref('all');
const expenseFormOpen = ref(false);
const expenseForm = ref({ category: 'meals', merchant: '', expense_date: localDateInput(new Date()), amount: '', description: '' });
const expenseReceiptFile = ref(null);
const expenseFormError = ref('');
const expenseReviewDialog = ref(null);
const expenseReviewOpen = ref(false);
const expenseReviewId = ref(null);
const expenseReviewDecision = ref('approve');
const expenseReviewNote = ref('');
const expenseReviewError = ref('');
const loanData = ref({ loans: [], meta: { total: 0, pending: 0, approved: 0, rejected: 0, repaid: 0, truncated: false, can_apply: false } });
const loanTab = ref('all');
const loanSearch = ref('');
const loanStatusFilter = ref('all');
const loanFormOpen = ref(false);
const loanForm = ref({ requested_amount: '', requested_installments: 6, purpose: '' });
const loanFormError = ref('');
const loanReviewDialog = ref(null);
const loanReviewOpen = ref(false);
const loanReviewId = ref(null);
const loanReviewDecision = ref('approve');
const loanReviewForm = ref({ approved_amount: '', repayment_installments: 6, first_due_date: '', review_note: '' });
const loanReviewError = ref('');
const loanRepaymentInstallmentId = ref(null);
const loanRepaymentForm = ref({ amount: '', recorded_date: localDateInput(new Date()), reference: '' });
const loanRepaymentError = ref('');
const travelData = ref({ trips: [], meta: { total: 0, pending: 0, approved: 0, report_submitted: 0, reconciled: 0, truncated: false, can_request: false } });
const travelTab = ref('all');
const travelSearch = ref('');
const travelStatusFilter = ref('all');
const travelFormOpen = ref(false);
const travelForm = ref({ origin: '', destination: '', purpose: '', departure_date: '', return_date: '', estimated_budget: '', itinerary_notes: '' });
const travelFormError = ref('');
const travelReviewDialog = ref(null);
const travelReviewOpen = ref(false);
const travelReviewId = ref(null);
const travelReviewDecision = ref('approve');
const travelReviewForm = ref({ approved_budget: '', review_note: '' });
const travelReviewError = ref('');
const travelReportId = ref(null);
const travelReportForm = ref({ actual_total: '', post_trip_note: '' });
const travelReportError = ref('');
const travelReconcileId = ref(null);
const travelReconcileNote = ref('');
const travelReconcileError = ref('');
const disciplinaryData = ref({
    cases: [], employees: [], investigators: [],
    meta: { total: 0, open: 0, under_review: 0, resolved: 0, limit: 300, truncated: false },
});
const disciplinaryTab = ref('all');
const disciplinarySearch = ref('');
const disciplinaryFormOpen = ref(false);
const disciplinaryForm = ref({ employee_id: '', case_type: 'conduct', incident_date: localDateInput(new Date()), summary: '' });
const disciplinaryFormError = ref('');
const disciplinaryActionId = ref(null);
const disciplinaryActionType = ref('');
const disciplinaryActionForm = ref({ investigator_id: '', investigation_notes: '', employee_response: '', resolution_summary: '' });
const disciplinaryActionError = ref('');
const safetyData = ref({
    records: [], employees: [], assignees: [],
    meta: { total: 0, open: 0, in_progress: 0, resolved: 0, limit: 300, truncated: false },
});
const safetyTab = ref('all');
const safetySearch = ref('');
const safetyFormOpen = ref(false);
const safetyForm = ref({
    record_type: 'hazard', occurred_on: localDateInput(new Date()), location: '',
    priority: 'normal', summary: '', employee_id: '',
});
const safetyFormError = ref('');
const safetyActionId = ref(null);
const safetyActionType = ref('');
const safetyActionForm = ref({ assigned_to: '', action_plan: '', resolution_summary: '' });
const safetyActionError = ref('');
const selfServiceTab = ref('overview');
const managerServiceTab = ref('overview');
const selfServiceProfileForm = ref({ work_email: '', phone: '' });
const selfServiceProfileError = ref('');
const selfServiceLeaveError = ref('');
const selfServiceAttendanceError = ref('');
const managerReviewDialog = ref(null);
const managerReviewOpen = ref(false);
const managerReviewRequestId = ref(null);
const managerReviewDecision = ref('approved');
const managerReviewNote = ref('');
const managerReviewAcknowledged = ref(false);
const managerReviewError = ref('');
const organization = ref({ company: null, branches: [], departments: [], positions: [], employees: [], metrics: {} });
const recruitment = ref({
    requisitions: [], candidates: [], departments: [], positions: [], pipeline: {}, metrics: {},
    candidate_pagination: { current_page: 1, last_page: 1, per_page: 100, total: 0 },
});
const lifecycle = ref({
    cases: [], employees: [],
    metrics: { active_onboarding: 0, active_offboarding: 0, overdue_tasks: 0, tasks_due_soon: 0, completed_this_month: 0 },
});
const shiftRoster = ref({ assignments: [], patterns: [], employees: [], branches: [], meta: {} });
const rosterWeekStart = ref(currentWeekStart());
const rosterSearch = ref('');
const rosterTab = ref('week');
const showRosterAssignmentForm = ref(false);
const showShiftPatternForm = ref(false);
const rosterFormError = ref('');
const shiftPatternFormError = ref('');
const shiftPatternForm = ref({
    name: '', code: '', branch_id: '', start_time: '09:00', end_time: '17:00', break_minutes: 60,
});
const rosterAssignmentForm = ref({ employee_id: '', shift_pattern_id: '', shift_date: '', notes: '' });
const payrollData = ref({ cycles: [], employees: [], branches: [], meta: {} });
const payrollTab = ref('cycles');
const selectedPayrollCycleId = ref(null);
const payrollSearch = ref('');
const payrollStatusFilter = ref('all');
const payrollEmployeeSearch = ref('');
const showPayrollCycleForm = ref(false);
const payrollFormError = ref('');
const payrollReviewNote = ref('');
const payrollItemDrafts = ref({});
const payrollItemErrors = ref({});
const payrollCycleForm = ref({
    period_start: currentMonthStart(),
    period_end: localDateInput(new Date()),
    pay_date: localDateInput(new Date()),
    currency: 'AED',
    branch_id: '',
    employee_ids: [],
});
const payrollExporting = ref(false);
const payrollReviewDialog = ref(null);
const payrollReviewOpen = ref(false);
const payrollReviewDecision = ref('approve');
const payrollReviewAcknowledged = ref(false);
const payrollReviewError = ref('');
const benefitsData = ref({ plans: [], enrollments: [], employees: [], branches: [], meta: {} });
const benefitsTab = ref('overview');
const benefitsSearch = ref('');
const benefitStatusFilter = ref('all');
const showBenefitPlanForm = ref(false);
const showBenefitEnrollmentForm = ref(false);
const editingBenefitPlanId = ref(null);
const benefitFormError = ref('');
const benefitEnrollmentError = ref('');
const benefitPlanForm = ref({
    name: '', category: 'allowance', currency: 'AED', branch_id: '', provider: '',
    description: '', eligibility_notes: '', status: 'active',
});
const benefitEnrollmentForm = ref({
    benefit_plan_id: '', employee_id: '', effective_start: localDateInput(new Date()),
    effective_end: '', notes: '',
});
const benefitContributionDrafts = ref({});
const benefitContributionErrors = ref({});
const benefitReviewDialog = ref(null);
const benefitReviewOpen = ref(false);
const selectedBenefitEnrollmentId = ref(null);
const benefitReviewDecision = ref('approve');
const benefitReviewAcknowledged = ref(false);
const benefitReviewNote = ref('');
const benefitReviewError = ref('');
const benefitEndOpen = ref(false);
const benefitEndDialog = ref(null);
const benefitEndDate = ref(localDateInput(new Date()));
const benefitEndError = ref('');
const benefitsExporting = ref(false);
const separationData = ref({ settlements: [], offboarding_cases: [], meta: {} });
const separationTab = ref('overview');
const separationSearch = ref('');
const separationStatusFilter = ref('all');
const selectedSettlementId = ref(null);
const showSettlementForm = ref(false);
const settlementForm = ref({ lifecycle_case_id: '', notes: '' });
const settlementFormError = ref('');
const settlementItemDrafts = ref({});
const settlementItemError = ref('');
const settlementReviewDialog = ref(null);
const settlementReviewOpen = ref(false);
const settlementReviewDecision = ref('approve');
const settlementReviewAcknowledged = ref(false);
const settlementReviewNote = ref('');
const settlementReviewError = ref('');
const settlementExporting = ref(false);
const performanceData = ref({ cycles: [], reviews: [], employees: [], branches: [], meta: {} });
const performanceTab = ref('overview');
const performanceSearch = ref('');
const performanceStatusFilter = ref('all');
const selectedPerformanceCycleId = ref(null);
const selectedPerformanceReviewId = ref(null);
const showPerformanceCycleForm = ref(false);
const performanceCycleError = ref('');
const performanceCycleForm = ref({
    title: '', period_start: currentMonthStart(), period_end: localDateInput(new Date()),
    due_date: '', branch_id: '', description: '', employee_ids: [],
});
const performanceReviewDrafts = ref({});
const performanceReviewErrors = ref({});
const performanceReviewDialog = ref(null);
const performanceReviewOpen = ref(false);
const performanceReviewDecision = ref('approve');
const performanceReviewAcknowledged = ref(false);
const performanceReviewNote = ref('');
const performanceReviewError = ref('');
const performanceExporting = ref(false);
const trainingData = ref({ programs: [], enrollments: [], employees: [], branches: [], meta: {} });
const trainingTab = ref('overview');
const trainingSearch = ref('');
const trainingStatusFilter = ref('all');
const selectedTrainingProgramId = ref(null);
const selectedTrainingEnrollmentId = ref(null);
const showTrainingProgramForm = ref(false);
const showTrainingEnrollmentForm = ref(false);
const editingTrainingProgramId = ref(null);
const trainingProgramError = ref('');
const trainingEnrollmentError = ref('');
const trainingEnrollmentUpdateError = ref('');
const trainingTransitionOpen = ref(false);
const trainingTransitionDialog = ref(null);
const pendingTrainingTransition = ref(null);
const trainingProgramForm = ref({
    title: '', category: 'professional', provider: '', delivery_mode: 'classroom',
    start_date: '', due_date: '', capacity: '', branch_id: '', description: '', status: 'active',
});
const trainingEnrollmentForm = ref({ training_program_id: '', employee_id: '', notes: '' });
const trainingEnrollmentDrafts = ref({});
const trainingExporting = ref(false);
const loading = ref(false);
const busy = ref(false);
const error = ref('');
const notice = ref('');
const loginForm = ref({ email: 'maya.hr@example.test', password: 'password' });
const employeeSearch = ref('');
const showEmployeeForm = ref(false);
const employeeForm = ref({
    employee_code: '', first_name: '', last_name: '', work_email: '',
    job_title: '', department: '', employment_type: 'Full-time', join_date: new Date().toISOString().slice(0, 10),
});
const leaveForm = ref({
    leave_type_id: '', start_date: '', end_date: '', reason: '',
});
const leaveReviewNote = ref('');
const organizationTab = ref('overview');
const organizationSearch = ref('');
const showDepartmentForm = ref(false);
const showPositionForm = ref(false);
const editingDepartmentId = ref(null);
const departmentForm = ref({ name: '', code: '', branch_id: '', parent_id: '', manager_employee_id: '', cost_center: '', status: 'active' });
const positionForm = ref({ title: '', code: '', department_id: '', branch_id: '', reports_to_position_id: '', grade: '', employment_type: 'Full-time', headcount: 1 });
const managerDrafts = ref({});
const assignmentDrafts = ref({});
const recruitmentTab = ref('overview');
const recruitmentSearch = ref('');
const recruitmentStageFilter = ref('all');
const recruitmentRequestFilter = ref('all');
const showRequisitionForm = ref(false);
const showCandidateForm = ref(false);
const requisitionForm = ref({
    code: '', title: '', department_id: '', position_id: '', employment_type: 'Full-time',
    work_mode: 'On-site', location: '', openings: 1, priority: 'normal', target_date: '', description: '',
});
const candidateForm = ref({
    requisition_id: '', first_name: '', last_name: '', email: '', phone: '',
    source: 'Direct', rating: '', notes: '',
});
const candidateStageDrafts = ref({});
const candidateFeedbackDrafts = ref({});
const candidateActionErrors = ref({});
const recruitmentFormError = ref('');
const hireActionError = ref('');
const selectedCandidate = ref(null);
const hireDialog = ref(null);
const hireForm = ref({ employee_code: '', join_date: new Date().toISOString().slice(0, 10) });
const lifecycleTab = ref('all');
const lifecycleSearch = ref('');
const lifecycleStatusFilter = ref('all');
const showLifecycleForm = ref(false);
const lifecycleForm = ref({
    type: 'onboarding', employee_id: '', effective_date: new Date(Date.now() + 14 * 86400000).toISOString().slice(0, 10), notes: '',
});
const lifecycleFormError = ref('');
const selectedLifecycleCaseId = ref(null);
const lifecycleTaskDrafts = ref({});
const lifecycleTaskErrors = ref({});
const lifecycleCloseDialog = ref(null);
const lifecycleCloseOpen = ref(false);
const lifecycleCloseAcknowledged = ref(false);
let recruitmentFilterTimer = null;
let attendanceFilterTimer = null;

const descriptions = {
    dashboard: 'A company-scoped view of workforce, time, and approvals from saved records.',
    employees: 'A searchable employee directory with persisted employee creation and company/branch data boundaries.',
    attendance: 'Employee check-in/out and a persisted daily attendance register. Device and location integrations are not enabled.',
    leave: 'Submit leave requests, review scoped approvals, and update balances only when a request is approved.',
    organization: 'Organization structures, reporting relationships, positions, and allocation masters.',
    recruitment: 'Plan hiring, organize a private candidate pipeline, and convert successful offers into employee records.',
    'onboarding-offboarding': 'Run employee onboarding and offboarding with dated checklists, progress tracking, and controlled closure.',
    'shifts-rosters': 'Create shift patterns and publish a scoped weekly roster with assignment and leave-conflict safeguards.',
    payroll: 'Prepare payroll input cycles, capture reviewed amounts, route approvals, and export a controlled workpaper.',
    benefits: 'Manage program metadata, manually record benefit enrollment amounts, and document HR review decisions.',
    performance: 'Goals, reviews, feedback, and documented performance plans.',
    training: 'Training needs, programs, attendance, credentials, and career development.',
    'employee-self-service': 'Your own profile contacts, attendance, and leave in one private workspace.',
    'manager-self-service': 'Direct-report directory, leave approvals, and attendance records.',
    expenses: 'Submit expense claims with receipts, review scoped requests, and keep approval history in one place.',
    'loans-advances': 'Request an advance, review principal-only repayment schedules, and reconcile repayments recorded outside the app.',
    travel: 'Submit business travel requests, record itinerary details, and reconcile post-trip reports.',
    disciplinary: 'Restricted incident, investigation, hearing, and corrective-action records.',
    'health-safety': 'Restricted, company/branch-scoped incident, hazard, inspection, and PPE records with assigned manual follow-up and documented resolution.',
    'assets-uniforms': 'Assignment, handover, return, condition, uniforms, and PPE records.',
    documents: 'Private employee records with controlled uploads, access, and expiry tracking.',
    'separation-settlement': 'Prepare offboarding-linked settlement workpapers with manual lines, completed clearances, and recorded review.',
    reports: 'Permission-aware views and exports based on persisted domain data.',
    settings: 'Company masters, leave and attendance rules, approval workflows, and access settings.',
    'workforce-planning': 'Manpower plans, positions, demand forecasts, and budget boundaries.',
    'labor-productivity': 'Configurable labor allocation and productivity measures for manufacturing operations.',
    'skills-competencies': 'Skills inventory, competency levels, role requirements, and gap analysis.',
    'succession-planning': 'Critical-role coverage, readiness reviews, and succession candidates.',
    'career-paths': 'Role families, progression criteria, career goals, and internal mobility.',
    'engagement-surveys': 'Survey cycles, confidential response controls, and engagement trends.',
    helpdesk: 'Employee HR requests, assignment, response history, and service metrics.',
    'uae-compliance': 'Configurable document expiries, WPS preparation boundaries, and compliance checks.',
    emiratization: 'Configurable Emiratisation workforce views and reporting.',
    'gratuity-provision': 'Configurable EOSB eligibility and provision estimates pending legal review.',
    'labor-cost-allocation': 'Cost-center rules, accounting boundaries, and workforce cost reporting.',
    'workforce-analytics': 'Company-scoped workforce trends built on validated operational data.',
    integrations: 'Explicit contracts for ERP, manufacturing, projects, assets, banking, and document systems.',
};

const stagedScope = {
    'onboarding-offboarding': 'Persisted onboarding and offboarding checklists, task ownership, due dates, progress, and explicit employee deactivation on completed exits.',
    'shifts-rosters': 'Shift patterns and weekly roster assignments are persisted and scoped by company, branch, and employee visibility. Holiday calendars, automatic rotations, and swap approvals remain staged.',
    payroll: 'Payroll cycles, manual compensation inputs, arithmetic gross/net totals, review approvals, audit events, and CSV workpapers are persisted. No statutory tax, social insurance, wage protection, payslip, or payment calculations are configured.',
    benefits: 'Benefit plan settings, employee enrollment, and documented review decisions are persisted. Contribution amounts are manually entered.',
    performance: 'Evaluation cycles, goals, 360 feedback, and recommendations are staged.',
    training: 'Programs, provider records, attendance, certifications, and career plans are staged.',
    'employee-self-service': 'Employee-owned contacts, leave, and attendance are available. Private documents, letters, and payslips are not exposed.',
    'manager-self-service': 'Direct-report overview, team attendance, and leave decisions are available. Scheduling and other team workflows remain staged.',
    expenses: 'Employee claim submission, private receipt downloads, and direct-report or HR review are available. Approval does not trigger reimbursement or payment.',
    'loans-advances': 'Employee applications, scoped approvals, equal-principal schedules, and HR/admin repayment reconciliation are available. No interest, disbursement, payroll deduction, or collection is initiated.',
    travel: 'Travel requests, manager/HR decisions, itinerary notes, approved budget ceilings, and manual post-trip report reconciliation are available. No booking or reimbursement is initiated.',
    disciplinary: 'Restricted, company/branch-scoped case intake, investigation, employee-response recording, and manual resolution tracking for HR/admin.',
    'health-safety': 'Restricted HR/admin intake, manually entered priority, scoped assignment, action tracking, and documented resolution. No medical details or automated risk/compliance assessments are recorded.',
    'assets-uniforms': 'Assignments, returns, uniform records, and ERP boundaries are staged.',
    documents: 'Private file access, upload validation, expiry alerts, and retention controls are staged.',
    'separation-settlement': 'Settlement workpapers are linked to completed offboarding clearances. Earning and recovery lines are manually entered.',
    reports: 'A persisted dashboard is available. Filterable exports and advanced analytics are staged.',
    settings: 'Company context is seeded. Configurable policy and workflow editors are staged.',
    'workforce-planning': 'Position plans, demand assumptions, and budget controls are staged.',
    'labor-productivity': 'Manufacturing and timesheet integration contracts are staged.',
    'skills-competencies': 'Skill taxonomies, assessments, and gap analysis are staged.',
    'succession-planning': 'Succession and readiness-review workflows are staged.',
    'career-paths': 'Career frameworks and internal mobility workflows are staged.',
    'engagement-surveys': 'Survey authoring and confidential response handling are staged.',
    helpdesk: 'Ticket ownership, response SLA, and employee history are staged.',
    'uae-compliance': 'No legal certification is claimed. WPS/SIF, visa, identity, and labor-rule controls are staged for customer/legal verification.',
    emiratization: 'Reporting and compliance rules require customer/legal validation and are staged.',
    'gratuity-provision': 'EOSB rules and provision calculations require customer/legal validation and are staged.',
    'labor-cost-allocation': 'Cost-center mapping and journal export contracts are staged.',
    'workforce-analytics': 'Advanced metrics will be delivered after their source workflows are implemented.',
    integrations: 'No external system is connected. Adapters require authorized credentials and a test environment.',
};

const moduleBySlug = computed(() => modules.value.find((module) => module.slug === activeSlug.value));
const isApprover = computed(() => ['admin', 'hr', 'manager'].includes(user.value?.role));
const canManageOrganization = computed(() => ['admin', 'hr'].includes(user.value?.role));
const selfServiceEmployee = computed(() => selfServiceData.value.employee);
const selfServicePendingLeaveCount = computed(() => selfServiceData.value.leave_requests
    .filter((request) => request.status === 'pending').length);
const selfServiceAvailableLeaveDays = computed(() => selfServiceData.value.balances
    .reduce((total, balance) => total + Math.max(0, Number(balance.entitled_days) - Number(balance.used_days)), 0));
const selfServiceProfileChanged = computed(() => Boolean(selfServiceEmployee.value)
    && (String(selfServiceProfileForm.value.work_email || '') !== String(selfServiceEmployee.value.work_email || '')
        || String(selfServiceProfileForm.value.phone || '') !== String(selfServiceEmployee.value.phone || '')));
const selfServiceContactComplete = computed(() => [selfServiceEmployee.value?.work_email, selfServiceEmployee.value?.phone]
    .filter((value) => String(value || '').trim()).length);
const selfServiceUpcomingLeave = computed(() => selfServiceData.value.leave_requests
    .filter((request) => request.status === 'approved' && request.start_date >= localDateInput(new Date()))
    .sort((left, right) => left.start_date.localeCompare(right.start_date))[0] || null);
const managerTeam = computed(() => managerServiceData.value.employees
    .filter((employee) => employee.manager_id === user.value?.employee?.id));
const managerTeamIds = computed(() => new Set(managerTeam.value.map((employee) => employee.id)));
const managerTeamLeaveRequests = computed(() => managerServiceData.value.leave_requests
    .filter((request) => managerTeamIds.value.has(request.employee_id)));
const managerOnLeaveToday = computed(() => managerTeamLeaveRequests.value
    .filter((request) => request.status === 'approved'
        && request.start_date <= localDateInput(new Date())
        && request.end_date >= localDateInput(new Date())));
const managerPendingLeaveRequests = computed(() => managerTeamLeaveRequests.value
    .filter((request) => request.status === 'pending'));
const managerTeamAttendance = computed(() => managerServiceData.value.attendance
    .filter((entry) => managerTeamIds.value.has(entry.employee_id)));
const managerTodayAttendance = computed(() => managerTeamAttendance.value
    .filter((entry) => String(entry.attendance_date).slice(0, 10) === localDateInput(new Date())));
const managerWorkingCount = computed(() => managerTodayAttendance.value
    .filter((entry) => entry.check_in && !entry.check_out).length);
const canReviewExpenses = computed(() => ['admin', 'hr', 'manager'].includes(user.value?.role));
const canSubmitExpenses = computed(() => ['admin', 'hr', 'manager', 'employee'].includes(user.value?.role)
    && user.value?.employee?.status === 'active');
const expenseReviewQueue = computed(() => expenseData.value.claims.filter((claim) => claim.can_review));
const selectedExpenseClaim = computed(() => expenseData.value.claims.find((claim) => claim.id === expenseReviewId.value) || null);
const visibleExpenseClaims = computed(() => {
    const query = expenseSearch.value.trim().toLowerCase();
    return expenseData.value.claims.filter((claim) => {
        const matchesTab = expenseTab.value !== 'review' || claim.can_review;
        const matchesStatus = expenseStatusFilter.value === 'all' || claim.status === expenseStatusFilter.value;
        const matchesSearch = !query || [
            claim.employee_name, claim.employee_code, claim.department, claim.merchant,
            claim.category, claim.description, claim.status,
        ].some((value) => String(value || '').toLowerCase().includes(query));
        return matchesTab && matchesStatus && matchesSearch;
    });
});
const expenseApprovedTotals = computed(() => (expenseData.value.meta.approved_amounts || [])
    .map((item) => `${item.currency} ${Number(item.total).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`)
    .join(' · ') || '—');
const canApplyForLoan = computed(() => Boolean(loanData.value.meta.can_apply));
const loanReviewQueue = computed(() => loanData.value.loans.filter((loan) => loan.can_review));
const selectedLoanAdvance = computed(() => loanData.value.loans.find((loan) => loan.id === loanReviewId.value) || null);
const visibleLoanAdvances = computed(() => {
    const query = loanSearch.value.trim().toLowerCase();
    return loanData.value.loans.filter((loan) => {
        const matchesTab = loanTab.value !== 'review' || loan.can_review;
        const matchesStatus = loanStatusFilter.value === 'all' || loan.status === loanStatusFilter.value;
        const matchesSearch = !query || [
            loan.employee_name, loan.employee_code, loan.department, loan.purpose, loan.currency, loan.status,
        ].some((value) => String(value || '').toLowerCase().includes(query));
        return matchesTab && matchesStatus && matchesSearch;
    });
});
const loanOutstandingTotals = computed(() => {
    const totals = new Map();
    loanData.value.loans.filter((loan) => ['approved', 'repaid'].includes(loan.status)).forEach((loan) => {
        const currency = loan.currency || '—';
        totals.set(currency, (totals.get(currency) || 0) + Number(loan.outstanding_amount || 0));
    });
    return [...totals.entries()]
        .map(([currency, amount]) => `${currency} ${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`)
        .join(' · ') || '—';
});
const canRequestTravel = computed(() => Boolean(travelData.value.meta.can_request));
const travelReviewQueue = computed(() => travelData.value.trips.filter((trip) => trip.can_review));
const travelReports = computed(() => travelData.value.trips.filter((trip) =>
    trip.can_submit_report || trip.can_reconcile || ['report_submitted', 'reconciled'].includes(trip.status),
));
const selectedTravelTrip = computed(() => travelData.value.trips.find((trip) => trip.id === travelReviewId.value) || null);
const visibleTravelTrips = computed(() => {
    const query = travelSearch.value.trim().toLowerCase();
    return travelData.value.trips.filter((trip) => {
        const matchesTab = travelTab.value === 'all'
            || (travelTab.value === 'review' && trip.can_review)
            || (travelTab.value === 'reports' && travelReports.value.includes(trip));
        const matchesStatus = travelStatusFilter.value === 'all' || trip.status === travelStatusFilter.value;
        const matchesSearch = !query || [
            trip.employee_name, trip.employee_code, trip.origin, trip.destination, trip.purpose, trip.status,
        ].some((value) => String(value || '').toLowerCase().includes(query));
        return matchesTab && matchesStatus && matchesSearch;
    });
});
const travelApprovedTotals = computed(() => {
    const totals = new Map();
    travelData.value.trips.filter((trip) => trip.approved_budget !== null).forEach((trip) => {
        const currency = trip.currency || '—';
        totals.set(currency, (totals.get(currency) || 0) + Number(trip.approved_budget));
    });
    return [...totals.entries()]
        .map(([currency, amount]) => `${currency} ${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`)
        .join(' · ') || '—';
});
const filteredDisciplinaryCases = computed(() => {
    const query = disciplinarySearch.value.trim().toLowerCase();
    return disciplinaryData.value.cases.filter((caseItem) => {
        const matchesTab = disciplinaryTab.value === 'all'
            || (disciplinaryTab.value === 'open' && caseItem.status === 'open')
            || (disciplinaryTab.value === 'under_review' && caseItem.status === 'under_review')
            || (disciplinaryTab.value === 'resolved' && caseItem.status === 'resolved');
        const matchesSearch = !query || [
            caseItem.employee?.first_name, caseItem.employee?.last_name, caseItem.employee?.employee_code,
            caseItem.case_type, caseItem.status, caseItem.summary, caseItem.investigator?.name,
        ].some((value) => String(value || '').toLowerCase().includes(query));
        return matchesTab && matchesSearch;
    });
});
const filteredSafetyRecords = computed(() => {
    const query = safetySearch.value.trim().toLowerCase();
    return safetyData.value.records.filter((record) => {
        const matchesTab = safetyTab.value === 'all'
            || (safetyTab.value === 'open' && record.status === 'open')
            || (safetyTab.value === 'in_progress' && record.status === 'in_progress')
            || (safetyTab.value === 'resolved' && record.status === 'resolved');
        const matchesSearch = !query || [
            record.employee?.first_name, record.employee?.last_name, record.employee?.employee_code,
            record.record_type, record.location, record.summary, record.status, record.assignee?.name,
        ].some((value) => String(value || '').toLowerCase().includes(query));
        return matchesTab && matchesSearch;
    });
});
const selectedManagerRequest = computed(() => managerTeamLeaveRequests.value
    .find((request) => request.id === managerReviewRequestId.value) || null);
const filteredLifecycleCases = computed(() => {
    const query = lifecycleSearch.value.trim().toLowerCase();
    return lifecycle.value.cases.filter((caseItem) => {
        const matchesTab = lifecycleTab.value === 'all' || caseItem.type === lifecycleTab.value;
        const matchesSearch = !query || [
            caseItem.employee?.first_name, caseItem.employee?.last_name, caseItem.employee?.employee_code,
            caseItem.employee?.job_title, caseItem.employee?.department, caseItem.type, caseItem.status,
        ].some((value) => String(value || '').toLowerCase().includes(query));
        const overdue = caseItem.status === 'in_progress' && caseItem.tasks.some((task) => isLifecycleTaskOverdue(task));
        const matchesStatus = lifecycleStatusFilter.value === 'all'
            || (lifecycleStatusFilter.value === 'overdue' ? overdue : caseItem.status === lifecycleStatusFilter.value);
        return matchesTab && matchesSearch && matchesStatus;
    });
});
const availableLifecycleEmployees = computed(() => lifecycle.value.employees.filter((employee) =>
    !lifecycle.value.cases.some((caseItem) => caseItem.employee_id === employee.id && caseItem.type === lifecycleForm.value.type)));
const selectedLifecycleCase = computed(() => lifecycle.value.cases.find((caseItem) => caseItem.id === selectedLifecycleCaseId.value) || null);
const selectedLifecycleProgress = computed(() => selectedLifecycleCase.value
    ? lifecycleCaseProgress(selectedLifecycleCase.value)
    : 0);
const selectedLifecycleTasksComplete = computed(() => Boolean(selectedLifecycleCase.value?.tasks.length)
    && selectedLifecycleCase.value.tasks.every((task) => task.status === 'completed'));
const rosterWeekDays = computed(() => Array.from({ length: 7 }, (_, index) => {
    const date = new Date(`${rosterWeekStart.value}T12:00:00`);
    date.setDate(date.getDate() + index);
    return localDateInput(date);
}));
const filteredRosterAssignments = computed(() => {
    const query = rosterSearch.value.trim().toLowerCase();
    return shiftRoster.value.assignments.filter((assignment) => !query || [
        fullName(assignment.employee), assignment.employee?.employee_code,
        assignment.employee?.department, assignment.shift_pattern?.name,
        assignment.employee?.branch?.name,
    ].some((value) => String(value || '').toLowerCase().includes(query)));
});
const activeShiftPatterns = computed(() => shiftRoster.value.patterns.filter((pattern) => pattern.status === 'active'));
const selectedPayrollCycle = computed(() => payrollData.value.cycles.find((cycle) => cycle.id === selectedPayrollCycleId.value) || null);
const visiblePayrollCycles = computed(() => {
    const query = payrollSearch.value.trim().toLowerCase();
    return payrollData.value.cycles.filter((cycle) => {
        const statusMatches = payrollStatusFilter.value === 'all' || cycle.status === payrollStatusFilter.value;
        const searchMatches = !query || [
            cycle.currency, cycle.branch?.name, cycle.period_start, cycle.period_end, cycle.status,
        ].some((value) => String(value || '').toLowerCase().includes(query));
        return statusMatches && searchMatches;
    });
});
const payrollScopeEmployees = computed(() => payrollData.value.employees.filter((employee) => {
    const currencyMatches = employee.currency === payrollCycleForm.value.currency;
    const branchMatches = !payrollCycleForm.value.branch_id
        || String(employee.branch_id) === String(payrollCycleForm.value.branch_id);
    return currencyMatches && branchMatches;
}));
const eligiblePayrollEmployees = computed(() => payrollScopeEmployees.value.filter((employee) => {
    const query = payrollEmployeeSearch.value.trim().toLowerCase();
    const searchMatches = !query || [
        fullName(employee), employee.employee_code, employee.department, employee.currency,
    ].some((value) => String(value || '').toLowerCase().includes(query));
    return searchMatches;
}));
const selectedPayrollTotals = computed(() => {
    const items = selectedPayrollCycle.value?.items || [];
    return items.reduce((totals, item) => {
        totals.ready += item.net_pay !== null ? 1 : 0;
        totals.base += Number(item.base_pay || 0);
        totals.earnings += Number(item.other_earnings || 0);
        totals.deductions += Number(item.manual_deductions || 0);
        totals.net += Number(item.net_pay || 0);
        return totals;
    }, { ready: 0, base: 0, earnings: 0, deductions: 0, net: 0 });
});
const filteredPayrollItems = computed(() => {
    const query = payrollSearch.value.trim().toLowerCase();
    return (selectedPayrollCycle.value?.items || []).filter((item) => !query || [
        item.employee_name, item.employee_code, item.department,
    ].some((value) => String(value || '').toLowerCase().includes(query)));
});
const selectedBenefitEnrollment = computed(() => benefitsData.value.enrollments.find((enrollment) => enrollment.id === selectedBenefitEnrollmentId.value) || null);
const visibleBenefitPlans = computed(() => {
    const query = benefitsSearch.value.trim().toLowerCase();
    return benefitsData.value.plans.filter((plan) => !query || [
        plan.name, plan.category, plan.provider, plan.currency, plan.branch?.name, plan.status,
    ].some((value) => String(value || '').toLowerCase().includes(query)));
});
const visibleBenefitEnrollments = computed(() => {
    const query = benefitsSearch.value.trim().toLowerCase();
    return benefitsData.value.enrollments.filter((enrollment) => {
        const statusMatches = benefitStatusFilter.value === 'all' || enrollment.status === benefitStatusFilter.value;
        const searchMatches = !query || [
            enrollment.employee_name, enrollment.employee_code, enrollment.department,
            enrollment.plan?.name, enrollment.plan?.category, enrollment.status, enrollment.branch?.name,
        ].some((value) => String(value || '').toLowerCase().includes(query));
        return statusMatches && searchMatches;
    });
});
const benefitEnrollmentEmployees = computed(() => {
    const plan = benefitsData.value.plans.find((entry) => String(entry.id) === String(benefitEnrollmentForm.value.benefit_plan_id));
    return benefitsData.value.employees.filter((employee) => !plan || employee.currency === plan.currency);
});
const selectedSettlement = computed(() => separationData.value.settlements.find((settlement) => settlement.id === selectedSettlementId.value) || null);
const visibleSettlements = computed(() => {
    const query = separationSearch.value.trim().toLowerCase();
    return separationData.value.settlements.filter((settlement) => {
        const statusMatches = separationStatusFilter.value === 'all' || settlement.status === separationStatusFilter.value;
        const searchMatches = !query || [
            settlement.employee_name, settlement.employee_code, settlement.department,
            settlement.currency, settlement.status, settlement.offboarding_case?.effective_date,
        ].some((value) => String(value || '').toLowerCase().includes(query));
        return statusMatches && searchMatches;
    });
});
const eligibleSettlementCases = computed(() => separationData.value.offboarding_cases.filter((caseItem) =>
    !separationData.value.settlements.some((settlement) => settlement.lifecycle_case_id === caseItem.id)));
const selectedSettlementTotals = computed(() => {
    const totals = (selectedSettlement.value?.items || []).reduce((result, item) => {
        if (item.category === 'earning') result.earnings += Number(item.amount || 0);
        if (item.category === 'recovery') result.recoveries += Number(item.amount || 0);
        return result;
    }, { earnings: 0, recoveries: 0 });
    return { ...totals, net: totals.earnings - totals.recoveries };
});
const performanceCycleEmployees = computed(() => performanceData.value.employees.filter((employee) =>
    !performanceCycleForm.value.branch_id || String(employee.branch_id) === String(performanceCycleForm.value.branch_id)));
const visiblePerformanceReviews = computed(() => {
    const query = performanceSearch.value.trim().toLowerCase();
    return performanceData.value.reviews.filter((review) => {
        const statusMatches = performanceStatusFilter.value === 'all' || review.status === performanceStatusFilter.value;
        const cycleMatches = !selectedPerformanceCycleId.value
            || String(review.performance_cycle_id) === String(selectedPerformanceCycleId.value);
        const searchMatches = !query || [
            review.employee_name, review.employee_code, review.department, review.cycle?.title, review.status,
        ].some((value) => String(value || '').toLowerCase().includes(query));
        return statusMatches && cycleMatches && searchMatches;
    });
});
const selectedPerformanceReview = computed(() => performanceData.value.reviews.find((review) => review.id === selectedPerformanceReviewId.value) || null);
const visibleTrainingPrograms = computed(() => {
    const query = trainingSearch.value.trim().toLowerCase();
    return trainingData.value.programs.filter((program) => !query || [
        program.title, program.category, program.provider, program.delivery_mode, program.branch?.name, program.status,
    ].some((value) => String(value || '').toLowerCase().includes(query)));
});
const visibleTrainingEnrollments = computed(() => {
    const query = trainingSearch.value.trim().toLowerCase();
    return trainingData.value.enrollments.filter((enrollment) => {
        const statusMatches = trainingStatusFilter.value === 'all' || enrollment.status === trainingStatusFilter.value;
        const searchMatches = !query || [
            enrollment.employee_name, enrollment.employee_code, enrollment.department,
            enrollment.program?.title, enrollment.program?.category, enrollment.status,
        ].some((value) => String(value || '').toLowerCase().includes(query));
        return statusMatches && searchMatches;
    });
});
const selectedTrainingProgram = computed(() => trainingData.value.programs.find((program) => program.id === selectedTrainingProgramId.value) || null);
const selectedTrainingEnrollment = computed(() => trainingData.value.enrollments.find((enrollment) => enrollment.id === selectedTrainingEnrollmentId.value) || null);
const trainingEnrollmentEmployees = computed(() => {
    const program = trainingData.value.programs.find((entry) => String(entry.id) === String(trainingEnrollmentForm.value.training_program_id));
    return trainingData.value.employees.filter((employee) => !program?.branch_id || String(employee.branch_id) === String(program.branch_id));
});

watch([() => payrollCycleForm.value.currency, () => payrollCycleForm.value.branch_id], () => {
    if (showPayrollCycleForm.value) {
        const eligibleIds = new Set(payrollScopeEmployees.value.map((employee) => employee.id));
        payrollCycleForm.value.employee_ids = payrollCycleForm.value.employee_ids.filter((id) => eligibleIds.has(id));
    }
});

watch(payrollReviewOpen, async (open) => {
    if (!open) return;
    await nextTick();
    if (payrollReviewDialog.value && !payrollReviewDialog.value.open) payrollReviewDialog.value.showModal();
});
watch(benefitReviewOpen, async (open) => {
    if (!open) return;
    await nextTick();
    if (benefitReviewDialog.value && !benefitReviewDialog.value.open) benefitReviewDialog.value.showModal();
});
watch(benefitEndOpen, async (open) => {
    if (!open) return;
    await nextTick();
    if (benefitEndDialog.value && !benefitEndDialog.value.open) benefitEndDialog.value.showModal();
});
watch(settlementReviewOpen, async (open) => {
    if (!open) return;
    await nextTick();
    if (settlementReviewDialog.value && !settlementReviewDialog.value.open) settlementReviewDialog.value.showModal();
});
watch(trainingTransitionOpen, async (open) => {
    if (!open) return;
    await nextTick();
    if (trainingTransitionDialog.value && !trainingTransitionDialog.value.open) trainingTransitionDialog.value.showModal();
});
watch(benefitEnrollmentEmployees, (employees) => {
    if (!employees.some((employee) => String(employee.id) === String(benefitEnrollmentForm.value.employee_id))) {
        benefitEnrollmentForm.value.employee_id = String(employees[0]?.id || '');
    }
});
watch(visibleBenefitEnrollments, (enrollments) => {
    if (!enrollments.some((enrollment) => enrollment.id === selectedBenefitEnrollmentId.value)) {
        selectedBenefitEnrollmentId.value = enrollments[0]?.id || null;
    }
});
watch(performanceReviewOpen, async (open) => {
    if (!open) return;
    await nextTick();
    if (performanceReviewDialog.value && !performanceReviewDialog.value.open) performanceReviewDialog.value.showModal();
});
watch(expenseReviewOpen, async (open) => {
    if (!open) return;
    await nextTick();
    if (expenseReviewDialog.value && !expenseReviewDialog.value.open) expenseReviewDialog.value.showModal();
});
watch(loanReviewOpen, async (open) => {
    if (!open) return;
    await nextTick();
    if (loanReviewDialog.value && !loanReviewDialog.value.open) loanReviewDialog.value.showModal();
});
watch(travelReviewOpen, async (open) => {
    if (!open) return;
    await nextTick();
    if (travelReviewDialog.value && !travelReviewDialog.value.open) travelReviewDialog.value.showModal();
});
watch([() => performanceCycleForm.value.branch_id, performanceCycleEmployees], () => {
    const eligibleIds = new Set(performanceCycleEmployees.value.map((employee) => employee.id));
    performanceCycleForm.value.employee_ids = performanceCycleForm.value.employee_ids.filter((id) => eligibleIds.has(id));
});
watch(visiblePerformanceReviews, (reviews) => {
    if (!reviews.some((review) => review.id === selectedPerformanceReviewId.value)) {
        selectedPerformanceReviewId.value = reviews[0]?.id || null;
    }
});
watch(trainingEnrollmentEmployees, (employees) => {
    if (!employees.some((employee) => String(employee.id) === String(trainingEnrollmentForm.value.employee_id))) {
        trainingEnrollmentForm.value.employee_id = String(employees[0]?.id || '');
    }
});
watch(visibleTrainingEnrollments, (enrollments) => {
    if (!enrollments.some((enrollment) => enrollment.id === selectedTrainingEnrollmentId.value)) {
        selectedTrainingEnrollmentId.value = enrollments[0]?.id || null;
    }
});
const recruitmentStages = [
    { id: 'new', label: 'New', icon: '✳' },
    { id: 'screening', label: 'Screening', icon: '⌕' },
    { id: 'interview', label: 'Interview', icon: '◷' },
    { id: 'offer', label: 'Offer', icon: '◇' },
    { id: 'hired', label: 'Hired', icon: '✓' },
    { id: 'rejected', label: 'Declined', icon: '−' },
];
const filteredRecruitmentCandidates = computed(() => {
    const query = recruitmentSearch.value.trim().toLowerCase();
    return recruitment.value.candidates.filter((candidate) => {
        const matchesSearch = !query || [
            candidate.first_name, candidate.last_name, candidate.email, candidate.phone,
            candidate.source, candidate.requisition?.title, candidate.requisition?.code,
        ].some((value) => String(value || '').toLowerCase().includes(query));
        const matchesStage = recruitmentStageFilter.value === 'all'
            || candidate.stage === recruitmentStageFilter.value;
        const matchesRequisition = recruitmentRequestFilter.value === 'all'
            || String(candidate.requisition_id) === recruitmentRequestFilter.value;
        return matchesSearch && matchesStage && matchesRequisition;
    });
});
const filteredRequisitions = computed(() => {
    const query = recruitmentSearch.value.trim().toLowerCase();
    return recruitment.value.requisitions.filter((requisition) =>
        !query || [
            requisition.code, requisition.title, requisition.department?.name,
            requisition.branch?.name, requisition.location, requisition.status,
        ].some((value) => String(value || '').toLowerCase().includes(query)));
});
const openRecruitmentRequisitions = computed(() => recruitment.value.requisitions
    .filter((requisition) => requisition.status === 'open'));
const filteredDepartments = computed(() => {
    const query = organizationSearch.value.trim().toLowerCase();
    return organization.value.departments.filter((department) =>
        !query || [department.name, department.code, department.cost_center, department.branch?.name, department.manager?.first_name, department.manager?.last_name]
            .some((value) => String(value || '').toLowerCase().includes(query)));
});
const filteredPositions = computed(() => {
    const query = organizationSearch.value.trim().toLowerCase();
    return organization.value.positions.filter((position) =>
        !query || [position.title, position.code, position.grade, position.department?.name, position.branch?.name]
            .some((value) => String(value || '').toLowerCase().includes(query)));
});
const organizationTreeRows = computed(() => {
    const staff = organization.value.employees;
    const byId = new Map(staff.map((employee) => [employee.id, employee]));
    const children = new Map();
    staff.forEach((employee) => {
        const key = byId.has(employee.manager_id) ? employee.manager_id : null;
        if (!children.has(key)) children.set(key, []);
        children.get(key).push(employee);
    });
    const rows = [];
    const visited = new Set();
    const append = (employee, depth) => {
        if (visited.has(employee.id)) return;
        visited.add(employee.id);
        const reports = children.get(employee.id) || [];
        rows.push({ ...employee, depth, reports_count: reports.length });
        reports.forEach((report) => append(report, depth + 1));
    };
    (children.get(null) || []).forEach((employee) => append(employee, 0));
    staff.filter((employee) => !visited.has(employee.id)).forEach((employee) => append(employee, 0));
    return rows;
});
const filteredEmployees = computed(() => {
    const query = employeeSearch.value.trim().toLowerCase();
    if (!query) return employees.value;
    return employees.value.filter((employee) =>
        [employee.first_name, employee.last_name, employee.employee_code, employee.department, employee.job_title]
            .some((value) => String(value || '').toLowerCase().includes(query)));
});
const employeeAttendance = computed(() => {
    return activeSlug.value === 'employee-self-service'
        ? selfServiceData.value.today_attendance
        : attendanceToday.value;
});
const groupedModules = computed(() => {
    const visible = modules.value.filter((module) => {
        if (['recruitment', 'onboarding-offboarding'].includes(module.slug) && !canManageOrganization.value) return false;
        if (['payroll', 'benefits', 'separation-settlement'].includes(module.slug) && !canManageOrganization.value) return false;
        if (['admin', 'hr', 'manager'].includes(user.value?.role)) return true;
        return ['dashboard', 'employees', 'attendance', 'leave', 'shifts-rosters', 'employee-self-service', 'documents', 'performance', 'training', 'expenses', 'travel', 'helpdesk'].includes(module.slug);
    });
    const groups = {};
    for (const module of visible) (groups[module.area] ||= []).push(module);
    return groups;
});

function message(text, isError = false) {
    error.value = isError ? text : '';
    notice.value = isError ? '' : text;
    window.setTimeout(() => { notice.value = ''; error.value = ''; }, 4500);
}

function payrollItemChanged(item) {
    const draft = payrollItemDrafts.value[item.id];
    if (!draft) return false;
    return String(draft.base_pay ?? '') !== String(item.base_pay ?? '')
        || String(draft.other_earnings ?? '') !== String(item.other_earnings ?? '')
        || String(draft.manual_deductions ?? '') !== String(item.manual_deductions ?? '')
        || String(draft.notes ?? '') !== String(item.notes ?? '');
}

function navigate(slug) {
    activeSlug.value = slug;
    history.pushState({}, '', `/app/${slug}`);
    error.value = '';
    notice.value = '';
    loadActive();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function searchWorkspace() {
    const query = employeeSearch.value.trim().toLowerCase();
    if (!query) return;
    const match = modules.value.find((module) => module.name.toLowerCase().includes(query)
        || module.slug.includes(query.replaceAll(' ', '-')));
    navigate(match?.slug || 'employees');
}

function syncLocation() {
    const match = window.location.pathname.match(/^\/app\/([^/]+)/);
    activeSlug.value = match?.[1] || 'dashboard';
    if (user.value) loadActive();
}

async function loadShell() {
    loading.value = true;
    try {
        const result = await api('/me');
        user.value = result.user;
        const catalog = await api('/modules');
        modules.value = catalog.modules;
        syncLocation();
        await loadActive();
    } catch (requestError) {
        user.value = null;
        if (requestError.message !== 'Unauthenticated.') error.value = requestError.message;
    } finally {
        loading.value = false;
    }
}

async function loadActive() {
    if (!user.value) return;
    if (activeSlug.value === 'recruitment') {
        window.clearTimeout(recruitmentFilterTimer);
        recruitmentFilterTimer = null;
    }
    busy.value = true;
    try {
        if (activeSlug.value === 'dashboard') dashboard.value = await api('/dashboard');
        if (activeSlug.value === 'employees') {
            const result = await api(`/employees${employeeSearch.value ? `?search=${encodeURIComponent(employeeSearch.value)}` : ''}`);
            employees.value = result.data;
        }
        if (activeSlug.value === 'attendance') await loadAttendanceReport();
        if (activeSlug.value === 'employee-self-service') {
            selfServiceData.value = await api('/self-service');
            selfServiceProfileForm.value = {
                work_email: selfServiceData.value.employee?.work_email || '',
                phone: selfServiceData.value.employee?.phone || '',
            };
            if (!selfServiceData.value.leave_types.some((type) => String(type.id) === String(leaveForm.value.leave_type_id))) {
                leaveForm.value.leave_type_id = selfServiceData.value.leave_types[0]?.id || '';
            }
        }
        if (activeSlug.value === 'manager-self-service' && user.value.role === 'manager') {
            const today = new Date();
            const from = new Date(today);
            from.setDate(from.getDate() - 6);
            const [employeeResult, leaveResult, attendanceResult] = await Promise.all([
                api('/employees?page=1'),
                api('/leave'),
                api(`/attendance/report?from=${localDateInput(from)}&to=${localDateInput(today)}&per_page=500`),
            ]);
            const [employeePages, attendancePages] = await Promise.all([
                Promise.all(Array.from({ length: Math.max(0, employeeResult.last_page - 1) }, (_, index) =>
                    api(`/employees?page=${index + 2}`))),
                Promise.all(Array.from({ length: Math.max(0, attendanceResult.meta.last_page - 1) }, (_, index) =>
                    api(`/attendance/report?from=${localDateInput(from)}&to=${localDateInput(today)}&per_page=500&page=${index + 2}`))),
            ]);
            managerServiceData.value = {
                employees: [employeeResult, ...employeePages].flatMap((page) => page.data),
                leave_requests: leaveResult.requests,
                attendance: [attendanceResult, ...attendancePages].flatMap((page) => page.data),
            };
        }
        if (activeSlug.value === 'expenses') expenseData.value = await api('/expenses');
        if (activeSlug.value === 'loans-advances') loanData.value = await api('/loans-advances');
        if (activeSlug.value === 'travel') travelData.value = await api('/travel');
        if (activeSlug.value === 'disciplinary' && canManageOrganization.value) {
            disciplinaryData.value = await api('/disciplinary');
            if (!disciplinaryForm.value.employee_id && disciplinaryData.value.employees.length) {
                disciplinaryForm.value.employee_id = String(disciplinaryData.value.employees[0].id);
            }
            if (!disciplinaryActionForm.value.investigator_id && disciplinaryData.value.investigators.length) {
                disciplinaryActionForm.value.investigator_id = String(disciplinaryData.value.investigators[0].id);
            }
        }
        if (activeSlug.value === 'health-safety' && canManageOrganization.value) {
            safetyData.value = await api('/health-safety');
            if (!safetyForm.value.employee_id && safetyData.value.employees.length) {
                safetyForm.value.employee_id = String(safetyData.value.employees[0].id);
            }
            if (!safetyActionForm.value.assigned_to && safetyData.value.assignees.length) {
                safetyActionForm.value.assigned_to = String(safetyData.value.assignees[0].id);
            }
        }
        if (activeSlug.value === 'shifts-rosters') {
            shiftRoster.value = await api(shiftRosterApiPath());
            if (!rosterAssignmentForm.value.employee_id && shiftRoster.value.employees.length) {
                rosterAssignmentForm.value.employee_id = String(shiftRoster.value.employees[0].id);
            }
            if (!rosterAssignmentForm.value.shift_pattern_id && activeShiftPatterns.value.length) {
                rosterAssignmentForm.value.shift_pattern_id = String(activeShiftPatterns.value[0].id);
            }
        }
        if (activeSlug.value === 'payroll' && canManageOrganization.value) {
            payrollData.value = await api('/payroll');
            if (!payrollData.value.cycles.some((cycle) => cycle.id === selectedPayrollCycleId.value)) {
                selectedPayrollCycleId.value = payrollData.value.cycles[0]?.id || null;
            }
            payrollItemDrafts.value = Object.fromEntries(payrollData.value.cycles.flatMap((cycle) =>
                cycle.items.map((item) => [item.id, {
                    base_pay: item.base_pay ?? '',
                    other_earnings: item.other_earnings ?? '',
                    manual_deductions: item.manual_deductions ?? '',
                    notes: item.notes || '',
                }])));
            payrollItemErrors.value = {};
        }
        if (activeSlug.value === 'benefits' && canManageOrganization.value) {
            benefitsData.value = await api('/benefits');
            benefitContributionDrafts.value = Object.fromEntries(benefitsData.value.enrollments.map((enrollment) => [
                enrollment.id,
                {
                    employee_contribution: enrollment.employee_contribution ?? '',
                    employer_contribution: enrollment.employer_contribution ?? '',
                    effective_start: enrollment.effective_start,
                    effective_end: enrollment.effective_end || '',
                    notes: enrollment.notes || '',
                },
            ]));
            benefitContributionErrors.value = {};
            if (!benefitsData.value.plans.some((plan) => String(plan.id) === String(benefitEnrollmentForm.value.benefit_plan_id))) {
                benefitEnrollmentForm.value.benefit_plan_id = String(benefitsData.value.plans.find((plan) => plan.status === 'active')?.id || '');
            }
            if (!benefitsData.value.employees.some((employee) => String(employee.id) === String(benefitEnrollmentForm.value.employee_id))) {
                benefitEnrollmentForm.value.employee_id = String(benefitEnrollmentEmployees.value[0]?.id || '');
            }
            if (!benefitsData.value.enrollments.some((enrollment) => enrollment.id === selectedBenefitEnrollmentId.value)) {
                selectedBenefitEnrollmentId.value = benefitsData.value.enrollments[0]?.id || null;
            }
        }
        if (activeSlug.value === 'separation-settlement' && canManageOrganization.value) {
            separationData.value = await api('/separations');
            settlementItemDrafts.value = Object.fromEntries(separationData.value.settlements.map((settlement) => [
                settlement.id,
                settlement.items.map((item) => ({
                    category: item.category,
                    description: item.description,
                    amount: item.amount,
                    notes: item.notes || '',
                })),
            ]));
            settlementItemError.value = '';
            if (!separationData.value.settlements.some((settlement) => settlement.id === selectedSettlementId.value)) {
                selectedSettlementId.value = separationData.value.settlements[0]?.id || null;
            }
            if (!separationData.value.offboarding_cases.some((caseItem) => String(caseItem.id) === String(settlementForm.value.lifecycle_case_id))) {
                settlementForm.value.lifecycle_case_id = String(eligibleSettlementCases.value[0]?.id || '');
            }
        }
        if (activeSlug.value === 'performance' && canManageOrganization.value) {
            performanceData.value = await api('/performance');
            performanceReviewDrafts.value = Object.fromEntries(performanceData.value.reviews.map((review) => [
                review.id,
                {
                    rating: review.rating ?? '',
                    goals: review.goals || '',
                    results: review.results || '',
                    strengths: review.strengths || '',
                    development_focus: review.development_focus || '',
                },
            ]));
            performanceReviewErrors.value = {};
            if (!performanceData.value.cycles.some((cycle) => cycle.id === selectedPerformanceCycleId.value)) {
                selectedPerformanceCycleId.value = null;
            }
            if (!performanceData.value.reviews.some((review) => review.id === selectedPerformanceReviewId.value)) {
                selectedPerformanceReviewId.value = performanceData.value.reviews[0]?.id || null;
            }
            if (!performanceData.value.employees.some((employee) => String(employee.id) === String(performanceCycleForm.value.employee_ids[0]))) {
                performanceCycleForm.value.employee_ids = [];
            }
        }
        if (activeSlug.value === 'training' && canManageOrganization.value) {
            trainingData.value = await api('/training');
            trainingEnrollmentDrafts.value = Object.fromEntries(trainingData.value.enrollments.map((enrollment) => [
                enrollment.id,
                { status: enrollment.status, notes: enrollment.notes || '' },
            ]));
            if (!trainingData.value.programs.some((program) => program.id === selectedTrainingProgramId.value)) {
                selectedTrainingProgramId.value = trainingData.value.programs[0]?.id || null;
            }
            if (!trainingData.value.enrollments.some((enrollment) => enrollment.id === selectedTrainingEnrollmentId.value)) {
                selectedTrainingEnrollmentId.value = trainingData.value.enrollments[0]?.id || null;
            }
            if (!trainingData.value.programs.some((program) => String(program.id) === String(trainingEnrollmentForm.value.training_program_id))) {
                trainingEnrollmentForm.value.training_program_id = String(trainingData.value.programs.find((program) => program.status === 'active')?.id || '');
            }
            if (!trainingData.value.employees.some((employee) => String(employee.id) === String(trainingEnrollmentForm.value.employee_id))) {
                trainingEnrollmentForm.value.employee_id = String(trainingEnrollmentEmployees.value[0]?.id || '');
            }
        }
        if (activeSlug.value === 'leave') leaveData.value = await api('/leave');
        if (activeSlug.value === 'organization') {
            organization.value = await api('/organization');
            managerDrafts.value = Object.fromEntries(organization.value.employees.map((employee) => [
                employee.id,
                employee.manager_id || '',
            ]));
            if (!positionForm.value.department_id && organization.value.departments.length) {
                positionForm.value.department_id = String(organization.value.departments[0].id);
            }
        }
        if (activeSlug.value === 'recruitment') {
            recruitment.value = await api(recruitmentApiPath());
            candidateStageDrafts.value = Object.fromEntries(recruitment.value.candidates.map((candidate) => [
                candidate.id,
                candidate.stage,
            ]));
            candidateFeedbackDrafts.value = Object.fromEntries(recruitment.value.candidates.map((candidate) => [
                candidate.id,
                { rating: candidate.rating ?? '', notes: candidate.notes || '' },
            ]));
            if (!candidateForm.value.requisition_id && openRecruitmentRequisitions.value.length) {
                candidateForm.value.requisition_id = String(openRecruitmentRequisitions.value[0].id);
            }
            if (!requisitionForm.value.department_id && recruitment.value.departments.length) {
                requisitionForm.value.department_id = String(recruitment.value.departments[0].id);
            }
        }
        if (activeSlug.value === 'onboarding-offboarding') {
            lifecycle.value = await api('/lifecycle');
            lifecycleTaskDrafts.value = Object.fromEntries(lifecycle.value.cases.flatMap((caseItem) => caseItem.tasks.map((task) => [
                task.id,
                { status: task.status, notes: task.notes || '' },
            ])));
            lifecycleTaskErrors.value = {};
            if (!lifecycle.value.cases.some((caseItem) => caseItem.id === selectedLifecycleCaseId.value)) {
                selectedLifecycleCaseId.value = lifecycle.value.cases[0]?.id || null;
            }
            if (!lifecycleForm.value.employee_id && availableLifecycleEmployees.value.length) {
                lifecycleForm.value.employee_id = String(availableLifecycleEmployees.value[0].id);
            }
        }
    } catch (requestError) {
        message(requestError.message, true);
    } finally {
        busy.value = false;
    }
}

function recruitmentApiPath(page = 1) {
    const params = new URLSearchParams();
    const search = recruitmentSearch.value.trim();
    if (search) params.set('search', search);
    if (recruitmentStageFilter.value !== 'all') params.set('stage', recruitmentStageFilter.value);
    if (recruitmentRequestFilter.value !== 'all') params.set('requisition_id', recruitmentRequestFilter.value);
    params.set('page', String(page));
    return `/recruitment?${params.toString()}`;
}

function attendanceApiPath(page = 1, perPage = null) {
    const params = new URLSearchParams({
        from: attendanceFrom.value,
        to: attendanceTo.value,
        page: String(page),
    });
    if (attendanceSearch.value.trim()) params.set('search', attendanceSearch.value.trim());
    if (attendanceStatusFilter.value !== 'all') params.set('status', attendanceStatusFilter.value);
    if (perPage) params.set('per_page', String(perPage));
    return `/attendance/report?${params.toString()}`;
}

function shiftRosterApiPath() {
    const end = new Date(`${rosterWeekStart.value}T12:00:00`);
    end.setDate(end.getDate() + 6);
    const params = new URLSearchParams({
        from: rosterWeekStart.value,
        to: localDateInput(end),
    });
    return `/shifts-rosters?${params.toString()}`;
}

async function loadAttendanceReport(append = false, page = 1) {
    const result = await api(attendanceApiPath(page));
    attendance.value = append ? [...attendance.value, ...result.data] : result.data;
    attendanceReport.value = { meta: result.meta, metrics: result.metrics, trend: result.trend };
    attendanceToday.value = result.today;
}

async function loadShiftRoster() {
    shiftRoster.value = await api(shiftRosterApiPath());
}

function moveRosterWeek(weeks) {
    const date = new Date(`${rosterWeekStart.value}T12:00:00`);
    date.setDate(date.getDate() + weeks * 7);
    rosterWeekStart.value = localDateInput(date);
    loadActive();
}

function goToCurrentRosterWeek() {
    rosterWeekStart.value = currentWeekStart();
    loadActive();
}

function openRosterAssignmentForm() {
    rosterFormError.value = '';
    const today = localDateInput(new Date());
    const firstFutureDay = rosterWeekDays.value.find((date) => date >= today);
    rosterAssignmentForm.value = {
        employee_id: shiftRoster.value.employees[0]?.id?.toString() || '',
        shift_pattern_id: activeShiftPatterns.value[0]?.id?.toString() || '',
        shift_date: firstFutureDay || today,
        notes: '',
    };
    showRosterAssignmentForm.value = true;
    rosterTab.value = 'week';
}

function closeRosterAssignmentForm() {
    showRosterAssignmentForm.value = false;
    rosterFormError.value = '';
}

async function saveShiftPattern() {
    shiftPatternFormError.value = '';
    busy.value = true;
    try {
        await api('/shifts-rosters/patterns', {
            method: 'POST',
            body: JSON.stringify({
                ...shiftPatternForm.value,
                branch_id: shiftPatternForm.value.branch_id ? Number(shiftPatternForm.value.branch_id) : null,
                break_minutes: Number(shiftPatternForm.value.break_minutes),
            }),
        });
        showShiftPatternForm.value = false;
        shiftPatternForm.value = {
            name: '', code: '', branch_id: '', start_time: '09:00', end_time: '17:00', break_minutes: 60,
        };
        message('Shift pattern created.');
        await loadActive();
    } catch (requestError) {
        shiftPatternFormError.value = requestError.message;
    } finally {
        busy.value = false;
    }
}

async function toggleShiftPattern(pattern) {
    busy.value = true;
    try {
        await api(`/shifts-rosters/patterns/${pattern.id}`, {
            method: 'PATCH',
            body: JSON.stringify({ status: pattern.status === 'active' ? 'inactive' : 'active' }),
        });
        message(pattern.status === 'active' ? 'Shift pattern archived.' : 'Shift pattern activated.');
        await loadActive();
    } catch (requestError) {
        message(requestError.message, true);
    } finally {
        busy.value = false;
    }
}

async function saveRosterAssignment() {
    rosterFormError.value = '';
    busy.value = true;
    try {
        await api('/shifts-rosters/assignments', {
            method: 'POST',
            body: JSON.stringify({
                ...rosterAssignmentForm.value,
                employee_id: Number(rosterAssignmentForm.value.employee_id),
                shift_pattern_id: Number(rosterAssignmentForm.value.shift_pattern_id),
            }),
        });
        closeRosterAssignmentForm();
        message('Roster assignment saved.');
        await loadActive();
    } catch (requestError) {
        rosterFormError.value = requestError.message;
    } finally {
        busy.value = false;
    }
}

async function removeRosterAssignment(assignment) {
    busy.value = true;
    try {
        await api(`/shifts-rosters/assignments/${assignment.id}`, { method: 'DELETE' });
        message(`Removed ${fullName(assignment.employee)} from the roster.`);
        await loadActive();
    } catch (requestError) {
        message(requestError.message, true);
    } finally {
        busy.value = false;
    }
}

function exportRosterCsv() {
    const headers = ['Date', 'Employee code', 'Employee', 'Department', 'Branch', 'Shift', 'Start', 'End', 'Break minutes', 'Notes'];
    const quote = (value) => {
        let text = String(value ?? '');
        if (/^[\t\r\n ]*[=+\-@]/.test(text)) text = `'${text}`;
        return `"${text.replaceAll('"', '""')}"`;
    };
    const rows = [
        headers,
        ...filteredRosterAssignments.value.map((assignment) => [
            assignment.shift_date,
            assignment.employee?.employee_code,
            fullName(assignment.employee),
            assignment.employee?.department,
            assignment.employee?.branch?.name,
            assignment.shift_pattern?.name,
            assignment.shift_pattern?.start_time,
            assignment.shift_pattern?.end_time,
            assignment.shift_pattern?.break_minutes,
            assignment.notes,
        ]),
    ];
    const csv = rows.map((row) => row.map(quote).join(',')).join('\r\n');
    const url = URL.createObjectURL(new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = `northstar-roster-${rosterWeekStart.value}.csv`;
    link.click();
    URL.revokeObjectURL(url);
    message(`Exported ${filteredRosterAssignments.value.length} roster assignments.`);
}

function openPayrollCycleForm() {
    payrollFormError.value = '';
    payrollEmployeeSearch.value = '';
    payrollCycleForm.value = {
        period_start: currentMonthStart(),
        period_end: localDateInput(new Date()),
        pay_date: localDateInput(new Date()),
        currency: ['AED', 'INR', 'SAR'].includes(user.value?.company?.currency)
            ? user.value.company.currency
            : 'AED',
        branch_id: user.value?.branch?.id ? String(user.value.branch.id) : '',
        employee_ids: [],
    };
    showPayrollCycleForm.value = true;
    payrollTab.value = 'create';
}

function togglePayrollEmployee(employeeId) {
    const selected = new Set(payrollCycleForm.value.employee_ids);
    if (selected.has(employeeId)) selected.delete(employeeId);
    else selected.add(employeeId);
    payrollCycleForm.value.employee_ids = [...selected];
}

function toggleAllPayrollEmployees() {
    const eligibleIds = eligiblePayrollEmployees.value.map((employee) => employee.id);
    const selected = new Set(payrollCycleForm.value.employee_ids);
    const allSelected = eligibleIds.length && eligibleIds.every((id) => selected.has(id));
    payrollCycleForm.value.employee_ids = allSelected
        ? [...selected].filter((id) => !eligibleIds.includes(id))
        : [...new Set([...selected, ...eligibleIds])];
}

async function createPayrollCycle() {
    payrollFormError.value = '';
    busy.value = true;
    try {
        const result = await api('/payroll/cycles', {
            method: 'POST',
            body: JSON.stringify({
                ...payrollCycleForm.value,
                branch_id: payrollCycleForm.value.branch_id ? Number(payrollCycleForm.value.branch_id) : null,
                employee_ids: payrollCycleForm.value.employee_ids,
            }),
        });
        showPayrollCycleForm.value = false;
        selectedPayrollCycleId.value = result.cycle.id;
        payrollTab.value = 'detail';
        message('Payroll cycle created as a draft. Enter and verify each employee amount.');
        await loadActive();
    } catch (requestError) {
        payrollFormError.value = requestError.message;
    } finally {
        busy.value = false;
    }
}

function selectPayrollCycle(cycle) {
    selectedPayrollCycleId.value = cycle.id;
    payrollTab.value = 'detail';
    payrollSearch.value = '';
    payrollReviewNote.value = '';
}

async function savePayrollItem(item) {
    payrollItemErrors.value = { ...payrollItemErrors.value, [item.id]: '' };
    busy.value = true;
    try {
        const draft = payrollItemDrafts.value[item.id];
        await api(`/payroll/items/${item.id}`, {
            method: 'PATCH',
            body: JSON.stringify({
                base_pay: draft.base_pay,
                other_earnings: draft.other_earnings,
                manual_deductions: draft.manual_deductions,
                notes: draft.notes,
            }),
        });
        message(`Saved payroll inputs for ${item.employee_name}.`);
        await loadActive();
    } catch (requestError) {
        payrollItemErrors.value = { ...payrollItemErrors.value, [item.id]: requestError.message };
    } finally {
        busy.value = false;
    }
}

async function submitPayrollCycle() {
    if (!selectedPayrollCycle.value) return;
    busy.value = true;
    try {
        await api(`/payroll/cycles/${selectedPayrollCycle.value.id}/submit`, {
            method: 'POST',
            body: '{}',
        });
        payrollReviewNote.value = '';
        message('Payroll cycle submitted for review. Inputs are now locked.');
        await loadActive();
    } catch (requestError) {
        message(requestError.message, true);
    } finally {
        busy.value = false;
    }
}

async function reviewPayrollCycle(decision) {
    if (!selectedPayrollCycle.value) return;
    payrollReviewError.value = '';
    busy.value = true;
    try {
        await api(`/payroll/cycles/${selectedPayrollCycle.value.id}/review`, {
            method: 'POST',
            body: JSON.stringify({
                decision,
                review_note: payrollReviewNote.value.trim() || null,
            }),
        });
        message(decision === 'approve' ? 'Payroll cycle approved. No payment was initiated.' : 'Payroll cycle returned for changes.');
        payrollReviewNote.value = '';
        payrollReviewDialog.value?.close();
        await loadActive();
    } catch (requestError) {
        payrollReviewError.value = requestError.message;
    } finally {
        busy.value = false;
    }
}

function openPayrollReview(decision) {
    payrollReviewDecision.value = decision;
    payrollReviewAcknowledged.value = false;
    payrollReviewError.value = '';
    payrollReviewOpen.value = true;
}

function closePayrollReview() {
    payrollReviewOpen.value = false;
    payrollReviewAcknowledged.value = false;
    payrollReviewError.value = '';
}

function formatPayrollMoney(value, currency) {
    if (value === null || value === undefined || value === '') return '—';
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value));
}

function exportPayrollWorkpaper() {
    if (!selectedPayrollCycle.value) return;
    payrollExporting.value = true;
    try {
        const cycle = selectedPayrollCycle.value;
        const headers = [
            'Employee code', 'Employee', 'Department', 'Currency', 'Base pay',
            'Other earnings (manual)', 'Manual deductions (not statutory calculation)', 'Gross arithmetic total',
            'Net arithmetic total', 'Notes',
        ];
        const quote = (value) => {
            let text = String(value ?? '');
            if (/^[\t\r\n ]*[=+\-@]/.test(text)) text = `'${text}`;
            return `"${text.replaceAll('"', '""')}"`;
        };
        const rows = [
            headers,
            ...cycle.items.map((item) => [
                item.employee_code,
                item.employee_name,
                item.department,
                cycle.currency,
                item.base_pay,
                item.other_earnings,
                item.manual_deductions,
                item.gross_pay,
                item.net_pay,
                item.notes,
            ]),
        ];
        const csv = rows.map((row) => row.map(quote).join(',')).join('\r\n');
        const url = URL.createObjectURL(new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8' }));
        const link = document.createElement('a');
        link.href = url;
        link.download = `payroll-workpaper-${cycle.currency}-${cycle.period_start}-${cycle.period_end}.csv`;
        link.click();
        URL.revokeObjectURL(url);
        message('Exported the payroll workpaper. It is not a bank or statutory file.');
    } catch (requestError) {
        message(requestError.message, true);
    } finally {
        payrollExporting.value = false;
    }
}

function openPerformanceCycleForm() {
    performanceCycleError.value = '';
    performanceCycleForm.value = {
        title: '', period_start: currentMonthStart(), period_end: localDateInput(new Date()),
        due_date: '', branch_id: user.value?.branch_id ? String(user.value.branch_id) : '',
        description: '', employee_ids: [],
    };
    showPerformanceCycleForm.value = true;
    performanceTab.value = 'cycles';
}

function togglePerformanceEmployee(employeeId) {
    const employeeIds = performanceCycleForm.value.employee_ids;
    performanceCycleForm.value.employee_ids = employeeIds.includes(employeeId)
        ? employeeIds.filter((id) => id !== employeeId)
        : [...employeeIds, employeeId];
}

function toggleAllPerformanceEmployees() {
    const allIds = performanceCycleEmployees.value.map((employee) => employee.id);
    performanceCycleForm.value.employee_ids = allIds.length
        && allIds.every((id) => performanceCycleForm.value.employee_ids.includes(id))
        ? []
        : allIds;
}

function openPerformanceCycle(cycle) {
    selectedPerformanceCycleId.value = cycle.id;
    performanceStatusFilter.value = 'all';
    performanceTab.value = 'reviews';
}

async function createPerformanceCycle() {
    performanceCycleError.value = '';
    busy.value = true;
    try {
        const result = await api('/performance/cycles', {
            method: 'POST',
            body: JSON.stringify({
                ...performanceCycleForm.value,
                branch_id: performanceCycleForm.value.branch_id ? Number(performanceCycleForm.value.branch_id) : null,
                due_date: performanceCycleForm.value.due_date || null,
                employee_ids: performanceCycleForm.value.employee_ids,
            }),
        });
        showPerformanceCycleForm.value = false;
        selectedPerformanceCycleId.value = result.cycle.id;
        performanceTab.value = 'reviews';
        performanceStatusFilter.value = 'all';
        message('Performance cycle created with individual draft reviews.');
        await loadActive();
    } catch (requestError) {
        performanceCycleError.value = requestError.message;
    } finally {
        busy.value = false;
    }
}

async function updatePerformanceCycleStatus(cycle, status) {
    busy.value = true;
    try {
        await api(`/performance/cycles/${cycle.id}/status`, {
            method: 'PATCH',
            body: JSON.stringify({ status }),
        });
        message(status === 'closed' ? 'Performance cycle closed.' : 'Performance cycle reopened.');
        await loadActive();
    } catch (requestError) {
        message(requestError.message, true);
    } finally {
        busy.value = false;
    }
}

function performanceReviewChanged(review) {
    const draft = performanceReviewDrafts.value[review.id];
    if (!draft) return false;
    return String(draft.rating ?? '') !== String(review.rating ?? '')
        || String(draft.goals || '') !== String(review.goals || '')
        || String(draft.results || '') !== String(review.results || '')
        || String(draft.strengths || '') !== String(review.strengths || '')
        || String(draft.development_focus || '') !== String(review.development_focus || '');
}

async function savePerformanceReview(review) {
    performanceReviewErrors.value = { ...performanceReviewErrors.value, [review.id]: '' };
    busy.value = true;
    try {
        const draft = performanceReviewDrafts.value[review.id];
        await api(`/performance/reviews/${review.id}`, {
            method: 'PATCH',
            body: JSON.stringify({ ...draft, rating: draft.rating === '' ? null : Number(draft.rating) }),
        });
        message(`Saved the review draft for ${review.employee_name}.`);
        await loadActive();
    } catch (requestError) {
        performanceReviewErrors.value = { ...performanceReviewErrors.value, [review.id]: requestError.message };
    } finally {
        busy.value = false;
    }
}

async function submitPerformanceReview(review) {
    performanceReviewErrors.value = { ...performanceReviewErrors.value, [review.id]: '' };
    busy.value = true;
    try {
        await api(`/performance/reviews/${review.id}/submit`, { method: 'POST', body: '{}' });
        message(`Review for ${review.employee_name} submitted for approval.`);
        await loadActive();
    } catch (requestError) {
        performanceReviewErrors.value = { ...performanceReviewErrors.value, [review.id]: requestError.message };
    } finally {
        busy.value = false;
    }
}

function openPerformanceReviewDecision(review, decision) {
    selectedPerformanceReviewId.value = review.id;
    performanceReviewDecision.value = decision;
    performanceReviewAcknowledged.value = false;
    performanceReviewNote.value = '';
    performanceReviewError.value = '';
    performanceReviewOpen.value = true;
}

async function decidePerformanceReview() {
    const review = selectedPerformanceReview.value;
    if (!review) return;
    performanceReviewError.value = '';
    busy.value = true;
    try {
        await api(`/performance/reviews/${review.id}/review`, {
            method: 'POST',
            body: JSON.stringify({
                decision: performanceReviewDecision.value,
                review_note: performanceReviewNote.value.trim() || null,
            }),
        });
        performanceReviewOpen.value = false;
        message(performanceReviewDecision.value === 'approve'
            ? 'Performance review approved and recorded.'
            : 'Performance review returned with requested changes.');
        await loadActive();
    } catch (requestError) {
        performanceReviewError.value = requestError.message;
    } finally {
        busy.value = false;
    }
}

function exportPerformanceReviews() {
    performanceExporting.value = true;
    try {
        downloadCsv([
            ['Cycle', 'Period start', 'Period end', 'Due date', 'Employee code', 'Employee', 'Department', 'Manual rating (1-5)', 'Goals', 'Results', 'Strengths', 'Development focus', 'Status', 'Review note'],
            ...visiblePerformanceReviews.value.map((review) => [
                review.cycle?.title, review.cycle?.period_start, review.cycle?.period_end,
                review.cycle?.due_date, review.employee_code, review.employee_name,
                review.department, review.rating, review.goals, review.results,
                review.strengths, review.development_focus, review.status, review.review_note,
            ]),
        ], `performance-reviews-${localDateInput(new Date())}.csv`);
        message(`Exported ${visiblePerformanceReviews.value.length} performance review records.`);
    } catch (requestError) {
        message(requestError.message, true);
    } finally {
        performanceExporting.value = false;
    }
}

function openTrainingProgramForm(program = null) {
    editingTrainingProgramId.value = program?.id || null;
    trainingProgramError.value = '';
    trainingProgramForm.value = program ? {
        title: program.title,
        category: program.category,
        provider: program.provider || '',
        delivery_mode: program.delivery_mode,
        start_date: program.start_date || '',
        due_date: program.due_date || '',
        capacity: program.capacity ?? '',
        branch_id: program.branch_id ? String(program.branch_id) : '',
        description: program.description || '',
        status: program.status,
    } : {
        title: '', category: 'professional', provider: '', delivery_mode: 'classroom',
        start_date: '', due_date: '', capacity: '',
        branch_id: user.value?.branch_id ? String(user.value.branch_id) : '',
        description: '', status: 'active',
    };
    showTrainingProgramForm.value = true;
    trainingTab.value = 'catalog';
}

async function saveTrainingProgram() {
    trainingProgramError.value = '';
    busy.value = true;
    try {
        const editing = Boolean(editingTrainingProgramId.value);
        const result = await api(editing
            ? `/training/programs/${editingTrainingProgramId.value}`
            : '/training/programs', {
            method: editing ? 'PATCH' : 'POST',
            body: JSON.stringify({
                ...trainingProgramForm.value,
                branch_id: trainingProgramForm.value.branch_id ? Number(trainingProgramForm.value.branch_id) : null,
                start_date: trainingProgramForm.value.start_date || null,
                due_date: trainingProgramForm.value.due_date || null,
                capacity: trainingProgramForm.value.capacity === '' ? null : Number(trainingProgramForm.value.capacity),
            }),
        });
        showTrainingProgramForm.value = false;
        editingTrainingProgramId.value = null;
        selectedTrainingProgramId.value = result.program.id;
        message(editing ? 'Training program settings saved.' : 'Training program created.');
        await loadActive();
    } catch (requestError) {
        trainingProgramError.value = requestError.message;
    } finally {
        busy.value = false;
    }
}

function openTrainingEnrollmentForm(program = null) {
    trainingEnrollmentError.value = '';
    trainingEnrollmentForm.value = {
        training_program_id: String(program?.id || trainingData.value.programs.find((entry) => entry.status === 'active')?.id || ''),
        employee_id: '',
        notes: '',
    };
    trainingEnrollmentForm.value.employee_id = String(trainingEnrollmentEmployees.value[0]?.id || '');
    showTrainingEnrollmentForm.value = true;
    trainingTab.value = 'enrollments';
}

async function createTrainingEnrollment() {
    trainingEnrollmentError.value = '';
    busy.value = true;
    try {
        const result = await api('/training/enrollments', {
            method: 'POST',
            body: JSON.stringify({
                ...trainingEnrollmentForm.value,
                training_program_id: Number(trainingEnrollmentForm.value.training_program_id),
                employee_id: Number(trainingEnrollmentForm.value.employee_id),
            }),
        });
        showTrainingEnrollmentForm.value = false;
        selectedTrainingEnrollmentId.value = result.enrollment.id;
        message(`${result.enrollment.employee_name} assigned to the training program.`);
        await loadActive();
    } catch (requestError) {
        trainingEnrollmentError.value = requestError.message;
    } finally {
        busy.value = false;
    }
}

function trainingEnrollmentChanged(enrollment) {
    const draft = trainingEnrollmentDrafts.value[enrollment.id];
    return Boolean(draft) && (draft.status !== enrollment.status || String(draft.notes || '') !== String(enrollment.notes || ''));
}

function trainingStatusOptions(enrollment) {
    if (enrollment.status === 'assigned') return ['assigned', 'in_progress', 'completed', 'cancelled'];
    if (enrollment.status === 'in_progress') return ['in_progress', 'completed', 'cancelled'];
    return [enrollment.status];
}

async function saveTrainingEnrollment(enrollment, confirmed = false) {
    trainingEnrollmentUpdateError.value = '';
    const nextStatus = trainingEnrollmentDrafts.value[enrollment.id]?.status;
    if (!confirmed && nextStatus !== enrollment.status && ['completed', 'cancelled'].includes(nextStatus)) {
        pendingTrainingTransition.value = enrollment;
        trainingTransitionOpen.value = true;
        return;
    }
    busy.value = true;
    try {
        const result = await api(`/training/enrollments/${enrollment.id}`, {
            method: 'PATCH',
            body: JSON.stringify(trainingEnrollmentDrafts.value[enrollment.id]),
        });
        pendingTrainingTransition.value = null;
        message(`${result.enrollment.employee_name}'s training record updated.`);
        await loadActive();
    } catch (requestError) {
        trainingEnrollmentUpdateError.value = requestError.message;
    } finally {
        busy.value = false;
    }
}

async function confirmTrainingEnrollmentTransition() {
    const enrollment = pendingTrainingTransition.value;
    if (!enrollment) return;
    trainingTransitionDialog.value?.close();
    await saveTrainingEnrollment(enrollment, true);
}

function exportTrainingEnrollments() {
    trainingExporting.value = true;
    try {
        downloadCsv([
            ['Program', 'Category', 'Provider', 'Delivery mode', 'Employee code', 'Employee', 'Department', 'Status', 'Start date', 'Due date', 'Completed at', 'Notes'],
            ...visibleTrainingEnrollments.value.map((enrollment) => [
                enrollment.program?.title, enrollment.program?.category, enrollment.program?.provider,
                enrollment.program?.delivery_mode, enrollment.employee_code, enrollment.employee_name,
                enrollment.department, enrollment.status, enrollment.program?.start_date,
                enrollment.program?.due_date, enrollment.completed_at, enrollment.notes,
            ]),
        ], `training-enrollments-${localDateInput(new Date())}.csv`);
        message(`Exported ${visibleTrainingEnrollments.value.length} training enrollment records.`);
    } catch (requestError) {
        message(requestError.message, true);
    } finally {
        trainingExporting.value = false;
    }
}

function openBenefitPlanForm(plan = null) {
    editingBenefitPlanId.value = plan?.id || null;
    benefitFormError.value = '';
    benefitPlanForm.value = plan ? {
        name: plan.name,
        category: plan.category,
        currency: plan.currency,
        branch_id: plan.branch_id ? String(plan.branch_id) : '',
        provider: plan.provider || '',
        description: plan.description || '',
        eligibility_notes: plan.eligibility_notes || '',
        status: plan.status,
    } : {
        name: '', category: 'allowance',
        currency: ['AED', 'INR', 'SAR'].includes(user.value?.company?.currency) ? user.value.company.currency : 'AED',
        branch_id: user.value?.branch?.id ? String(user.value.branch.id) : '',
        provider: '', description: '', eligibility_notes: '', status: 'active',
    };
    showBenefitPlanForm.value = true;
    benefitsTab.value = 'plans';
}

async function saveBenefitPlan() {
    benefitFormError.value = '';
    busy.value = true;
    try {
        const method = editingBenefitPlanId.value ? 'PATCH' : 'POST';
        const path = editingBenefitPlanId.value
            ? `/benefits/plans/${editingBenefitPlanId.value}`
            : '/benefits/plans';
        await api(path, {
            method,
            body: JSON.stringify({
                ...benefitPlanForm.value,
                branch_id: benefitPlanForm.value.branch_id ? Number(benefitPlanForm.value.branch_id) : null,
            }),
        });
        showBenefitPlanForm.value = false;
        message(editingBenefitPlanId.value ? 'Benefit plan settings saved.' : 'Benefit plan created.');
        editingBenefitPlanId.value = null;
        await loadActive();
    } catch (requestError) {
        benefitFormError.value = requestError.message;
    } finally {
        busy.value = false;
    }
}

function openBenefitEnrollmentForm() {
    benefitEnrollmentError.value = '';
    benefitEnrollmentForm.value = {
        benefit_plan_id: String(benefitsData.value.plans.find((plan) => plan.status === 'active')?.id || ''),
        employee_id: '',
        effective_start: localDateInput(new Date()),
        effective_end: '',
        notes: '',
    };
    benefitEnrollmentForm.value.employee_id = String(benefitEnrollmentEmployees.value[0]?.id || '');
    showBenefitEnrollmentForm.value = true;
    benefitsTab.value = 'enrollments';
}

async function createBenefitEnrollment() {
    benefitEnrollmentError.value = '';
    busy.value = true;
    try {
        const result = await api('/benefits/enrollments', {
            method: 'POST',
            body: JSON.stringify({
                ...benefitEnrollmentForm.value,
                benefit_plan_id: Number(benefitEnrollmentForm.value.benefit_plan_id),
                employee_id: Number(benefitEnrollmentForm.value.employee_id),
                effective_end: benefitEnrollmentForm.value.effective_end || null,
            }),
        });
        showBenefitEnrollmentForm.value = false;
        selectedBenefitEnrollmentId.value = result.enrollment.id;
        message('Enrollment draft created. Enter the manually confirmed contribution amounts.');
        await loadActive();
    } catch (requestError) {
        benefitEnrollmentError.value = requestError.message;
    } finally {
        busy.value = false;
    }
}

async function saveBenefitContribution(enrollment) {
    benefitContributionErrors.value = { ...benefitContributionErrors.value, [enrollment.id]: '' };
    busy.value = true;
    try {
        const draft = benefitContributionDrafts.value[enrollment.id];
        await api(`/benefits/enrollments/${enrollment.id}`, {
            method: 'PATCH',
            body: JSON.stringify({
                employee_contribution: draft.employee_contribution,
                employer_contribution: draft.employer_contribution,
                effective_start: draft.effective_start,
                effective_end: draft.effective_end || null,
                notes: draft.notes,
            }),
        });
        message(`Saved enrollment inputs for ${enrollment.employee_name}.`);
        await loadActive();
    } catch (requestError) {
        benefitContributionErrors.value = { ...benefitContributionErrors.value, [enrollment.id]: requestError.message };
    } finally {
        busy.value = false;
    }
}

function benefitContributionChanged(enrollment) {
    const draft = benefitContributionDrafts.value[enrollment.id];
    return Boolean(draft) && (
        String(draft.employee_contribution ?? '') !== String(enrollment.employee_contribution ?? '')
        || String(draft.employer_contribution ?? '') !== String(enrollment.employer_contribution ?? '')
        || String(draft.effective_start ?? '') !== String(enrollment.effective_start ?? '')
        || String(draft.effective_end ?? '') !== String(enrollment.effective_end ?? '')
        || String(draft.notes ?? '') !== String(enrollment.notes ?? '')
    );
}

async function submitBenefitEnrollment(enrollment) {
    benefitContributionErrors.value = { ...benefitContributionErrors.value, [enrollment.id]: '' };
    busy.value = true;
    try {
        await api(`/benefits/enrollments/${enrollment.id}/submit`, { method: 'POST', body: '{}' });
        message('Enrollment submitted for HR review. Inputs are locked.');
        await loadActive();
    } catch (requestError) {
        benefitContributionErrors.value = { ...benefitContributionErrors.value, [enrollment.id]: requestError.message };
    } finally {
        busy.value = false;
    }
}

function openBenefitReview(enrollment, decision) {
    selectedBenefitEnrollmentId.value = enrollment.id;
    benefitReviewDecision.value = decision;
    benefitReviewAcknowledged.value = false;
    benefitReviewNote.value = '';
    benefitReviewError.value = '';
    benefitReviewOpen.value = true;
}

function closeBenefitReview() {
    benefitReviewOpen.value = false;
    benefitReviewAcknowledged.value = false;
    benefitReviewError.value = '';
}

async function reviewBenefitEnrollment() {
    const enrollment = selectedBenefitEnrollment.value;
    if (!enrollment) return;
    benefitReviewError.value = '';
    busy.value = true;
    try {
        await api(`/benefits/enrollments/${enrollment.id}/review`, {
            method: 'POST',
            body: JSON.stringify({
                decision: benefitReviewDecision.value,
                review_note: benefitReviewNote.value.trim() || null,
            }),
        });
        benefitReviewOpen.value = false;
        message(`Enrollment ${benefitReviewDecision.value === 'approve' ? 'approved' : benefitReviewDecision.value === 'return' ? 'returned for changes' : 'declined'}. No contribution or payment was processed.`);
        await loadActive();
    } catch (requestError) {
        benefitReviewError.value = requestError.message;
    } finally {
        busy.value = false;
    }
}

function openBenefitEnd(enrollment) {
    selectedBenefitEnrollmentId.value = enrollment.id;
    benefitEndDate.value = localDateInput(new Date());
    benefitEndError.value = '';
    benefitEndOpen.value = true;
}

async function endBenefitEnrollment() {
    const enrollment = selectedBenefitEnrollment.value;
    if (!enrollment) return;
    benefitEndError.value = '';
    busy.value = true;
    try {
        await api(`/benefits/enrollments/${enrollment.id}/end`, {
            method: 'POST',
            body: JSON.stringify({ effective_end: benefitEndDate.value }),
        });
        benefitEndOpen.value = false;
        message('Benefit enrollment end date recorded.');
        await loadActive();
    } catch (requestError) {
        benefitEndError.value = requestError.message;
    } finally {
        busy.value = false;
    }
}

function exportBenefitsCsv() {
    benefitsExporting.value = true;
    try {
        const rows = [
            ['Employee code', 'Employee', 'Department', 'Plan', 'Category', 'Currency', 'Employee contribution (manual)', 'Employer contribution (manual)', 'Effective start', 'Effective end', 'Status', 'Review note'],
            ...visibleBenefitEnrollments.value.map((enrollment) => [
                enrollment.employee_code, enrollment.employee_name, enrollment.department,
                enrollment.plan?.name, enrollment.plan?.category, enrollment.plan?.currency,
                enrollment.employee_contribution, enrollment.employer_contribution,
                enrollment.effective_start, enrollment.effective_end, enrollment.status, enrollment.review_note,
            ]),
        ];
        downloadCsv(rows, `benefit-enrollments-${localDateInput(new Date())}.csv`);
        message(`Exported ${visibleBenefitEnrollments.value.length} benefit enrollments.`);
    } catch (requestError) {
        message(requestError.message, true);
    } finally {
        benefitsExporting.value = false;
    }
}

function openSettlementForm() {
    settlementFormError.value = '';
    settlementForm.value = { lifecycle_case_id: String(eligibleSettlementCases.value[0]?.id || ''), notes: '' };
    showSettlementForm.value = true;
    separationTab.value = 'workpapers';
}

async function createSettlement() {
    settlementFormError.value = '';
    busy.value = true;
    try {
        const result = await api('/separations/settlements', {
            method: 'POST',
            body: JSON.stringify({
                ...settlementForm.value,
                lifecycle_case_id: Number(settlementForm.value.lifecycle_case_id),
            }),
        });
        showSettlementForm.value = false;
        selectedSettlementId.value = result.settlement.id;
        separationTab.value = 'detail';
        message('Settlement workpaper created. Add only manually verified amounts.');
        await loadActive();
    } catch (requestError) {
        settlementFormError.value = requestError.message;
    } finally {
        busy.value = false;
    }
}

function selectSettlement(settlement) {
    selectedSettlementId.value = settlement.id;
    separationTab.value = 'detail';
    settlementItemError.value = '';
}

function addSettlementLine(category = 'earning') {
    if (!selectedSettlement.value) return;
    settlementItemDrafts.value = {
        ...settlementItemDrafts.value,
        [selectedSettlement.value.id]: [
            ...(settlementItemDrafts.value[selectedSettlement.value.id] || []),
            { category, description: '', amount: '', notes: '' },
        ],
    };
}

function removeSettlementLine(index) {
    if (!selectedSettlement.value) return;
    settlementItemDrafts.value = {
        ...settlementItemDrafts.value,
        [selectedSettlement.value.id]: settlementItemDrafts.value[selectedSettlement.value.id]
            .filter((_, lineIndex) => lineIndex !== index),
    };
}

function settlementItemsChanged(settlement) {
    const draft = settlementItemDrafts.value[settlement.id] || [];
    if (draft.length !== settlement.items.length) return true;
    return draft.some((line, index) => {
        const saved = settlement.items[index];
        return line.category !== saved.category
            || String(line.description || '') !== String(saved.description || '')
            || String(line.amount ?? '') !== String(saved.amount ?? '')
            || String(line.notes || '') !== String(saved.notes || '');
    });
}

async function saveSettlementItems() {
    if (!selectedSettlement.value) return;
    settlementItemError.value = '';
    busy.value = true;
    try {
        const rows = settlementItemDrafts.value[selectedSettlement.value.id] || [];
        await api(`/separations/settlements/${selectedSettlement.value.id}/items`, {
            method: 'PUT',
            body: JSON.stringify({ items: rows }),
        });
        message('Manual settlement workpaper lines saved.');
        await loadActive();
    } catch (requestError) {
        settlementItemError.value = requestError.message;
    } finally {
        busy.value = false;
    }
}

async function submitSettlement() {
    if (!selectedSettlement.value) return;
    settlementItemError.value = '';
    busy.value = true;
    try {
        await api(`/separations/settlements/${selectedSettlement.value.id}/submit`, {
            method: 'POST',
            body: '{}',
        });
        message('Settlement workpaper submitted for review. No payment was made.');
        await loadActive();
    } catch (requestError) {
        settlementItemError.value = requestError.message;
    } finally {
        busy.value = false;
    }
}

function openSettlementReview(decision) {
    settlementReviewDecision.value = decision;
    settlementReviewAcknowledged.value = false;
    settlementReviewNote.value = '';
    settlementReviewError.value = '';
    settlementReviewOpen.value = true;
}

async function reviewSettlement() {
    if (!selectedSettlement.value) return;
    settlementReviewError.value = '';
    busy.value = true;
    try {
        await api(`/separations/settlements/${selectedSettlement.value.id}/review`, {
            method: 'POST',
            body: JSON.stringify({
                decision: settlementReviewDecision.value,
                review_note: settlementReviewNote.value.trim() || null,
            }),
        });
        settlementReviewOpen.value = false;
        message(settlementReviewDecision.value === 'approve'
            ? 'Settlement workpaper approved. No payment was initiated.'
            : 'Settlement workpaper returned for changes.');
        await loadActive();
    } catch (requestError) {
        settlementReviewError.value = requestError.message;
    } finally {
        busy.value = false;
    }
}

function settlementClearancesComplete(settlement) {
    const caseItem = settlement.offboarding_case;
    return caseItem?.status === 'completed'
        && caseItem.tasks?.length > 0
        && caseItem.tasks.every((task) => task.status === 'completed');
}

function exportSettlementCsv() {
    if (!selectedSettlement.value) return;
    settlementExporting.value = true;
    try {
        const settlement = selectedSettlement.value;
        const rows = [
            ['Employee code', 'Employee', 'Department', 'Currency', 'Offboarding effective date', 'Category', 'Manual component', 'Manual amount', 'Notes'],
            ...settlement.items.map((item) => [
                settlement.employee_code, settlement.employee_name, settlement.department,
                settlement.currency, settlement.offboarding_case?.effective_date,
                item.category, item.description, item.amount, item.notes,
            ]),
        ];
        downloadCsv(rows, `separation-workpaper-${settlement.employee_code}-${localDateInput(new Date())}.csv`);
        message(`Exported the ${settlement.currency} settlement workpaper. It is not a payment instruction.`);
    } catch (requestError) {
        message(requestError.message, true);
    } finally {
        settlementExporting.value = false;
    }
}

function downloadCsv(rows, filename) {
    const quote = (value) => {
        let text = String(value ?? '');
        if (/^[\t\r\n ]*[=+\-@]/.test(text)) text = `'${text}`;
        return `"${text.replaceAll('"', '""')}"`;
    };
    const csv = rows.map((row) => row.map(quote).join(',')).join('\r\n');
    const url = URL.createObjectURL(new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.click();
    URL.revokeObjectURL(url);
}

async function loadMoreAttendance() {
    if (busy.value || attendanceReport.value.meta.current_page >= attendanceReport.value.meta.last_page) return;
    busy.value = true;
    try {
        await loadAttendanceReport(true, attendanceReport.value.meta.current_page + 1);
    } catch (requestError) {
        message(requestError.message, true);
    } finally {
        busy.value = false;
    }
}

function resetAttendanceFilters() {
    attendanceFrom.value = localDateInput(new Date(Date.now() - 6 * 86400000));
    attendanceTo.value = localDateInput(new Date());
    attendanceSearch.value = '';
    attendanceStatusFilter.value = 'all';
}

async function exportAttendanceCsv() {
    attendanceExporting.value = true;
    try {
        let result = await api(attendanceApiPath(1, 500));
        const rows = [...result.data];
        while (result.meta.current_page < result.meta.last_page) {
            result = await api(attendanceApiPath(result.meta.current_page + 1, 500));
            rows.push(...result.data);
        }
        const headers = ['Employee code', 'Employee', 'Department', 'Branch', 'Date', 'Check in', 'Check out', 'Shift duration', 'Status'];
        const quote = (value) => `"${String(value ?? '').replaceAll('"', '""')}"`;
        const csv = [
            headers,
            ...rows.map((entry) => [
                entry.employee?.employee_code,
                fullName(entry.employee),
                entry.employee?.department,
                entry.employee?.branch?.name,
                entry.attendance_date,
                entry.check_in,
                entry.check_out,
                formatShiftDuration(entry.check_in, entry.check_out),
                entry.check_out ? 'Completed' : 'In progress',
            ]),
        ].map((row) => row.map(quote).join(',')).join('\r\n');
        const url = URL.createObjectURL(new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8' }));
        const link = document.createElement('a');
        link.href = url;
        link.download = `northstar-attendance-${attendanceFrom.value}-to-${attendanceTo.value}.csv`;
        link.click();
        URL.revokeObjectURL(url);
        message(`Exported ${rows.length} attendance records.`);
    } catch (requestError) {
        message(requestError.message, true);
    } finally {
        attendanceExporting.value = false;
    }
}

async function loadMoreCandidates() {
    if (busy.value || recruitment.value.candidate_pagination.current_page >= recruitment.value.candidate_pagination.last_page) return;
    busy.value = true;
    try {
        const nextPage = recruitment.value.candidate_pagination.current_page + 1;
        const result = await api(recruitmentApiPath(nextPage));
        recruitment.value = {
            ...result,
            candidates: [...recruitment.value.candidates, ...result.candidates],
        };
        candidateStageDrafts.value = {
            ...candidateStageDrafts.value,
            ...Object.fromEntries(result.candidates.map((candidate) => [candidate.id, candidate.stage])),
        };
        candidateFeedbackDrafts.value = {
            ...candidateFeedbackDrafts.value,
            ...Object.fromEntries(result.candidates.map((candidate) => [
                candidate.id,
                { rating: candidate.rating ?? '', notes: candidate.notes || '' },
            ])),
        };
    } catch (requestError) {
        message(requestError.message, true);
    } finally {
        busy.value = false;
    }
}

async function signIn() {
    busy.value = true;
    error.value = '';
    try {
        await api('/login', { method: 'POST', body: JSON.stringify(loginForm.value) });
        await loadShell();
    } catch (requestError) {
        message(requestError.message, true);
    } finally {
        busy.value = false;
    }
}

async function signOut() {
    try {
        const result = await api('/logout', { method: 'POST', body: '{}' });
        setCsrfToken(result.csrf_token);
        user.value = null;
        activeSlug.value = 'dashboard';
    } catch (requestError) {
        message(requestError.message, true);
    }
}

async function createEmployee() {
    busy.value = true;
    try {
        await api('/employees', { method: 'POST', body: JSON.stringify(employeeForm.value) });
        showEmployeeForm.value = false;
        employeeForm.value = {
            employee_code: '', first_name: '', last_name: '', work_email: '',
            job_title: '', department: '', employment_type: 'Full-time', join_date: new Date().toISOString().slice(0, 10),
        };
        message('Employee record saved.');
        await loadActive();
    } catch (requestError) {
        message(requestError.message, true);
    } finally {
        busy.value = false;
    }
}

function resetDepartmentForm() {
    departmentForm.value = { name: '', code: '', branch_id: '', parent_id: '', manager_employee_id: '', cost_center: '', status: 'active' };
    editingDepartmentId.value = null;
}

function editDepartment(department) {
    editingDepartmentId.value = department.id;
    departmentForm.value = {
        name: department.name,
        code: department.code,
        branch_id: department.branch_id ? String(department.branch_id) : '',
        parent_id: department.parent_id ? String(department.parent_id) : '',
        manager_employee_id: department.manager_employee_id ? String(department.manager_employee_id) : '',
        cost_center: department.cost_center || '',
        status: department.status,
    };
    showDepartmentForm.value = true;
}

async function saveDepartment() {
    busy.value = true;
    try {
        const wasEditing = Boolean(editingDepartmentId.value);
        const payload = {
            ...departmentForm.value,
            branch_id: departmentForm.value.branch_id ? Number(departmentForm.value.branch_id) : null,
            parent_id: departmentForm.value.parent_id ? Number(departmentForm.value.parent_id) : null,
            manager_employee_id: departmentForm.value.manager_employee_id ? Number(departmentForm.value.manager_employee_id) : null,
        };
        const path = editingDepartmentId.value
            ? `/organization/departments/${editingDepartmentId.value}`
            : '/organization/departments';
        await api(path, { method: editingDepartmentId.value ? 'PATCH' : 'POST', body: JSON.stringify(payload) });
        showDepartmentForm.value = false;
        resetDepartmentForm();
        message(wasEditing ? 'Department updated.' : 'Department created.');
        await loadActive();
    } catch (requestError) {
        message(requestError.message, true);
    } finally {
        busy.value = false;
    }
}

async function toggleDepartmentStatus(department) {
    busy.value = true;
    try {
        await api(`/organization/departments/${department.id}`, {
            method: 'PATCH',
            body: JSON.stringify({
                name: department.name,
                code: department.code,
                branch_id: department.branch_id,
                parent_id: department.parent_id,
                manager_employee_id: department.manager_employee_id,
                cost_center: department.cost_center,
                status: department.status === 'active' ? 'inactive' : 'active',
            }),
        });
        message(`Department ${department.status === 'active' ? 'archived' : 'activated'}.`);
        await loadActive();
    } catch (requestError) {
        message(requestError.message, true);
    } finally {
        busy.value = false;
    }
}

async function createPosition() {
    busy.value = true;
    try {
        await api('/organization/positions', {
            method: 'POST',
            body: JSON.stringify({
                ...positionForm.value,
                department_id: Number(positionForm.value.department_id),
                branch_id: positionForm.value.branch_id ? Number(positionForm.value.branch_id) : null,
                reports_to_position_id: positionForm.value.reports_to_position_id
                    ? Number(positionForm.value.reports_to_position_id)
                    : null,
                headcount: Number(positionForm.value.headcount),
            }),
        });
        showPositionForm.value = false;
        positionForm.value = {
            title: '', code: '', department_id: organization.value.departments[0]?.id?.toString() || '',
            branch_id: '', reports_to_position_id: '', grade: '', employment_type: 'Full-time', headcount: 1,
        };
        message('Position added to the workforce plan.');
        await loadActive();
    } catch (requestError) {
        message(requestError.message, true);
    } finally {
        busy.value = false;
    }
}

async function assignPosition(position) {
    const employeeId = assignmentDrafts.value[position.id];
    if (!employeeId) {
        message('Choose an employee before assigning this position.', true);
        return;
    }
    busy.value = true;
    try {
        await api(`/organization/positions/${position.id}/assign`, {
            method: 'POST',
            body: JSON.stringify({ employee_id: Number(employeeId) }),
        });
        assignmentDrafts.value[position.id] = '';
        message('Position assignment saved.');
        await loadActive();
    } catch (requestError) {
        message(requestError.message, true);
    } finally {
        busy.value = false;
    }
}

async function saveReportingLine(employee) {
    busy.value = true;
    try {
        const managerId = managerDrafts.value[employee.id];
        await api(`/organization/employees/${employee.id}/reporting-line`, {
            method: 'PATCH',
            body: JSON.stringify({ manager_id: managerId ? Number(managerId) : null }),
        });
        message(`Reporting line updated for ${fullName(employee)}.`);
        await loadActive();
    } catch (requestError) {
        message(requestError.message, true);
    } finally {
        busy.value = false;
    }
}

function resetRequisitionForm() {
    requisitionForm.value = {
        code: '', title: '', department_id: recruitment.value.departments[0]?.id?.toString() || '',
        position_id: '', employment_type: 'Full-time', work_mode: 'On-site', location: '',
        openings: 1, priority: 'normal', target_date: '', description: '',
    };
    recruitmentFormError.value = '';
}

function openRequisitionForm() {
    resetRequisitionForm();
    recruitmentTab.value = 'requisitions';
    showRequisitionForm.value = true;
    showCandidateForm.value = false;
}

function openCandidateForm(requisitionId = '') {
    recruitmentTab.value = 'requisitions';
    showRequisitionForm.value = false;
    const defaultId = openRecruitmentRequisitions.value[0]?.id?.toString() || '';
    candidateForm.value = {
        requisition_id: requisitionId ? String(requisitionId) : defaultId,
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        source: 'Direct',
        rating: '',
        notes: '',
    };
    recruitmentFormError.value = '';
    showCandidateForm.value = true;
}

function syncRequisitionDepartment() {
    const position = recruitment.value.positions.find((entry) => String(entry.id) === requisitionForm.value.position_id);
    if (position) requisitionForm.value.department_id = String(position.department_id);
}

function candidateMoveOptions(candidate) {
    const currentIndex = ['new', 'screening', 'interview', 'offer'].indexOf(candidate.stage);
    return recruitmentStages.filter((stage) => stage.id === candidate.stage
        || ['rejected', 'withdrawn'].includes(stage.id)
        || (['screening', 'interview', 'offer'].includes(stage.id)
            && ['new', 'screening', 'interview', 'offer'].indexOf(stage.id) > currentIndex));
}

async function createRequisition() {
    busy.value = true;
    recruitmentFormError.value = '';
    try {
        const position = recruitment.value.positions.find((entry) => String(entry.id) === requisitionForm.value.position_id);
        const payload = {
            ...requisitionForm.value,
            branch_id: user.value.branch?.id || null,
            department_id: requisitionForm.value.department_id
                ? Number(requisitionForm.value.department_id)
                : position?.department_id || null,
            position_id: requisitionForm.value.position_id ? Number(requisitionForm.value.position_id) : null,
            openings: Number(requisitionForm.value.openings),
            target_date: requisitionForm.value.target_date || null,
            description: requisitionForm.value.description || null,
        };
        await api('/recruitment/requisitions', { method: 'POST', body: JSON.stringify(payload) });
        showRequisitionForm.value = false;
        resetRequisitionForm();
        recruitmentTab.value = 'requisitions';
        message('Requisition created and saved.');
        await loadActive();
    } catch (requestError) {
        recruitmentFormError.value = requestError.message;
        message(requestError.message, true);
    } finally {
        busy.value = false;
    }
}

async function createCandidate() {
    busy.value = true;
    recruitmentFormError.value = '';
    try {
        const requisitionId = Number(candidateForm.value.requisition_id);
        await api(`/recruitment/requisitions/${requisitionId}/candidates`, {
            method: 'POST',
            body: JSON.stringify({
                ...candidateForm.value,
                rating: candidateForm.value.rating ? Number(candidateForm.value.rating) : null,
                notes: candidateForm.value.notes || null,
            }),
        });
        candidateForm.value = {
            requisition_id: String(requisitionId), first_name: '', last_name: '', email: '',
            phone: '', source: 'Direct', rating: '', notes: '',
        };
        showCandidateForm.value = false;
        recruitmentTab.value = 'pipeline';
        recruitmentStageFilter.value = 'all';
        recruitmentRequestFilter.value = String(requisitionId);
        message('Candidate added to the private pipeline.');
        await loadActive();
    } catch (requestError) {
        recruitmentFormError.value = requestError.message;
        message(requestError.message, true);
    } finally {
        busy.value = false;
    }
}

async function updateCandidateStage(candidate) {
    const stage = candidateStageDrafts.value[candidate.id];
    if (!stage || stage === candidate.stage) return;
    busy.value = true;
    candidateActionErrors.value = { ...candidateActionErrors.value, [candidate.id]: '' };
    try {
        await api(`/recruitment/candidates/${candidate.id}`, {
            method: 'PATCH',
            body: JSON.stringify({
                stage,
                rating: candidateFeedbackDrafts.value[candidate.id]?.rating
                    ? Number(candidateFeedbackDrafts.value[candidate.id].rating)
                    : null,
                notes: candidateFeedbackDrafts.value[candidate.id]?.notes || null,
            }),
        });
        message(`Candidate moved to ${recruitmentStages.find((item) => item.id === stage)?.label || stage}.`);
        await loadActive();
    } catch (requestError) {
        candidateStageDrafts.value[candidate.id] = candidate.stage;
        candidateActionErrors.value = { ...candidateActionErrors.value, [candidate.id]: requestError.message };
        message(requestError.message, true);
    } finally {
        busy.value = false;
    }
}

async function saveCandidateFeedback(candidate) {
    busy.value = true;
    candidateActionErrors.value = { ...candidateActionErrors.value, [candidate.id]: '' };
    try {
        const draft = candidateFeedbackDrafts.value[candidate.id];
        await api(`/recruitment/candidates/${candidate.id}`, {
            method: 'PATCH',
            body: JSON.stringify({
                stage: candidate.stage,
                rating: draft.rating ? Number(draft.rating) : null,
                notes: draft.notes || null,
            }),
        });
        message('Recruiter feedback saved.');
        await loadActive();
    } catch (requestError) {
        candidateActionErrors.value = { ...candidateActionErrors.value, [candidate.id]: requestError.message };
        message(requestError.message, true);
    } finally {
        busy.value = false;
    }
}

async function updateRequisitionStatus(requisition, status) {
    busy.value = true;
    recruitmentFormError.value = '';
    try {
        await api(`/recruitment/requisitions/${requisition.id}/status`, {
            method: 'PATCH',
            body: JSON.stringify({ status }),
        });
        message(`Requisition ${status === 'on_hold' ? 'paused' : status === 'closed' ? 'closed' : 'reopened'}.`);
        await loadActive();
    } catch (requestError) {
        recruitmentFormError.value = requestError.message;
        message(requestError.message, true);
    } finally {
        busy.value = false;
    }
}

function openHireDialog(candidate) {
    selectedCandidate.value = candidate;
    hireActionError.value = '';
    hireForm.value = {
        employee_code: `NEW-${candidate.id}`,
        join_date: new Date().toISOString().slice(0, 10),
    };
}

async function hireCandidate() {
    if (!selectedCandidate.value) return;
    busy.value = true;
    hireActionError.value = '';
    try {
        await api(`/recruitment/candidates/${selectedCandidate.value.id}/hire`, {
            method: 'POST',
            body: JSON.stringify(hireForm.value),
        });
        hireDialog.value?.close();
        message('Candidate hired and employee record created.');
        await loadActive();
    } catch (requestError) {
        hireActionError.value = requestError.message;
        message(requestError.message, true);
    } finally {
        busy.value = false;
    }
}

function exportRecruitmentCsv() {
    const rows = filteredRecruitmentCandidates.value;
    const headers = ['First name', 'Last name', 'Email', 'Phone', 'Requisition', 'Stage', 'Source', 'Rating', 'Applied'];
    const quote = (value) => `"${String(value ?? '').replaceAll('"', '""')}"`;
    const csv = [
        headers,
        ...rows.map((candidate) => [
            candidate.first_name, candidate.last_name, candidate.email, candidate.phone,
            candidate.requisition?.title, candidate.stage, candidate.source, candidate.rating,
            candidate.applied_at,
        ]),
    ].map((row) => row.map(quote).join(',')).join('\r\n');
    const url = URL.createObjectURL(new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = `northstar-candidates-${new Date().toISOString().slice(0, 10)}.csv`;
    link.click();
    URL.revokeObjectURL(url);
}

function closeHireDialog() {
    selectedCandidate.value = null;
}

function isLifecycleTaskOverdue(task) {
    return task.status !== 'completed'
        && task.due_date
        && task.due_date.slice(0, 10) < new Date().toISOString().slice(0, 10);
}

function lifecycleCaseProgress(caseItem) {
    if (!caseItem.tasks?.length) return 0;
    return Math.round(caseItem.tasks.filter((task) => task.status === 'completed').length / caseItem.tasks.length * 100);
}

function setLifecycleTab(tab) {
    lifecycleTab.value = tab;
    selectedLifecycleCaseId.value = filteredLifecycleCases.value[0]?.id || null;
}

function openLifecycleForm(type = lifecycleTab.value === 'offboarding' ? 'offboarding' : 'onboarding') {
    lifecycleFormError.value = '';
    lifecycleForm.value = {
        type,
        employee_id: '',
        effective_date: new Date(Date.now() + 14 * 86400000).toISOString().slice(0, 10),
        notes: '',
    };
    showLifecycleForm.value = true;
}

async function startLifecycleCase() {
    lifecycleFormError.value = '';
    busy.value = true;
    try {
        const result = await api('/lifecycle/cases', {
            method: 'POST',
            body: JSON.stringify({
                ...lifecycleForm.value,
                employee_id: Number(lifecycleForm.value.employee_id),
                notes: lifecycleForm.value.notes.trim() || null,
            }),
        });
        showLifecycleForm.value = false;
        lifecycleTab.value = result.case.type;
        selectedLifecycleCaseId.value = result.case.id;
        message(`${result.case.type === 'onboarding' ? 'Onboarding' : 'Offboarding'} journey created with ${result.case.tasks.length} checklist steps.`);
        await loadActive();
        selectedLifecycleCaseId.value = result.case.id;
    } catch (requestError) {
        lifecycleFormError.value = requestError.message;
        message(requestError.message, true);
    } finally {
        busy.value = false;
    }
}

async function saveLifecycleTask(task) {
    lifecycleTaskErrors.value = { ...lifecycleTaskErrors.value, [task.id]: '' };
    busy.value = true;
    try {
        const draft = lifecycleTaskDrafts.value[task.id];
        await api(`/lifecycle/tasks/${task.id}`, {
            method: 'PATCH',
            body: JSON.stringify({ status: draft.status, notes: draft.notes.trim() || null }),
        });
        const caseId = selectedLifecycleCaseId.value;
        await loadActive();
        selectedLifecycleCaseId.value = caseId;
        message('Checklist step saved.');
    } catch (requestError) {
        lifecycleTaskErrors.value = { ...lifecycleTaskErrors.value, [task.id]: requestError.message };
        message(requestError.message, true);
    } finally {
        busy.value = false;
    }
}

async function completeLifecycleCase() {
    if (!selectedLifecycleCase.value) return;
    busy.value = true;
    try {
        await api(`/lifecycle/cases/${selectedLifecycleCase.value.id}/complete`, {
            method: 'POST',
            body: JSON.stringify({
                confirm_deactivation: selectedLifecycleCase.value.type === 'offboarding' && lifecycleCloseAcknowledged.value,
            }),
        });
        lifecycleCloseDialog.value?.close();
        message(selectedLifecycleCase.value.type === 'offboarding'
            ? 'Offboarding completed and employee access status set to inactive.'
            : 'Onboarding completed.');
        await loadActive();
    } catch (requestError) {
        message(requestError.message, true);
    } finally {
        busy.value = false;
    }
}

function exportLifecycleCsv() {
    const rows = filteredLifecycleCases.value;
    const headers = ['Employee code', 'Employee', 'Department', 'Journey', 'Status', 'Effective date', 'Completed steps', 'Total steps'];
    const quote = (value) => `"${String(value ?? '').replaceAll('"', '""')}"`;
    const csv = [
        headers,
        ...rows.map((caseItem) => [
            caseItem.employee?.employee_code,
            fullName(caseItem.employee),
            caseItem.employee?.department,
            caseItem.type,
            caseItem.status,
            caseItem.effective_date,
            caseItem.tasks.filter((task) => task.status === 'completed').length,
            caseItem.tasks.length,
        ]),
    ].map((row) => row.map(quote).join(',')).join('\r\n');
    const url = URL.createObjectURL(new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = `northstar-lifecycle-${new Date().toISOString().slice(0, 10)}.csv`;
    link.click();
    URL.revokeObjectURL(url);
}

async function createLeaveRequest() {
    busy.value = true;
    try {
        await api('/leave', { method: 'POST', body: JSON.stringify(leaveForm.value) });
        leaveForm.value = { leave_type_id: '', start_date: '', end_date: '', reason: '' };
        message('Leave request submitted for review.');
        await loadActive();
    } catch (requestError) {
        message(requestError.message, true);
    } finally {
        busy.value = false;
    }
}

async function submitSelfServiceLeaveRequest() {
    selfServiceLeaveError.value = '';
    busy.value = true;
    try {
        await api('/leave', { method: 'POST', body: JSON.stringify(leaveForm.value) });
        leaveForm.value = { leave_type_id: selfServiceData.value.leave_types[0]?.id || '', start_date: '', end_date: '', reason: '' };
        message('Your leave request is submitted for review.');
        await loadActive();
    } catch (requestError) {
        selfServiceLeaveError.value = requestError.message;
    } finally {
        busy.value = false;
    }
}

async function saveSelfServiceProfile() {
    selfServiceProfileError.value = '';
    busy.value = true;
    try {
        const result = await api('/self-service/profile', {
            method: 'PATCH',
            body: JSON.stringify(selfServiceProfileForm.value),
        });
        selfServiceData.value.employee = result.employee;
        message('Your contact details were updated.');
    } catch (requestError) {
        selfServiceProfileError.value = requestError.message;
    } finally {
        busy.value = false;
    }
}

async function reviewLeave(request, decision) {
    const verb = decision === 'approved' ? 'approve' : 'decline';
    if (!window.confirm(`${verb[0].toUpperCase()}${verb.slice(1)} this leave request?`)) return;
    busy.value = true;
    try {
        await api(`/leave/${request.id}/review`, {
            method: 'POST',
            body: JSON.stringify({ decision, review_note: leaveReviewNote.value || null }),
        });
        leaveReviewNote.value = '';
        message(`Request ${decision}.`);
        await loadActive();
    } catch (requestError) {
        message(requestError.message, true);
    } finally {
        busy.value = false;
    }
}

function openManagerLeaveReview(request, decision) {
    managerReviewRequestId.value = request.id;
    managerReviewDecision.value = decision;
    managerReviewNote.value = '';
    managerReviewAcknowledged.value = false;
    managerReviewError.value = '';
    managerReviewOpen.value = true;
}

function closeManagerLeaveReview() {
    managerReviewOpen.value = false;
    managerReviewRequestId.value = null;
    managerReviewAcknowledged.value = false;
    managerReviewError.value = '';
}

async function submitManagerLeaveReview() {
    if (!selectedManagerRequest.value) return;
    managerReviewError.value = '';
    busy.value = true;
    try {
        await api(`/leave/${selectedManagerRequest.value.id}/review`, {
            method: 'POST',
            body: JSON.stringify({
                decision: managerReviewDecision.value,
                review_note: managerReviewNote.value || null,
            }),
        });
        managerReviewDialog.value?.close();
        message(`Leave request ${managerReviewDecision.value}.`);
        await loadActive();
    } catch (requestError) {
        managerReviewError.value = requestError.message;
    } finally {
        busy.value = false;
    }
}

function expenseCategoryLabel(category) {
    return String(category || 'other').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

async function submitExpenseClaim() {
    expenseFormError.value = '';
    busy.value = true;
    try {
        const payload = new FormData();
        Object.entries(expenseForm.value).forEach(([key, value]) => payload.append(key, value));
        if (expenseReceiptFile.value) payload.append('receipt', expenseReceiptFile.value);
        await api('/expenses', { method: 'POST', body: payload });
        expenseForm.value = { category: 'meals', merchant: '', expense_date: localDateInput(new Date()), amount: '', description: '' };
        expenseReceiptFile.value = null;
        expenseFormOpen.value = false;
        expenseTab.value = 'all';
        message('Expense claim submitted for review. Approval does not arrange payment.');
        await loadActive();
    } catch (requestError) {
        expenseFormError.value = requestError.message;
    } finally {
        busy.value = false;
    }
}

function openExpenseReview(claim, decision) {
    expenseReviewId.value = claim.id;
    expenseReviewDecision.value = decision;
    expenseReviewNote.value = '';
    expenseReviewError.value = '';
    expenseReviewOpen.value = true;
}

function closeExpenseReview() {
    expenseReviewOpen.value = false;
    expenseReviewId.value = null;
    expenseReviewError.value = '';
}

async function submitExpenseReview() {
    if (!selectedExpenseClaim.value) return;
    expenseReviewError.value = '';
    busy.value = true;
    try {
        await api(`/expenses/${selectedExpenseClaim.value.id}/review`, {
            method: 'POST',
            body: JSON.stringify({
                decision: expenseReviewDecision.value,
                review_note: expenseReviewNote.value.trim() || null,
            }),
        });
        expenseReviewDialog.value?.close();
        message(expenseReviewDecision.value === 'approve'
            ? 'Claim approved. Reimbursement still requires a separate process.'
            : 'Claim rejected with a recorded reason.');
        await loadActive();
    } catch (requestError) {
        expenseReviewError.value = requestError.message;
    } finally {
        busy.value = false;
    }
}

function formatLoanMoney(amount, currency) {
    return `${currency || ''} ${Number(amount || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`.trim();
}

async function submitLoanRequest() {
    loanFormError.value = '';
    busy.value = true;
    try {
        await api('/loans-advances', { method: 'POST', body: JSON.stringify(loanForm.value) });
        loanForm.value = { requested_amount: '', requested_installments: 6, purpose: '' };
        loanFormOpen.value = false;
        loanTab.value = 'all';
        message('Loan request submitted for review. No funds or payroll deduction were initiated.');
        await loadActive();
    } catch (requestError) {
        loanFormError.value = requestError.message;
    } finally {
        busy.value = false;
    }
}

function openLoanReview(loan, decision) {
    loanReviewId.value = loan.id;
    loanReviewDecision.value = decision;
    const firstDueDate = new Date();
    firstDueDate.setMonth(firstDueDate.getMonth() + 1);
    loanReviewForm.value = {
        approved_amount: decision === 'approve' ? loan.requested_amount : '',
        repayment_installments: loan.requested_installments,
        first_due_date: localDateInput(firstDueDate),
        review_note: '',
    };
    loanReviewError.value = '';
    loanReviewOpen.value = true;
}

function closeLoanReview() {
    loanReviewOpen.value = false;
    loanReviewId.value = null;
    loanReviewError.value = '';
}

async function submitLoanReview() {
    if (!selectedLoanAdvance.value) return;
    loanReviewError.value = '';
    busy.value = true;
    try {
        const body = {
            decision: loanReviewDecision.value,
            review_note: loanReviewForm.value.review_note.trim() || null,
        };
        if (loanReviewDecision.value === 'approve') {
            body.approved_amount = loanReviewForm.value.approved_amount;
            body.repayment_installments = loanReviewForm.value.repayment_installments;
            body.first_due_date = loanReviewForm.value.first_due_date;
        }
        await api(`/loans-advances/${selectedLoanAdvance.value.id}/review`, {
            method: 'POST',
            body: JSON.stringify(body),
        });
        loanReviewDialog.value?.close();
        message(loanReviewDecision.value === 'approve'
            ? 'Request approved and a proposed repayment schedule recorded. No payment or payroll deduction was made.'
            : 'Request rejected with a recorded reason.');
        await loadActive();
    } catch (requestError) {
        loanReviewError.value = requestError.message;
    } finally {
        busy.value = false;
    }
}

function openLoanRepayment(installment) {
    loanRepaymentInstallmentId.value = installment.id;
    loanRepaymentForm.value = {
        amount: installment.remaining_amount,
        recorded_date: localDateInput(new Date()),
        reference: '',
    };
    loanRepaymentError.value = '';
}

async function submitLoanRepayment(loan) {
    if (!loanRepaymentInstallmentId.value) return;
    loanRepaymentError.value = '';
    busy.value = true;
    try {
        await api(`/loans-advances/installments/${loanRepaymentInstallmentId.value}/repayments`, {
            method: 'POST',
            body: JSON.stringify(loanRepaymentForm.value),
        });
        loanRepaymentInstallmentId.value = null;
        message('External repayment recorded for reconciliation; no funds were moved by this app.');
        await loadActive();
    } catch (requestError) {
        loanRepaymentError.value = requestError.message;
    } finally {
        busy.value = false;
    }
}

function travelStatusLabel(status) {
    return {
        pending: 'Awaiting review',
        approved: 'Approved',
        rejected: 'Rejected',
        report_submitted: 'Report submitted',
        reconciled: 'Reconciled',
    }[status] || String(status || '').replaceAll('_', ' ');
}

async function submitTravelRequest() {
    travelFormError.value = '';
    busy.value = true;
    try {
        await api('/travel', { method: 'POST', body: JSON.stringify(travelForm.value) });
        travelForm.value = { origin: '', destination: '', purpose: '', departure_date: '', return_date: '', estimated_budget: '', itinerary_notes: '' };
        travelFormOpen.value = false;
        travelTab.value = 'all';
        message('Travel request submitted for review. No booking or funds were initiated.');
        await loadActive();
    } catch (requestError) {
        travelFormError.value = requestError.message;
    } finally {
        busy.value = false;
    }
}

function openTravelReview(trip, decision) {
    travelReviewId.value = trip.id;
    travelReviewDecision.value = decision;
    travelReviewForm.value = {
        approved_budget: trip.estimated_budget,
        review_note: '',
    };
    travelReviewError.value = '';
    travelReviewOpen.value = true;
}

function closeTravelReview() {
    travelReviewOpen.value = false;
    travelReviewId.value = null;
    travelReviewError.value = '';
}

async function submitTravelReview() {
    if (!selectedTravelTrip.value) return;
    travelReviewError.value = '';
    busy.value = true;
    try {
        const body = {
            decision: travelReviewDecision.value,
            review_note: travelReviewForm.value.review_note.trim() || null,
        };
        if (travelReviewDecision.value === 'approve') body.approved_budget = travelReviewForm.value.approved_budget;
        await api(`/travel/${selectedTravelTrip.value.id}/review`, { method: 'POST', body: JSON.stringify(body) });
        travelReviewDialog.value?.close();
        message(travelReviewDecision.value === 'approve'
            ? 'Travel request approved. This does not book travel or issue funds.'
            : 'Travel request rejected with a recorded reason.');
        await loadActive();
    } catch (requestError) {
        travelReviewError.value = requestError.message;
    } finally {
        busy.value = false;
    }
}

function openTravelReport(trip) {
    travelReportId.value = trip.id;
    travelReportForm.value = { actual_total: '', post_trip_note: '' };
    travelReportError.value = '';
}

async function submitTravelReport(trip) {
    travelReportError.value = '';
    busy.value = true;
    try {
        await api(`/travel/${trip.id}/post-trip-report`, {
            method: 'POST',
            body: JSON.stringify(travelReportForm.value),
        });
        travelReportId.value = null;
        message('Post-trip report submitted to HR for reconciliation.');
        await loadActive();
    } catch (requestError) {
        travelReportError.value = requestError.message;
    } finally {
        busy.value = false;
    }
}

function openTravelReconcile(trip) {
    travelReconcileId.value = trip.id;
    travelReconcileNote.value = '';
    travelReconcileError.value = '';
}

async function submitTravelReconcile(trip) {
    travelReconcileError.value = '';
    busy.value = true;
    try {
        await api(`/travel/${trip.id}/reconcile`, {
            method: 'POST',
            body: JSON.stringify({ reconciliation_note: travelReconcileNote.value }),
        });
        travelReconcileId.value = null;
        message('Travel report reconciled. This does not record a reimbursement payment.');
        await loadActive();
    } catch (requestError) {
        travelReconcileError.value = requestError.message;
    } finally {
        busy.value = false;
    }
}

function disciplinaryTypeLabel(type) {
    return {
        conduct: 'Conduct',
        attendance: 'Attendance',
        policy: 'Policy concern',
        grievance: 'Grievance',
        other: 'Other',
    }[type] || String(type || '').replaceAll('_', ' ');
}

function disciplinaryStatusLabel(status) {
    return {
        open: 'Open',
        under_review: 'Under investigation',
        resolved: 'Resolved',
    }[status] || String(status || '').replaceAll('_', ' ');
}

async function submitDisciplinaryCase() {
    disciplinaryFormError.value = '';
    busy.value = true;
    try {
        await api('/disciplinary/cases', { method: 'POST', body: JSON.stringify(disciplinaryForm.value) });
        disciplinaryForm.value = {
            employee_id: disciplinaryData.value.employees[0]?.id ? String(disciplinaryData.value.employees[0].id) : '',
            case_type: 'conduct',
            incident_date: localDateInput(new Date()),
            summary: '',
        };
        disciplinaryFormOpen.value = false;
        disciplinaryTab.value = 'all';
        message('Restricted HR case created. No finding or employment action was made.');
        await loadActive();
    } catch (requestError) {
        disciplinaryFormError.value = requestError.message;
    } finally {
        busy.value = false;
    }
}

function openDisciplinaryAction(caseItem, action) {
    disciplinaryActionId.value = caseItem.id;
    disciplinaryActionType.value = action;
    disciplinaryActionForm.value = {
        investigator_id: String(caseItem.investigator_id || user.value.id),
        investigation_notes: '',
        employee_response: caseItem.employee_response || '',
        resolution_summary: '',
    };
    disciplinaryActionError.value = '';
}

async function submitDisciplinaryAction(caseItem) {
    disciplinaryActionError.value = '';
    busy.value = true;
    const action = disciplinaryActionType.value;
    const endpoint = {
        investigate: 'start-review',
        response: 'employee-response',
        resolve: 'resolve',
    }[action];
    const body = action === 'investigate'
        ? {
            investigator_id: disciplinaryActionForm.value.investigator_id,
            investigation_notes: disciplinaryActionForm.value.investigation_notes,
        }
        : action === 'response'
            ? { employee_response: disciplinaryActionForm.value.employee_response }
            : { resolution_summary: disciplinaryActionForm.value.resolution_summary };
    try {
        await api(`/disciplinary/cases/${caseItem.id}/${endpoint}`, {
            method: 'POST',
            body: JSON.stringify(body),
        });
        disciplinaryActionId.value = null;
        disciplinaryActionType.value = '';
        message(action === 'resolve'
            ? 'Case marked resolved with the recorded manual summary.'
            : action === 'response'
                ? 'Employee response summary recorded.'
                : 'Investigation started and assigned.');
        await loadActive();
    } catch (requestError) {
        disciplinaryActionError.value = requestError.message;
    } finally {
        busy.value = false;
    }
}

function safetyTypeLabel(type) {
    return {
        incident: 'Incident',
        hazard: 'Hazard',
        inspection: 'Inspection',
        ppe: 'PPE concern',
    }[type] || String(type || '').replaceAll('_', ' ');
}

function safetyStatusLabel(status) {
    return {
        open: 'Open',
        in_progress: 'Action in progress',
        resolved: 'Resolved',
    }[status] || String(status || '').replaceAll('_', ' ');
}

function safetyPriorityLabel(priority) {
    return {
        low: 'Low',
        normal: 'Normal',
        high: 'High',
        urgent: 'Urgent',
    }[priority] || String(priority || '').replaceAll('_', ' ');
}

async function submitSafetyRecord() {
    safetyFormError.value = '';
    busy.value = true;
    try {
        await api('/health-safety/records', {
            method: 'POST',
            body: JSON.stringify({
                ...safetyForm.value,
                employee_id: safetyForm.value.employee_id || null,
            }),
        });
        safetyForm.value = {
            record_type: 'hazard',
            occurred_on: localDateInput(new Date()),
            location: '',
            priority: 'normal',
            summary: '',
            employee_id: safetyData.value.employees[0]?.id ? String(safetyData.value.employees[0].id) : '',
        };
        safetyFormOpen.value = false;
        safetyTab.value = 'all';
        message('Safety record created for scoped HR follow-up. No compliance determination was made.');
        await loadActive();
    } catch (requestError) {
        safetyFormError.value = requestError.message;
    } finally {
        busy.value = false;
    }
}

function openSafetyAction(record, action) {
    safetyActionId.value = record.id;
    safetyActionType.value = action;
    safetyActionForm.value = {
        assigned_to: String(record.assigned_to || safetyData.value.assignees[0]?.id || ''),
        action_plan: '',
        resolution_summary: '',
    };
    safetyActionError.value = '';
}

async function submitSafetyAction(record) {
    safetyActionError.value = '';
    busy.value = true;
    const action = safetyActionType.value;
    const endpoint = action === 'assign' ? 'assign' : 'resolve';
    const body = action === 'assign'
        ? {
            assigned_to: safetyActionForm.value.assigned_to,
            action_plan: safetyActionForm.value.action_plan,
        }
        : { resolution_summary: safetyActionForm.value.resolution_summary };
    try {
        await api(`/health-safety/records/${record.id}/${endpoint}`, {
            method: 'POST',
            body: JSON.stringify(body),
        });
        safetyActionId.value = null;
        safetyActionType.value = '';
        message(action === 'assign'
            ? 'Follow-up action assigned and recorded.'
            : 'Follow-up marked resolved with a manual summary.');
        await loadActive();
    } catch (requestError) {
        safetyActionError.value = requestError.message;
    } finally {
        busy.value = false;
    }
}

async function attendanceAction() {
    selfServiceAttendanceError.value = '';
    busy.value = true;
    try {
        if (employeeAttendance.value?.check_in && !employeeAttendance.value?.check_out) {
            await api(`/attendance/${employeeAttendance.value.id}/check-out`, { method: 'POST', body: '{}' });
            message('Check-out recorded.');
        } else {
            await api('/attendance/check-in', { method: 'POST', body: '{}' });
            message('Check-in recorded.');
        }
        if (activeSlug.value === 'employee-self-service') await loadActive();
        else await loadAttendanceReport();
    } catch (requestError) {
        if (activeSlug.value === 'employee-self-service') selfServiceAttendanceError.value = requestError.message;
        else message(requestError.message, true);
    } finally {
        busy.value = false;
    }
}

watch(selectedCandidate, async (candidate) => {
    if (!candidate) return;
    await nextTick();
    if (hireDialog.value && !hireDialog.value.open) hireDialog.value.showModal();
});

watch(managerReviewOpen, async (open) => {
    if (!open) return;
    await nextTick();
    if (managerReviewDialog.value && !managerReviewDialog.value.open) managerReviewDialog.value.showModal();
});

watch(lifecycleCloseOpen, async (open) => {
    if (!open) return;
    await nextTick();
    if (lifecycleCloseDialog.value && !lifecycleCloseDialog.value.open) lifecycleCloseDialog.value.showModal();
});

watch(() => lifecycleForm.value.type, () => {
    lifecycleForm.value.employee_id = availableLifecycleEmployees.value[0]?.id?.toString() || '';
});

watch([recruitmentSearch, recruitmentStageFilter, recruitmentRequestFilter], () => {
    if (!user.value || activeSlug.value !== 'recruitment') return;
    window.clearTimeout(recruitmentFilterTimer);
    recruitmentFilterTimer = window.setTimeout(() => loadActive(), 250);
});

watch([attendanceFrom, attendanceTo, attendanceSearch, attendanceStatusFilter], () => {
    if (!user.value || activeSlug.value !== 'attendance') return;
    window.clearTimeout(attendanceFilterTimer);
    attendanceFilterTimer = window.setTimeout(async () => {
        busy.value = true;
        try {
            await loadAttendanceReport();
        } catch (requestError) {
            message(requestError.message, true);
        } finally {
            busy.value = false;
        }
    }, 250);
});

function fullName(person) {
    return [person?.first_name, person?.last_name].filter(Boolean).join(' ') || person?.name || '—';
}

function initials(person) {
    return fullName(person).split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
}

function formatTime(value) {
    return value ? new Date(value).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '—';
}

function formatDate(value) {
    return value ? new Date(`${String(value).slice(0, 10)}T12:00:00`).toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
}

function localDateInput(date) {
    const localDate = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
    return localDate.toISOString().slice(0, 10);
}

function travelDurationDays(start, end) {
    const startDate = new Date(`${String(start).slice(0, 10)}T00:00:00Z`);
    const endDate = new Date(`${String(end).slice(0, 10)}T00:00:00Z`);
    return Math.max(1, Math.round((endDate - startDate) / 86400000) + 1);
}

function currentMonthStart() {
    const date = new Date();
    date.setDate(1);
    date.setHours(12, 0, 0, 0);
    return localDateInput(date);
}

function currentWeekStart() {
    const date = new Date();
    date.setHours(12, 0, 0, 0);
    date.setDate(date.getDate() - ((date.getDay() + 6) % 7));
    return localDateInput(date);
}

function rosterDayAssignments(date) {
    return filteredRosterAssignments.value.filter((assignment) => String(assignment.shift_date).slice(0, 10) === date);
}

function rosterDayLabel(date, options) {
    return new Date(`${date}T12:00:00`).toLocaleDateString([], options);
}

function isCurrentRosterDay(date) {
    return date === localDateInput(new Date());
}

function formatRosterTime(value) {
    if (!value) return '—';
    const [hours, minutes] = String(value).split(':').map(Number);
    const time = new Date();
    time.setHours(hours, minutes, 0, 0);
    return time.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
}

function shiftDurationLabel(pattern) {
    const toMinutes = (value) => {
        const [hours, minutes] = String(value || '00:00').split(':').map(Number);
        return hours * 60 + minutes;
    };
    let minutes = toMinutes(pattern.end_time) - toMinutes(pattern.start_time);
    if (minutes <= 0) minutes += 24 * 60;
    minutes = Math.max(0, minutes - Number(pattern.break_minutes || 0));
    return `${Math.floor(minutes / 60)}h ${String(minutes % 60).padStart(2, '0')}m`;
}

function formatShiftDuration(checkIn, checkOut) {
    if (!checkIn || !checkOut) return 'In progress';
    const minutes = Math.max(0, Math.round((new Date(checkOut) - new Date(checkIn)) / 60000));
    return `${Math.floor(minutes / 60)}h ${String(minutes % 60).padStart(2, '0')}m`;
}

function formatHours(hours) {
    const minutes = Math.round(Number(hours || 0) * 60);
    return `${Math.floor(minutes / 60)}h ${String(minutes % 60).padStart(2, '0')}m`;
}

function attendanceTrendWidth(entries) {
    const max = Math.max(1, ...attendanceReport.value.trend.map((day) => day.entries));
    return `${Math.max(entries ? 8 : 0, entries / max * 100)}%`;
}

onMounted(() => {
    window.addEventListener('popstate', syncLocation);
    loadShell();
});
onUnmounted(() => {
    window.removeEventListener('popstate', syncLocation);
    window.clearTimeout(recruitmentFilterTimer);
    window.clearTimeout(attendanceFilterTimer);
});
</script>

<template>
    <div v-if="!user" class="login-page">
        <div class="login-art">
            <div class="brand brand-light"><span class="brand-mark">N</span><span>northstar<span class="brand-sub">PEOPLE & CULTURE</span></span></div>
            <div class="login-art-content">
                <span class="eyebrow light-eyebrow">PEOPLE FIRST. BUILT FOR WHAT'S NEXT.</span>
                <h1>Make work<br>feel more human.</h1>
                <p>A considered home for the people, teams, and moments that move your business forward.</p>
            </div>
            <div class="art-footer"><span>GULF TRADING & MANUFACTURING</span><span>LOCAL DEMO ENVIRONMENT</span></div>
            <div class="art-orbit orbit-one"></div><div class="art-orbit orbit-two"></div>
        </div>
        <div class="login-card-wrap">
            <form class="login-card" @submit.prevent="signIn">
                <span class="eyebrow">WELCOME BACK</span>
                <h2>Sign in to your workspace</h2>
                <p class="muted">Use one of the synthetic local demo accounts.</p>
                <label>Email address<input v-model="loginForm.email" type="email" autocomplete="username" required></label>
                <label>Password<input v-model="loginForm.password" type="password" autocomplete="current-password" required></label>
                <p v-if="error" class="inline-error">{{ error }}</p>
                <button class="button button-primary button-wide" :disabled="busy">{{ busy ? 'Signing in…' : 'Sign in' }}<span>→</span></button>
                <div class="demo-accounts">
                    <span class="eyebrow">LOCAL DEMO ACCOUNTS · PASSWORD: <code>password</code></span>
                    <button type="button" @click="loginForm.email = 'maya.hr@example.test'">HR · maya.hr@example.test</button>
                    <button type="button" @click="loginForm.email = 'omar.manager@example.test'">Manager · omar.manager@example.test</button>
                    <button type="button" @click="loginForm.email = 'lina.employee@example.test'">Employee · lina.employee@example.test</button>
                </div>
                <p class="privacy-note">Synthetic demo records only. Replace demo credentials before any shared use.</p>
            </form>
        </div>
    </div>

    <div v-else class="app-shell">
        <aside class="sidebar">
            <button class="brand brand-dark" @click="navigate('dashboard')"><span class="brand-mark">N</span><span>northstar<span class="brand-sub">PEOPLE & CULTURE</span></span></button>
            <div class="workspace-switch"><span class="workspace-avatar">GT</span><span><b>Gulf Trading</b><small>{{ user.branch?.name || 'Company workspace' }}</small></span><span class="chevron">⌄</span></div>
            <nav class="side-nav" aria-label="Main navigation">
                <template v-for="(groupModules, area) in groupedModules" :key="area">
                    <p class="nav-group-label">{{ area }}</p>
                    <button v-for="module in groupModules" :key="module.slug" class="nav-link" :class="{ active: activeSlug === module.slug }" @click="navigate(module.slug)">
                        <span class="nav-icon">{{ module.name.slice(0, 1) }}</span><span>{{ module.name }}</span>
                        <span v-if="module.delivery === 'Planned'" class="nav-staged" aria-label="Planned">·</span>
                    </button>
                </template>
            </nav>
            <div class="sidebar-bottom">
                <div class="help-card"><span class="help-icon">?</span><div><b>Need a hand?</b><small>Explore the staged roadmap</small></div><span>↗</span></div>
                <button class="user-card" @click="signOut">
                    <span class="avatar avatar-user">{{ initials(user) }}</span><span class="user-meta"><b>{{ user.name }}</b><small>{{ user.role }} · sign out</small></span><span class="chevron">↗</span>
                </button>
            </div>
        </aside>

        <main class="main-area">
            <header class="topbar">
                <div class="breadcrumbs"><span>Workspace</span><span class="crumb-slash">/</span><b>{{ moduleBySlug?.name || 'Dashboard' }}</b></div>
                <div class="top-actions">
                    <label class="global-search"><span>⌕</span><input v-model="employeeSearch" placeholder="Search people and modules" @keyup.enter="searchWorkspace"><kbd>↵</kbd></label>
                    <button class="icon-button notification-button" aria-label="Notifications">♧<i></i></button>
                    <span class="top-divider"></span>
                    <button class="top-profile" @click="signOut"><span class="avatar avatar-user">{{ initials(user) }}</span><span>{{ user.name.split(' ')[0] }}</span><span class="chevron">⌄</span></button>
                </div>
            </header>

            <div class="page-content">
                <div v-if="notice" class="toast toast-success" role="status">{{ notice }}</div>
                <div v-if="error" class="toast toast-error" role="alert">{{ error }}</div>
                <div v-if="loading || busy" class="loading-line"><span></span></div>

                <template v-if="activeSlug === 'dashboard'">
                    <section class="page-heading dashboard-heading">
                        <div><span class="eyebrow">PEOPLE OVERVIEW <span class="dot-separator">•</span> {{ new Date().toLocaleDateString([], { weekday: 'long', month: 'long', day: 'numeric' }) }}</span><h1>Good {{ new Date().getHours() < 12 ? 'morning' : 'afternoon' }}, {{ user.name.split(' ')[0] }}<span class="wave">✳</span></h1><p>Here’s what’s happening across your people today.</p></div>
                        <div class="heading-actions"><button class="button button-soft" @click="navigate('reports')"><span>↗</span> View reports</button><button v-if="user.role === 'hr' || user.role === 'admin'" class="button button-primary" @click="navigate('employees')"><span>＋</span> Add employee</button></div>
                    </section>
                    <section class="metric-grid">
                        <article class="metric-card"><div class="metric-top"><span>ACTIVE PEOPLE</span><span class="metric-icon mint">◉</span></div><div class="metric-value">{{ dashboard?.metrics?.employees ?? '—' }}<span class="metric-unit">people</span></div><div class="metric-caption"><span class="metric-dot green-dot"></span> Across your company scope</div><div class="metric-line line-mint"></div></article>
                        <article class="metric-card"><div class="metric-top"><span>HERE TODAY</span><span class="metric-icon blue">◷</span></div><div class="metric-value">{{ dashboard?.metrics?.attendance_rate ?? '—' }}<span class="metric-unit">%</span></div><div class="metric-caption">From recorded attendance</div><div class="metric-line line-blue"></div></article>
                        <article class="metric-card"><div class="metric-top"><span>ON LEAVE</span><span class="metric-icon violet">◌</span></div><div class="metric-value">{{ dashboard?.metrics?.on_leave ?? '—' }}<span class="metric-unit">people</span></div><div class="metric-caption">Approved leave today</div><div class="metric-line line-violet"></div></article>
                        <article class="metric-card"><div class="metric-top"><span>{{ user.role === 'employee' ? 'MY OPEN REQUESTS' : 'NEEDS YOUR EYES' }}</span><span class="metric-icon amber">⌁</span></div><div class="metric-value">{{ dashboard?.metrics?.pending_approvals ?? '—' }}<span class="metric-unit">requests</span></div><div class="metric-caption">{{ user.role === 'employee' ? 'Waiting for a manager review' : 'Pending leave decisions' }}</div><button class="metric-link" @click="navigate('leave')">{{ user.role === 'employee' ? 'View my requests' : 'Open approvals' }} <span>→</span></button></article>
                    </section>
                    <section class="dashboard-grid">
                        <article class="panel attendance-panel">
                            <div class="panel-heading"><div><span class="eyebrow">TODAY AT A GLANCE</span><h2>Attendance</h2></div><button class="text-link" @click="navigate('attendance')">View register <span>→</span></button></div>
                            <div v-if="dashboard?.attendance_today?.length" class="compact-table">
                                <div v-for="entry in dashboard.attendance_today.slice(0, 5)" :key="entry.id" class="compact-row"><span class="avatar">{{ initials(entry.employee) }}</span><span class="person-cell"><b>{{ fullName(entry.employee) }}</b><small>{{ entry.employee?.employee_code }}</small></span><span class="time-cell">{{ formatTime(entry.check_in) }}</span><span class="status-pill status-present">Present</span></div>
                            </div>
                            <div v-else class="empty-state"><span class="empty-icon">◷</span><b>No check-ins recorded yet</b><p>Attendance will appear here as employees check in.</p><button class="text-link" @click="navigate('attendance')">Open attendance <span>→</span></button></div>
                        </article>
                        <article class="panel approvals-panel">
                            <div class="panel-heading"><div><span class="eyebrow">TEAM WORKFLOW</span><h2>Recent leave requests</h2></div><button class="text-link" @click="navigate('leave')">View all <span>→</span></button></div>
                            <div v-if="dashboard?.recent_leave?.length" class="compact-table">
                                <div v-for="request in dashboard.recent_leave.slice(0, 5)" :key="request.id" class="compact-row"><span class="avatar avatar-soft">{{ initials(request.employee) }}</span><span class="person-cell"><b>{{ fullName(request.employee) }}</b><small>{{ request.leave_type?.name }} · {{ request.requested_days }} days</small></span><span class="status-pill" :class="`status-${request.status}`">{{ request.status }}</span><span class="row-arrow">↗</span></div>
                            </div>
                            <div v-else class="empty-state empty-inline"><span class="empty-icon">✓</span><b>All caught up</b><p>There are no leave requests in your current scope.</p></div>
                        </article>
                    </section>
                    <section class="lower-grid">
                        <article class="panel roadmap-panel"><div class="panel-heading"><div><span class="eyebrow">DELIVERY STATUS</span><h2>Foundation, made useful</h2></div><span class="status-pill status-foundation">4 foundation slices</span></div><p class="panel-copy">People directory, daily attendance, leave requests, and a company-scoped dashboard persist to MySQL. The remaining enterprise modules are explicitly staged and do not simulate transactions.</p><div class="roadmap-steps"><span class="step-done"><i>✓</i> People foundation</span><span><i>2</i> Time & absence</span><span><i>3</i> Compensation & finance</span></div></article>
                        <article class="panel quick-panel"><div class="panel-heading"><div><span class="eyebrow">QUICK ACCESS</span><h2>Your workspace</h2></div></div><button class="quick-link" @click="navigate('employees')"><span class="quick-icon people-icon">♙</span><span><b>People directory</b><small>View scoped employee records</small></span><span>→</span></button><button class="quick-link" @click="navigate('leave')"><span class="quick-icon leave-icon">▣</span><span><b>Leave & approvals</b><small>Requests and live balances</small></span><span>→</span></button></article>
                    </section>
                </template>

                <template v-else-if="activeSlug === 'employees'">
                    <section class="page-heading"><div><span class="eyebrow">PEOPLE <span class="dot-separator">•</span> DIRECTORY</span><h1>People directory</h1><p>Find and manage employees in your authorized company scope.</p></div><button v-if="user.role === 'admin' || user.role === 'hr'" class="button button-primary" @click="showEmployeeForm = !showEmployeeForm"><span>＋</span> Add employee</button></section>
                    <form v-if="showEmployeeForm" class="panel form-panel" @submit.prevent="createEmployee">
                        <div class="panel-heading"><div><span class="eyebrow">NEW RECORD</span><h2>Add an employee</h2></div><button type="button" class="icon-button" aria-label="Close form" @click="showEmployeeForm = false">×</button></div>
                        <div class="form-grid">
                            <label>Employee ID<input v-model="employeeForm.employee_code" required placeholder="GTM-0024"></label>
                            <label>First name<input v-model="employeeForm.first_name" required autocomplete="given-name"></label>
                            <label>Last name<input v-model="employeeForm.last_name" required autocomplete="family-name"></label>
                            <label>Work email<input v-model="employeeForm.work_email" type="email"></label>
                            <label>Job title<input v-model="employeeForm.job_title" required></label>
                            <label>Department<input v-model="employeeForm.department" required></label>
                            <label>Employment type<select v-model="employeeForm.employment_type"><option>Full-time</option><option>Part-time</option><option>Contract</option><option>Temporary</option></select></label>
                            <label>Start date<input v-model="employeeForm.join_date" type="date" required></label>
                        </div>
                        <div class="form-actions"><span class="muted">Changes are saved to the company’s employee register.</span><button class="button button-primary" :disabled="busy">Save employee <span>→</span></button></div>
                    </form>
                    <section class="panel directory-panel">
                        <div class="directory-toolbar"><div><h2>Employee register</h2><span class="muted">{{ filteredEmployees.length }} visible record{{ filteredEmployees.length === 1 ? '' : 's' }}</span></div><label class="table-search"><span>⌕</span><input v-model="employeeSearch" placeholder="Name, ID, department" @input="loadActive"></label></div>
                        <div class="table-scroll"><table><thead><tr><th>EMPLOYEE</th><th>ROLE</th><th>DEPARTMENT</th><th>LOCATION</th><th>START DATE</th><th>STATUS</th></tr></thead><tbody>
                            <tr v-for="employee in filteredEmployees" :key="employee.id"><td><div class="table-person"><span class="avatar">{{ initials(employee) }}</span><span><b>{{ fullName(employee) }}</b><small>{{ employee.employee_code }} · {{ employee.work_email || 'No work email' }}</small></span></div></td><td>{{ employee.job_title || '—' }}</td><td>{{ employee.department || '—' }}</td><td>{{ employee.branch?.name || '—' }}</td><td>{{ formatDate(employee.join_date) }}</td><td><span class="status-pill status-present">{{ employee.status }}</span></td></tr>
                        </tbody></table></div>
                        <div v-if="!filteredEmployees.length" class="empty-state"><span class="empty-icon">♙</span><b>No employee records found</b><p>Try a different search or add a new employee.</p></div>
                    </section>
                </template>

                <template v-else-if="activeSlug === 'attendance'">
                    <section class="page-heading attendance-heading">
                        <div><span class="eyebrow">TIME & ABSENCE <span class="dot-separator">•</span> PUNCHES & HOURS</span><h1>Attendance</h1><p>Understand daily coverage, review workday punches, and export an accurate scoped register.</p></div>
                        <button v-if="user.employee" class="button button-primary attendance-punch-button" :disabled="busy || user.employee.status !== 'active' || Boolean(employeeAttendance?.check_out)" @click="attendanceAction"><span>◷</span>{{ employeeAttendance?.check_in && !employeeAttendance?.check_out ? 'Check out' : employeeAttendance?.check_out ? 'Day complete' : 'Check in' }}</button>
                    </section>

                    <section class="attendance-metrics" aria-label="Attendance summary">
                        <article class="attendance-metric"><span class="attendance-metric-icon attendance-icon-records">◷</span><div><span class="eyebrow">RECORDED PUNCHES</span><b>{{ attendanceReport.metrics.entries }}</b><small>{{ attendanceReport.meta.total }} in this view</small></div></article>
                        <article class="attendance-metric"><span class="attendance-metric-icon attendance-icon-open">◉</span><div><span class="eyebrow">OPEN SHIFTS</span><b>{{ attendanceReport.metrics.working }}</b><small>Waiting for check-out</small></div></article>
                        <article class="attendance-metric"><span class="attendance-metric-icon attendance-icon-complete">✓</span><div><span class="eyebrow">COMPLETED SHIFTS</span><b>{{ attendanceReport.metrics.completed }}</b><small>Average {{ formatHours(attendanceReport.metrics.average_hours) }}</small></div></article>
                        <article class="attendance-metric attendance-metric-accent"><span class="attendance-metric-icon attendance-icon-hours">⌁</span><div><span class="eyebrow">HOURS TRACKED</span><b>{{ formatHours(attendanceReport.metrics.hours) }}</b><small>From completed shifts</small></div></article>
                    </section>

                    <section class="attendance-layout">
                        <article class="panel attendance-register-panel">
                            <div class="attendance-toolbar">
                                <div class="attendance-register-title"><span class="eyebrow">LIVE TIME REGISTER</span><h2>Workday punches</h2><p>{{ attendanceReport.meta.total }} records · {{ formatDate(attendanceReport.meta.from) }} – {{ formatDate(attendanceReport.meta.to) }}</p></div>
                                <div class="attendance-controls">
                                    <label class="attendance-search"><span>⌕</span><input v-model.trim="attendanceSearch" aria-label="Search attendance" placeholder="Name, ID, or department"></label>
                                    <label class="attendance-date"><span>From</span><input v-model="attendanceFrom" type="date" :max="attendanceTo" aria-label="From date"></label>
                                    <label class="attendance-date"><span>To</span><input v-model="attendanceTo" type="date" :min="attendanceFrom" aria-label="To date"></label>
                                    <select v-model="attendanceStatusFilter" class="attendance-status-filter" aria-label="Filter shift status"><option value="all">All shifts</option><option value="working">Open shifts</option><option value="completed">Completed</option></select>
                                    <button class="button button-soft attendance-reset" @click="resetAttendanceFilters">Reset</button>
                                    <button class="button button-soft attendance-export" :disabled="attendanceExporting || !attendanceReport.meta.total" @click="exportAttendanceCsv">{{ attendanceExporting ? 'Preparing…' : 'Export CSV' }} <span>↓</span></button>
                                </div>
                            </div>
                            <div class="table-scroll attendance-table-scroll"><table><thead><tr><th>EMPLOYEE</th><th>WORK DATE</th><th>CHECK IN</th><th>CHECK OUT</th><th>DURATION</th><th>SHIFT STATUS</th></tr></thead><tbody>
                                <tr v-for="entry in attendance" :key="entry.id">
                                    <td><div class="table-person"><span class="avatar">{{ initials(entry.employee) }}</span><span><b>{{ fullName(entry.employee) }}</b><small>{{ entry.employee?.employee_code }} · {{ entry.employee?.department || 'Unassigned team' }}</small></span></div></td>
                                    <td>{{ formatDate(entry.attendance_date) }}</td><td>{{ formatTime(entry.check_in) }}</td><td>{{ formatTime(entry.check_out) }}</td>
                                    <td class="attendance-duration">{{ formatShiftDuration(entry.check_in, entry.check_out) }}</td>
                                    <td><span class="status-pill" :class="entry.check_out ? 'status-present' : 'status-pending'">{{ entry.check_out ? 'Completed' : 'Open shift' }}</span></td>
                                </tr>
                            </tbody></table></div>
                            <div v-if="!attendance.length" class="empty-state attendance-empty"><span class="empty-icon">◷</span><b>{{ attendanceReport.meta.total ? 'No records on this page' : 'No punches for this view' }}</b><p>{{ attendanceReport.meta.total ? 'Use the filters to adjust the register.' : 'Try widening the date range or removing a status/search filter.' }}</p><button v-if="attendanceSearch || attendanceStatusFilter !== 'all'" class="button button-soft" @click="resetAttendanceFilters">Clear filters</button></div>
                            <div v-if="attendanceReport.meta.current_page < attendanceReport.meta.last_page" class="attendance-load-more"><span>Showing {{ attendance.length }} of {{ attendanceReport.meta.total }} matching records</span><button class="button button-soft" :disabled="busy" @click="loadMoreAttendance">{{ busy ? 'Loading…' : 'Load more records' }} <span>↓</span></button></div>
                        </article>

                        <aside class="panel attendance-trend-panel">
                            <div class="attendance-trend-heading"><span class="eyebrow">DAILY COVERAGE</span><h2>Attendance activity</h2><p>Recorded punches within the selected date range.</p></div>
                            <div class="attendance-trend-list">
                                <div v-for="day in attendanceReport.trend" :key="day.date" class="attendance-trend-row"><span class="attendance-trend-date">{{ new Date(`${day.date}T12:00:00`).toLocaleDateString([], { month: 'short', day: 'numeric' }) }}<small>{{ day.label }}</small></span><span class="attendance-trend-track"><i :style="{ width: attendanceTrendWidth(day.entries) }"></i></span><b>{{ day.entries }}</b></div>
                            </div>
                            <div class="attendance-trend-legend"><span><i></i>Recorded punches per day</span></div>
                            <div class="attendance-today-card"><span class="attendance-today-icon">◷</span><div><b>{{ employeeAttendance?.check_out ? 'Your shift is complete' : employeeAttendance?.check_in ? 'You are currently checked in' : 'Your day has not started' }}</b><small>{{ employeeAttendance?.check_in ? `${formatTime(employeeAttendance.check_in)}${employeeAttendance.check_out ? ` – ${formatTime(employeeAttendance.check_out)}` : ' · Check out when you finish'}` : 'Your personal punch status for today' }}</small></div></div>
                            <p class="attendance-boundary-note"><span>ⓘ</span> Device, GPS, scheduled shifts, and overtime rules are not connected. Hours reflect punch times only.</p>
                        </aside>
                    </section>
                </template>

                <template v-else-if="activeSlug === 'leave'">
                    <section class="page-heading"><div><span class="eyebrow">TIME & ABSENCE <span class="dot-separator">•</span> REQUESTS & BALANCES</span><h1>Leave management</h1><p>Request time away and review decisions in your authorized team scope.</p></div></section>
                    <section class="leave-layout">
                        <div class="leave-main">
                            <div class="panel"><div class="panel-heading"><div><span class="eyebrow">AVAILABLE THIS YEAR</span><h2>My leave balance</h2></div><span class="status-pill status-foundation">{{ new Date().getFullYear() }}</span></div><div v-if="leaveData.balances.length" class="balance-grid"><div v-for="balance in leaveData.balances" :key="balance.id" class="balance-card"><div><span>{{ balance.leave_type?.name }}</span><b>{{ Math.max(0, Number(balance.entitled_days) - Number(balance.used_days)) }} <small>days left</small></b></div><div class="balance-track"><span :style="{ width: `${Math.min(100, Number(balance.entitled_days) ? Number(balance.used_days) / Number(balance.entitled_days) * 100 : 0)}%` }"></span></div><small>{{ balance.used_days }} used of {{ balance.entitled_days }} days</small></div></div><div v-else class="empty-inline"><p>No leave balance is configured for this account and year.</p></div></div>
                            <div class="panel requests-panel"><div class="directory-toolbar"><div><h2>Requests</h2><span class="muted">{{ leaveData.requests.length }} recent records</span></div><span v-if="isApprover && leaveData.requests.some((request) => request.status === 'pending')" class="status-pill status-pending">{{ leaveData.requests.filter((request) => request.status === 'pending').length }} pending</span></div><div v-if="leaveData.requests.length" class="request-list"><article v-for="request in leaveData.requests" :key="request.id" class="request-card"><span class="avatar">{{ initials(request.employee) }}</span><div class="request-details"><b>{{ fullName(request.employee) }}</b><span>{{ request.leave_type?.name }} · {{ request.requested_days }} working days</span><small>{{ formatDate(request.start_date) }} — {{ formatDate(request.end_date) }} · {{ request.reason }}</small></div><span class="status-pill" :class="`status-${request.status}`">{{ request.status }}</span><div v-if="isApprover && request.status === 'pending' && request.employee_id !== user.employee?.id" class="request-actions"><button class="button button-approve" :disabled="busy" @click="reviewLeave(request, 'approved')">Approve</button><button class="button button-decline" :disabled="busy" @click="reviewLeave(request, 'rejected')">Decline</button></div></article></div><div v-else class="empty-state"><span class="empty-icon">▣</span><b>No leave requests yet</b><p>Submitted requests will appear here.</p></div></div>
                        </div>
                        <form class="panel leave-form" @submit.prevent="createLeaveRequest"><div class="panel-heading"><div><span class="eyebrow">TIME OFF</span><h2>New request</h2></div><span class="form-icon">＋</span></div><label>Leave type<select v-model="leaveForm.leave_type_id" required><option value="" disabled>Select leave type</option><option v-for="type in leaveData.types" :key="type.id" :value="type.id">{{ type.name }}</option></select></label><div class="form-grid form-grid-two"><label>From<input v-model="leaveForm.start_date" type="date" required></label><label>To<input v-model="leaveForm.end_date" type="date" required></label></div><label>Reason<textarea v-model="leaveForm.reason" rows="4" required placeholder="Share a little context for your manager…"></textarea></label><p class="form-helper">Weekdays are counted; public holidays and custom leave rules are not configured in this foundation.</p><button class="button button-primary button-wide" :disabled="busy || !leaveData.types.length">Submit request <span>→</span></button></form>
                    </section>
                </template>

                <template v-else-if="activeSlug === 'organization'">
                    <section class="page-heading module-heading organization-heading">
                        <div>
                            <span class="eyebrow">PEOPLE <span class="dot-separator">•</span> ORGANIZATION INTELLIGENCE</span>
                            <h1>{{ organization.company?.name || 'Organization management' }}</h1>
                            <p>Design your teams, keep reporting lines clear, and plan the roles your business needs next.</p>
                        </div>
                        <span class="status-pill status-foundation"><i></i> Live organization data</span>
                    </section>

                    <section class="organization-metrics">
                        <article class="organization-metric"><span class="org-metric-icon org-icon-people">♙</span><div><span class="eyebrow">ACTIVE PEOPLE</span><b>{{ organization.metrics.employees ?? 0 }}</b><small>In your authorized scope</small></div></article>
                        <article class="organization-metric"><span class="org-metric-icon org-icon-departments">▦</span><div><span class="eyebrow">DEPARTMENTS</span><b>{{ organization.metrics.departments ?? 0 }}</b><small>Active teams</small></div></article>
                        <article class="organization-metric"><span class="org-metric-icon org-icon-branches">⌖</span><div><span class="eyebrow">LOCATIONS</span><b>{{ organization.metrics.branches ?? 0 }}</b><small>Branches in scope</small></div></article>
                        <article class="organization-metric organization-metric-accent"><span class="org-metric-icon org-icon-positions">＋</span><div><span class="eyebrow">OPEN CAPACITY</span><b>{{ organization.metrics.open_positions ?? 0 }}</b><small>Planned seats to fill</small></div></article>
                    </section>

                    <nav class="organization-tabs" aria-label="Organization workspace">
                        <button v-for="tab in [{ id: 'overview', label: 'Overview' }, { id: 'departments', label: 'Departments' }, { id: 'positions', label: 'Positions' }, { id: 'reporting', label: 'Reporting lines' }]" :key="tab.id" type="button" :class="{ active: organizationTab === tab.id }" @click="organizationTab = tab.id">
                            {{ tab.label }}<span v-if="tab.id === 'departments'">{{ organization.departments.length }}</span><span v-else-if="tab.id === 'positions'">{{ organization.positions.length }}</span><span v-else-if="tab.id === 'reporting'">{{ organization.employees.length }}</span>
                        </button>
                        <label class="table-search organization-search"><span>⌕</span><input v-model="organizationSearch" aria-label="Search organization" placeholder="Search teams or roles"></label>
                    </nav>

                    <template v-if="organizationTab === 'overview'">
                        <section class="organization-overview-grid">
                            <article class="panel organization-departments-panel">
                                <div class="panel-heading"><div><span class="eyebrow">YOUR ORGANIZATION</span><h2>Teams at a glance</h2></div><button class="text-link" @click="organizationTab = 'departments'">Manage departments <span>→</span></button></div>
                                <div v-if="organization.departments.length" class="organization-department-list">
                                    <button v-for="department in organization.departments.slice(0, 6)" :key="department.id" type="button" class="organization-department-card" @click="organizationTab = 'departments'">
                                        <span class="department-mark">{{ department.name.slice(0, 1) }}</span>
                                        <span class="department-card-copy"><b>{{ department.name }}</b><small>{{ department.manager ? fullName(department.manager) : 'Department lead not assigned' }} · {{ department.branch?.name || 'Company-wide' }}</small></span>
                                        <span class="department-people-count">{{ department.employees_count }}<small>{{ department.employees_count === 1 ? 'person' : 'people' }}</small></span>
                                        <span class="row-arrow">↗</span>
                                    </button>
                                    <div v-if="organization.departments.length > 6" class="organization-list-foot">{{ organization.departments.length - 6 }} more teams in your directory</div>
                                </div>
                                <div v-else class="empty-state"><span class="empty-icon">▦</span><b>Start with your first department</b><p>Build a structure that reflects how your teams work.</p><button v-if="canManageOrganization" class="button button-primary" @click="organizationTab = 'departments'; showDepartmentForm = true">Create a department</button></div>
                            </article>
                            <article class="panel organization-chart-panel">
                                <div class="panel-heading"><div><span class="eyebrow">PEOPLE & REPORTING</span><h2>Reporting structure</h2></div><button class="text-link" @click="organizationTab = 'reporting'">Open org chart <span>→</span></button></div>
                                <div v-if="organizationTreeRows.length" class="organization-tree-preview">
                                    <div v-for="person in organizationTreeRows.slice(0, 5)" :key="person.id" class="organization-tree-row" :style="{ '--org-depth': Math.min(person.depth, 4) }">
                                        <span class="avatar">{{ initials(person) }}</span><span class="org-tree-person"><b>{{ fullName(person) }}</b><small>{{ person.job_title || person.position?.title || 'Role not set' }} · {{ person.department_record?.name || person.department || 'Unassigned' }}</small></span><span class="org-report-count">{{ person.reports_count }}<small>reports</small></span>
                                    </div>
                                    <button class="organization-chart-link" @click="organizationTab = 'reporting'">Explore all {{ organization.employees.length }} people <span>→</span></button>
                                </div>
                                <div v-else class="empty-state"><span class="empty-icon">♙</span><b>No people in this scope</b><p>Employees will appear here as your organization is populated.</p></div>
                            </article>
                        </section>
                        <section class="panel organization-capacity-panel">
                            <div class="panel-heading"><div><span class="eyebrow">WORKFORCE PLANNING</span><h2>Open roles, ready for your next hire</h2></div><button class="text-link" @click="organizationTab = 'positions'">View position plan <span>→</span></button></div>
                            <div v-if="organization.positions.some((position) => position.status === 'open' && position.filled_count < position.headcount)" class="organization-open-roles">
                                <article v-for="position in organization.positions.filter((role) => role.status === 'open' && role.filled_count < role.headcount).slice(0, 4)" :key="position.id" class="organization-role-card">
                                    <span class="role-symbol">＋</span><div><b>{{ position.title }}</b><small>{{ position.department?.name }} · {{ position.branch?.name || 'Company-wide' }}</small></div><span class="role-vacancy">{{ Math.max(0, position.headcount - position.filled_count) }} open</span>
                                </article>
                            </div>
                            <div v-else class="organization-capacity-empty"><span class="status-pill status-present">On plan</span><span>No open seats right now. Add a position when your hiring plan changes.</span><button v-if="canManageOrganization" class="text-link" @click="organizationTab = 'positions'; showPositionForm = true">Plan a position <span>→</span></button></div>
                        </section>
                    </template>

                    <template v-else-if="organizationTab === 'departments'">
                        <section class="panel directory-panel organization-directory">
                            <div class="directory-toolbar">
                                <div><h2>Departments & teams</h2><span class="muted">{{ filteredDepartments.length }} of {{ organization.departments.length }} departments · structure, ownership, and cost center</span></div>
                                <button v-if="canManageOrganization" class="button button-primary" @click="showDepartmentForm = !showDepartmentForm; showDepartmentForm || resetDepartmentForm()"><span>＋</span> Add department</button>
                            </div>
                            <form v-if="showDepartmentForm && canManageOrganization" class="organization-form" @submit.prevent="saveDepartment">
                                <div class="organization-form-heading"><div><span class="eyebrow">{{ editingDepartmentId ? 'UPDATE YOUR STRUCTURE' : 'BUILD YOUR STRUCTURE' }}</span><h3>{{ editingDepartmentId ? 'Edit department' : 'Create a department' }}</h3></div><button type="button" class="icon-button" aria-label="Close department form" @click="showDepartmentForm = false; resetDepartmentForm()">×</button></div>
                                <div class="organization-form-grid">
                                    <label>Department name<input v-model.trim="departmentForm.name" required maxlength="120" placeholder="e.g. Retail Operations"></label>
                                    <label>Short code<input v-model.trim="departmentForm.code" required maxlength="32" placeholder="e.g. RETAIL"></label>
                                    <label>Location<select v-model="departmentForm.branch_id"><option value="">Company-wide</option><option v-for="branch in organization.branches" :key="branch.id" :value="String(branch.id)">{{ branch.name }}</option></select></label>
                                    <label>Parent department<select v-model="departmentForm.parent_id"><option value="">Top-level department</option><option v-for="department in organization.departments.filter((entry) => entry.id !== editingDepartmentId)" :key="department.id" :value="String(department.id)">{{ department.name }}</option></select></label>
                                    <label>Department lead<select v-model="departmentForm.manager_employee_id"><option value="">Not assigned</option><option v-for="person in organization.employees" :key="person.id" :value="String(person.id)">{{ fullName(person) }} · {{ person.employee_code }}</option></select></label>
                                    <label>Cost center<input v-model.trim="departmentForm.cost_center" maxlength="32" placeholder="e.g. CC-120"></label>
                                    <label v-if="editingDepartmentId">Status<select v-model="departmentForm.status"><option value="active">Active</option><option value="inactive">Inactive</option></select></label>
                                </div>
                                <div class="form-actions"><span class="muted">Department ownership and branch boundaries are validated when saved.</span><button class="button button-primary" :disabled="busy">{{ editingDepartmentId ? 'Save changes' : 'Create department' }} <span>→</span></button></div>
                            </form>
                            <div class="table-scroll"><table><thead><tr><th>DEPARTMENT</th><th>LEAD</th><th>PARENT</th><th>LOCATION</th><th>PEOPLE</th><th>STATUS</th><th v-if="canManageOrganization">ACTIONS</th></tr></thead><tbody>
                                <tr v-for="department in filteredDepartments" :key="department.id">
                                    <td><div class="table-person"><span class="department-mark department-mark-small">{{ department.name.slice(0, 1) }}</span><span><b>{{ department.name }}</b><small>{{ department.code }}<template v-if="department.cost_center"> · {{ department.cost_center }}</template></small></span></div></td>
                                    <td>{{ department.manager ? fullName(department.manager) : '—' }}</td><td>{{ department.parent?.name || 'Top level' }}</td><td>{{ department.branch?.name || 'Company-wide' }}</td><td><span class="status-pill status-foundation">{{ department.employees_count }} {{ department.employees_count === 1 ? 'person' : 'people' }}</span></td><td><span class="status-pill" :class="department.status === 'active' ? 'status-present' : 'status-planned'">{{ department.status }}</span></td>
                                    <td v-if="canManageOrganization"><div class="organization-row-actions"><button class="button button-soft organization-action-button" @click="editDepartment(department)">Edit</button><button class="button button-soft organization-action-button" @click="toggleDepartmentStatus(department)">{{ department.status === 'active' ? 'Archive' : 'Activate' }}</button></div></td>
                                </tr>
                            </tbody></table></div>
                            <div v-if="!filteredDepartments.length" class="empty-state"><span class="empty-icon">▦</span><b>{{ organizationSearch ? 'No departments match your search' : 'Your organization starts here' }}</b><p>{{ organizationSearch ? 'Try another name, code, or cost center.' : 'Create departments to organize teams, managers, and workforce plans.' }}</p><button v-if="canManageOrganization && !organizationSearch" class="button button-primary" @click="showDepartmentForm = true">Add your first department</button></div>
                        </section>
                    </template>

                    <template v-else-if="organizationTab === 'positions'">
                        <section class="organization-positions-intro"><div><span class="eyebrow">CAPACITY & HIRING PLAN</span><h2>Make room for the right people.</h2><p>Planned headcount stays separate from filled seats, so each opening is visible.</p></div><button v-if="canManageOrganization" class="button button-primary" @click="showPositionForm = !showPositionForm"><span>＋</span> Plan a position</button></section>
                        <form v-if="showPositionForm && canManageOrganization" class="panel organization-form organization-position-form" @submit.prevent="createPosition">
                            <div class="organization-form-heading"><div><span class="eyebrow">WORKFORCE PLAN</span><h3>Add a planned position</h3></div><button type="button" class="icon-button" aria-label="Close position form" @click="showPositionForm = false">×</button></div>
                            <div class="organization-form-grid">
                                <label>Position title<input v-model.trim="positionForm.title" required maxlength="150" placeholder="e.g. Production planner"></label>
                                <label>Position code<input v-model.trim="positionForm.code" required maxlength="32" placeholder="e.g. OPS-PLN-01"></label>
                                <label>Department<select v-model="positionForm.department_id" required><option value="" disabled>Select a department</option><option v-for="department in organization.departments.filter((entry) => entry.status === 'active')" :key="department.id" :value="String(department.id)">{{ department.name }}</option></select></label>
                                <label>Location<select v-model="positionForm.branch_id"><option value="">Company-wide</option><option v-for="branch in organization.branches" :key="branch.id" :value="String(branch.id)">{{ branch.name }}</option></select></label>
                                <label>Reports to position<select v-model="positionForm.reports_to_position_id"><option value="">No position selected</option><option v-for="position in organization.positions.filter((entry) => entry.status !== 'closed')" :key="position.id" :value="String(position.id)">{{ position.title }} · {{ position.code }}</option></select></label>
                                <label>Grade / level<input v-model.trim="positionForm.grade" maxlength="40" placeholder="e.g. P3"></label>
                                <label>Employment type<select v-model="positionForm.employment_type"><option>Full-time</option><option>Part-time</option><option>Contract</option><option>Temporary</option></select></label>
                                <label>Planned seats<input v-model.number="positionForm.headcount" type="number" min="1" max="500" required></label>
                            </div>
                            <div class="form-actions"><span class="muted">This is a workforce plan, not a published vacancy.</span><button class="button button-primary" :disabled="busy || !organization.departments.some((entry) => entry.status === 'active')">Save planned position <span>→</span></button></div>
                        </form>
                        <section v-if="!organization.positions.length" class="panel empty-state organization-empty-positions"><span class="empty-icon">＋</span><b>No planned positions yet</b><p>Create a position to track planned seats, current assignments, and team capacity.</p><button v-if="canManageOrganization && organization.departments.length" class="button button-primary" @click="showPositionForm = true">Plan your first position</button><button v-else-if="canManageOrganization" class="text-link" @click="organizationTab = 'departments'; showDepartmentForm = true">Create a department first <span>→</span></button></section>
                        <section v-else class="organization-position-grid">
                            <article v-for="position in filteredPositions" :key="position.id" class="panel organization-position-card">
                                <div class="position-card-top"><span class="position-icon">⌘</span><span class="status-pill" :class="position.status === 'open' ? 'status-present' : 'status-planned'">{{ position.status }}</span></div>
                                <span class="eyebrow">{{ position.code }}<template v-if="position.grade"> · {{ position.grade }}</template></span>
                                <h3>{{ position.title }}</h3><p>{{ position.department?.name }} · {{ position.branch?.name || 'Company-wide' }}</p>
                                <div class="position-capacity"><div><span>SEATS FILLED</span><b>{{ position.filled_count }} <small>/ {{ position.headcount }}</small></b></div><div class="capacity-track"><span :style="{ width: `${Math.min(100, position.headcount ? position.filled_count / position.headcount * 100 : 0)}%` }"></span></div><small>{{ Math.max(0, position.headcount - position.filled_count) }} open {{ Math.max(0, position.headcount - position.filled_count) === 1 ? 'seat' : 'seats' }}</small></div>
                                <div v-if="canManageOrganization && position.status === 'open' && position.filled_count < position.headcount" class="position-assignment">
                                    <label :for="`assign-${position.id}`">Assign an employee</label><div><select :id="`assign-${position.id}`" v-model="assignmentDrafts[position.id]"><option value="">Choose a person</option><option v-for="person in organization.employees" :key="person.id" :value="String(person.id)">{{ fullName(person) }} · {{ person.employee_code }}</option></select><button class="button button-primary" :disabled="busy || !assignmentDrafts[position.id]" @click="assignPosition(position)">Assign</button></div>
                                </div>
                                <div v-if="organization.employees.some((person) => person.position_id === position.id)" class="position-assignees"><span class="eyebrow">ASSIGNED TEAM</span><div v-for="person in organization.employees.filter((entry) => entry.position_id === position.id)" :key="person.id" class="position-assignee"><span class="avatar">{{ initials(person) }}</span><span><b>{{ fullName(person) }}</b><small>{{ person.employee_code }}</small></span></div></div>
                            </article>
                            <div v-if="!filteredPositions.length" class="empty-state panel organization-empty-positions"><span class="empty-icon">⌕</span><b>No positions match your search</b><p>Try a different title, department, or position code.</p></div>
                        </section>
                    </template>




                    <template v-else>
                        <section class="panel reporting-panel">
                            <div class="reporting-panel-heading"><div><span class="eyebrow">LIVE COMPANY HIERARCHY</span><h2>People, connected to their teams.</h2><p>Update a reporting line and the saved org chart refreshes from the employee register.</p></div><span class="reporting-count">{{ organizationTreeRows.length }}<small>people</small></span></div>
                            <div v-if="organizationTreeRows.length" class="reporting-tree">
                                <div v-for="person in organizationTreeRows" :key="person.id" class="reporting-tree-row" :class="{ 'reporting-tree-root': !person.depth }" :style="{ '--org-depth': Math.min(person.depth, 4) }">
                                    <span class="tree-connector" aria-hidden="true"></span><span class="avatar reporting-avatar">{{ initials(person) }}</span>
                                    <span class="reporting-person"><b>{{ fullName(person) }}</b><small>{{ person.employee_code }} · {{ person.job_title || person.position?.title || 'Role not set' }}</small></span>
                                    <span class="reporting-department">{{ person.department_record?.name || person.department || 'Unassigned team' }}</span>
                                    <span class="reporting-manager-name">{{ person.manager ? `Reports to ${fullName(person.manager)}` : 'Reports to company' }}</span>
                                    <div v-if="canManageOrganization" class="reporting-editor"><label class="visually-hidden" :for="`manager-${person.id}`">Reports to for {{ fullName(person) }}</label><select :id="`manager-${person.id}`" v-model="managerDrafts[person.id]"><option value="">Top-level</option><option v-for="candidate in organization.employees.filter((entry) => entry.id !== person.id && entry.branch_id === person.branch_id)" :key="candidate.id" :value="String(candidate.id)">{{ fullName(candidate) }}</option></select><button class="button button-soft organization-action-button" :disabled="busy || String(managerDrafts[person.id] || '') === String(person.manager_id || '')" @click="saveReportingLine(person)">Save</button></div>
                                </div>
                            </div>
                            <div v-else class="empty-state"><span class="empty-icon">♙</span><b>No employees are available to map</b><p>Add employees to start building your reporting structure.</p><button v-if="canManageOrganization" class="button button-primary" @click="navigate('employees')">Open employee directory <span>→</span></button></div>
                            <div v-if="organization.employees.length" class="organization-tree-note"><span>ⓘ</span><p>Reporting lines are branch-scoped and circular hierarchies are rejected. Employee visibility remains governed by your role and company access.</p></div>
                        </section>
                    </template>
                </template>

                <template v-else-if="['recruitment', 'shifts-rosters'].includes(activeSlug)">
                        <section v-if="activeSlug === 'recruitment'" class="page-heading recruitment-heading">
                            <div>
                                <span class="eyebrow">PEOPLE <span class="dot-separator">•</span> TALENT ACQUISITION</span>
                                <h1>Recruitment &amp; hiring</h1>
                                <p>Turn approved headcount into great hires with one clear, private pipeline.</p>
                            </div>
                            <div class="recruitment-heading-actions">
                                <span class="status-pill status-foundation"><i></i> Live recruiting workspace</span>
                                <button class="button button-primary" @click="openRequisitionForm"><span>＋</span> Create requisition</button>
                            </div>
                        </section>

                        <section v-if="activeSlug === 'recruitment'" class="recruitment-metrics">
                            <article class="recruitment-metric"><span class="recruitment-metric-icon mint">▤</span><div><span class="eyebrow">OPEN REQUISITIONS</span><b>{{ recruitment.metrics.open_requisitions ?? 0 }}</b><small>{{ recruitment.metrics.openings ?? 0 }} planned seats</small></div></article>
                            <article class="recruitment-metric"><span class="recruitment-metric-icon blue">♙</span><div><span class="eyebrow">ACTIVE CANDIDATES</span><b>{{ recruitment.metrics.active_candidates ?? 0 }}</b><small>Across your open pipeline</small></div></article>
                            <article class="recruitment-metric"><span class="recruitment-metric-icon amber">◷</span><div><span class="eyebrow">IN INTERVIEW</span><b>{{ recruitment.metrics.interviews ?? 0 }}</b><small>Ready for a decision</small></div></article>
                            <article class="recruitment-metric"><span class="recruitment-metric-icon green">✓</span><div><span class="eyebrow">HIRED THIS MONTH</span><b>{{ recruitment.metrics.hires_this_month ?? 0 }}</b><small>Converted to employees</small></div></article>
                        </section>

                        <nav v-if="activeSlug === 'recruitment'" class="recruitment-tabs" aria-label="Recruitment workspace">
                            <button v-for="tab in [{ id: 'overview', label: 'Overview' }, { id: 'requisitions', label: 'Requisitions' }, { id: 'pipeline', label: 'Talent pipeline' }]" :key="tab.id" type="button" :class="{ active: recruitmentTab === tab.id }" @click="recruitmentTab = tab.id">
                                {{ tab.label }}<span v-if="tab.id === 'requisitions'">{{ recruitment.requisitions.length }}</span><span v-else-if="tab.id === 'pipeline'">{{ recruitment.metrics.active_candidates ?? 0 }}</span>
                            </button>
                            <label class="table-search recruitment-search"><span>⌕</span><input v-model="recruitmentSearch" aria-label="Search requisitions and candidates" placeholder="Search roles or candidates"></label>
                            <button v-if="recruitmentTab === 'pipeline'" class="button button-soft recruitment-export" :disabled="!filteredRecruitmentCandidates.length" @click="exportRecruitmentCsv"><span>↓</span> Export visible</button>
                        </nav>

                        <template v-if="activeSlug === 'recruitment' && recruitmentTab === 'overview'">
                            <section class="recruitment-overview-grid">
                                <article class="panel recruitment-funnel-panel">
                                    <div class="panel-heading">
                                        <div><span class="eyebrow">LIVE TALENT FUNNEL</span><h2>Every candidate, one step forward.</h2><p class="recruitment-panel-copy">A clear view of your team's current hiring momentum.</p></div>
                                        <button class="text-link" @click="recruitmentTab = 'pipeline'">Open pipeline <span>→</span></button>
                                    </div>
                                    <div class="recruitment-funnel">
                                        <div v-for="stage in recruitmentStages.slice(0, 4)" :key="stage.id" class="recruitment-funnel-row">
                                            <span class="funnel-stage-icon">{{ stage.icon }}</span>
                                            <span class="funnel-stage-name">{{ stage.label }}</span>
                                            <span class="funnel-track"><i :style="{ width: `${Math.min(100, (recruitment.pipeline[stage.id] || 0) / Math.max(1, recruitment.metrics.active_candidates || 0) * 100)}%` }"></i></span>
                                            <b>{{ recruitment.pipeline[stage.id] || 0 }}</b>
                                        </div>
                                        <div class="recruitment-funnel-foot"><span>Offer accepted becomes a live employee record</span><span>{{ recruitment.pipeline.hired || 0 }} total hires</span></div>
                                    </div>
                                </article>
                                <article class="panel recruitment-focus-panel">
                                    <span class="eyebrow">YOUR NEXT BEST ACTION</span>
                                    <div class="recruitment-focus-mark">↗</div>
                                    <h2>{{ openRecruitmentRequisitions.length ? 'Keep great candidates moving.' : 'Make your next role count.' }}</h2>
                                    <p>{{ openRecruitmentRequisitions.length ? 'Review interview-stage candidates, record feedback, and move a promising match to offer.' : 'Start by opening a role with a clear owner, team, and target date.' }}</p>
                                    <button v-if="openRecruitmentRequisitions.length" class="button button-primary" @click="recruitmentTab = 'pipeline'; recruitmentStageFilter = 'interview'">Review interviews <span>→</span></button>
                                    <button v-else class="button button-primary" @click="openRequisitionForm">Create your first requisition <span>→</span></button>
                                    <div class="recruitment-focus-foot"><span class="privacy-lock">⌑</span> Candidate information is visible to HR and administrators only.</div>
                                </article>
                            </section>

                            <section class="panel recruitment-recent-panel">
                                <div class="directory-toolbar">
                                    <div><h2>Roles in motion</h2><span class="muted">Your latest requisitions and the people behind them</span></div>
                                    <button class="text-link" @click="recruitmentTab = 'requisitions'">All requisitions <span>→</span></button>
                                </div>
                                <div v-if="recruitment.requisitions.length" class="table-scroll">
                                    <table class="recruitment-table"><thead><tr><th>ROLE</th><th>TEAM &amp; LOCATION</th><th>PIPELINE</th><th>OPENINGS</th><th>STATUS</th><th></th></tr></thead>
                                        <tbody><tr v-for="requisition in recruitment.requisitions.slice(0, 5)" :key="requisition.id">
                                            <td><div class="recruitment-role-cell"><span class="recruitment-role-mark">{{ requisition.title.slice(0, 1) }}</span><span><b>{{ requisition.title }}</b><small>{{ requisition.code }} · {{ requisition.employment_type }}</small></span></div></td>
                                            <td><b class="recruitment-table-primary">{{ requisition.department?.name || 'Company-wide' }}</b><small class="recruitment-table-secondary">{{ requisition.branch?.name || requisition.location || 'All locations' }}</small></td>
                                            <td><span class="recruitment-candidate-count">{{ requisition.candidates_count }}</span><small class="recruitment-table-secondary">candidates</small></td>
                                            <td><span class="recruitment-openings">{{ recruitment.candidates.filter((candidate) => candidate.requisition_id === requisition.id && candidate.stage === 'hired').length }} / {{ requisition.openings }}</span></td>
                                            <td><span class="status-pill" :class="`recruitment-status-${requisition.status}`">{{ requisition.status.replace('_', ' ') }}</span></td>
                                            <td><button class="button button-soft recruitment-small-button" @click="recruitmentTab = 'pipeline'; recruitmentRequestFilter = String(requisition.id)">View candidates</button></td>
                                        </tr></tbody>
                                    </table>
                                </div>
                                <div v-else class="recruitment-empty"><span class="recruitment-empty-mark">↗</span><b>No requisitions yet</b><p>Create an opening to start building a focused candidate pipeline.</p><button class="button button-primary" @click="openRequisitionForm">Create a requisition <span>→</span></button></div>
                            </section>
                            <div class="recruitment-integrations-note"><span>ⓘ</span><p>Internal recruiting workflows are live. Job-board publishing, email, calendar scheduling, and resume storage require separate integrations and are not connected.</p></div>
                        </template>

                        <template v-else-if="activeSlug === 'recruitment' && recruitmentTab === 'requisitions'">
                            <section class="panel recruitment-requisition-workspace">
                                <div class="directory-toolbar recruitment-toolbar">
                                    <div><h2>Hiring requisitions</h2><span class="muted">{{ filteredRequisitions.length }} of {{ recruitment.requisitions.length }} roles · planned positions and hiring status</span></div>
                                    <div class="recruitment-toolbar-actions">
                                        <button class="button button-soft" :disabled="!openRecruitmentRequisitions.length" @click="openCandidateForm()">＋ Add candidate</button>
                                        <button class="button button-primary" @click="openRequisitionForm">＋ New requisition</button>
                                    </div>
                                </div>
                                <form v-if="showRequisitionForm" class="recruitment-form" @submit.prevent="createRequisition">
                                    <div class="recruitment-form-heading"><div><span class="eyebrow">OPEN A ROLE</span><h3>Create a hiring requisition</h3></div><button type="button" class="icon-button" aria-label="Close requisition form" @click="showRequisitionForm = false; resetRequisitionForm()">×</button></div>
                                    <div v-if="recruitmentFormError" class="inline-error" role="alert">{{ recruitmentFormError }}</div>
                                    <div class="recruitment-form-grid">
                                        <label>Requisition code<input v-model.trim="requisitionForm.code" required maxlength="40" placeholder="e.g. OPS-2026-04"></label>
                                        <label>Role title<input v-model.trim="requisitionForm.title" required maxlength="150" placeholder="e.g. Senior production planner"></label>
                                        <label>Department<select v-model="requisitionForm.department_id"><option value="">Company-wide</option><option v-for="department in recruitment.departments" :key="department.id" :value="String(department.id)">{{ department.name }}</option></select></label>
                                        <label>Planned position<select v-model="requisitionForm.position_id" @change="syncRequisitionDepartment"><option value="">No linked workforce position</option><option v-for="position in recruitment.positions" :key="position.id" :value="String(position.id)">{{ position.title }} · {{ position.code }}</option></select></label>
                                        <label>Employment type<select v-model="requisitionForm.employment_type"><option>Full-time</option><option>Part-time</option><option>Contract</option><option>Temporary</option></select></label>
                                        <label>Work arrangement<select v-model="requisitionForm.work_mode"><option>On-site</option><option>Hybrid</option><option>Remote</option></select></label>
                                        <label>Location<input v-model.trim="requisitionForm.location" maxlength="120" placeholder="e.g. Dubai, UAE"></label>
                                        <label>Openings<input v-model.number="requisitionForm.openings" type="number" min="1" max="100" required></label>
                                        <label>Priority<select v-model="requisitionForm.priority"><option value="low">Low</option><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option></select></label>
                                        <label>Target date<input v-model="requisitionForm.target_date" type="date"></label>
                                        <label class="recruitment-description-field">Role summary<textarea v-model.trim="requisitionForm.description" rows="3" maxlength="5000" placeholder="Describe the outcomes, experience, and skills this role needs."></textarea></label>
                                    </div>
                                    <div class="recruitment-form-footer"><span>Internal planning only. Creating a requisition does not publish an external vacancy.</span><button class="button button-primary" :disabled="busy">Save requisition <span>→</span></button></div>
                                </form>
                                <form v-if="showCandidateForm" class="recruitment-form recruitment-candidate-form" @submit.prevent="createCandidate">
                                    <div class="recruitment-form-heading"><div><span class="eyebrow">PRIVATE CANDIDATE RECORD</span><h3>Add a candidate to your pipeline</h3></div><button type="button" class="icon-button" aria-label="Close candidate form" @click="showCandidateForm = false; recruitmentFormError = ''">×</button></div>
                                    <div v-if="recruitmentFormError" class="inline-error" role="alert">{{ recruitmentFormError }}</div>
                                    <div class="recruitment-form-grid">
                                        <label>Requisition<select v-model="candidateForm.requisition_id" required><option value="" disabled>Select an open role</option><option v-for="requisition in openRecruitmentRequisitions" :key="requisition.id" :value="String(requisition.id)">{{ requisition.title }} · {{ requisition.code }}</option></select></label>
                                        <label>First name<input v-model.trim="candidateForm.first_name" required maxlength="100" autocomplete="given-name"></label>
                                        <label>Last name<input v-model.trim="candidateForm.last_name" required maxlength="100" autocomplete="family-name"></label>
                                        <label>Email address<input v-model.trim="candidateForm.email" type="email" required maxlength="190" autocomplete="email"></label>
                                        <label>Phone<input v-model.trim="candidateForm.phone" type="tel" maxlength="40" autocomplete="tel"></label>
                                        <label>Candidate source<select v-model="candidateForm.source"><option>Direct</option><option>Referral</option><option>LinkedIn</option><option>Job board</option><option>Agency</option><option>Other</option></select></label>
                                        <label>Initial rating<select v-model="candidateForm.rating"><option value="">Not rated</option><option value="1">1 · Early screen</option><option value="2">2 · Some match</option><option value="3">3 · Good match</option><option value="4">4 · Strong match</option><option value="5">5 · Exceptional</option></select></label>
                                        <label class="recruitment-description-field">Private recruiter notes<textarea v-model.trim="candidateForm.notes" rows="3" maxlength="4000" placeholder="Add job-related notes for the hiring team."></textarea></label>
                                    </div>
                                    <div class="recruitment-form-footer"><span>Keep notes factual and job-related. Candidate data is restricted to HR and administrators.</span><button class="button button-primary" :disabled="busy || !openRecruitmentRequisitions.length">Add candidate <span>→</span></button></div>
                                </form>
                                <div v-if="recruitmentFormError && !showRequisitionForm && !showCandidateForm" class="inline-error recruitment-page-error" role="alert">{{ recruitmentFormError }}</div>
                                <div v-if="filteredRequisitions.length" class="table-scroll">
                                    <table class="recruitment-table"><thead><tr><th>ROLE / CODE</th><th>TEAM &amp; LOCATION</th><th>PIPELINE</th><th>SEATS FILLED</th><th>PRIORITY / TARGET</th><th>STATUS</th><th>ACTIONS</th></tr></thead>
                                        <tbody><tr v-for="requisition in filteredRequisitions" :key="requisition.id">
                                            <td><div class="recruitment-role-cell"><span class="recruitment-role-mark">{{ requisition.title.slice(0, 1) }}</span><span><b>{{ requisition.title }}</b><small>{{ requisition.code }} · {{ requisition.employment_type }} · {{ requisition.work_mode }}</small></span></div></td>
                                            <td><b class="recruitment-table-primary">{{ requisition.department?.name || 'Company-wide' }}</b><small class="recruitment-table-secondary">{{ requisition.branch?.name || requisition.location || 'All locations' }}</small></td>
                                            <td><span class="recruitment-candidate-count">{{ requisition.candidates_count }}</span><small class="recruitment-table-secondary">candidates</small></td>
                                            <td><span class="recruitment-openings">{{ recruitment.candidates.filter((candidate) => candidate.requisition_id === requisition.id && candidate.stage === 'hired').length }} / {{ requisition.openings }}</span></td>
                                            <td><span class="recruitment-priority" :class="`priority-${requisition.priority}`">{{ requisition.priority }}</span><small class="recruitment-table-secondary">Target {{ formatDate(requisition.target_date) }}</small></td>
                                            <td><span class="status-pill" :class="`recruitment-status-${requisition.status}`">{{ requisition.status.replace('_', ' ') }}</span></td>
                                            <td><div class="recruitment-row-actions"><button v-if="requisition.status === 'open'" class="button button-soft recruitment-small-button" :disabled="busy" @click="openCandidateForm(requisition.id)">＋ Candidate</button><button v-if="['open', 'on_hold'].includes(requisition.status)" class="button button-soft recruitment-small-button" :disabled="busy" @click="updateRequisitionStatus(requisition, requisition.status === 'open' ? 'on_hold' : 'open')">{{ requisition.status === 'open' ? 'Pause' : 'Reopen' }}</button><button v-if="['open', 'on_hold'].includes(requisition.status)" class="recruitment-close-link" :disabled="busy" @click="updateRequisitionStatus(requisition, 'closed')">Close</button><button v-if="requisition.status === 'closed'" class="button button-soft recruitment-small-button" :disabled="busy" @click="updateRequisitionStatus(requisition, 'open')">Reopen</button></div></td>
                                        </tr></tbody>
                                    </table>
                                </div>
                                <div v-else-if="!requisitionSearch" class="recruitment-empty"><span class="recruitment-empty-mark">▤</span><b>Build your hiring plan</b><p>Open your first requisition and keep every candidate, interview, and decision connected to the role.</p><button class="button button-primary" @click="openRequisitionForm">Create your first requisition <span>→</span></button></div>
                                <div v-else class="recruitment-empty"><b>No roles match this search</b><p>Try a different title, department, location, or requisition code.</p></div>
                            </section>
                        </template>

                        <template v-else-if="activeSlug === 'shifts-rosters'">
                            <section class="page-heading roster-heading">
                                <div>
                                    <span class="eyebrow">TIME & ABSENCE <span class="dot-separator">•</span> SCHEDULING WORKSPACE</span>
                                    <h1>Shift & roster</h1>
                                    <p>Plan coverage for the week, keep assignments visible, and catch conflicts before the roster is set.</p>
                                </div>
                                <div class="heading-actions">
                                    <button class="button button-soft" :disabled="!filteredRosterAssignments.length" @click="exportRosterCsv">↓ Export week</button>
                                    <button v-if="canManageOrganization" class="button button-primary" :disabled="!activeShiftPatterns.length || !shiftRoster.employees.length" @click="openRosterAssignmentForm">＋ Add assignment</button>
                                </div>
                            </section>

                            <section class="roster-metrics" aria-label="Roster summary">
                                <article class="roster-metric"><span class="roster-metric-icon roster-icon-shifts">▦</span><div><span class="eyebrow">ASSIGNMENTS</span><b>{{ shiftRoster.meta.assignments ?? 0 }}</b><small>This week in your scope</small></div></article>
                                <article class="roster-metric"><span class="roster-metric-icon roster-icon-people">♙</span><div><span class="eyebrow">PEOPLE SCHEDULED</span><b>{{ shiftRoster.meta.scheduled_employees ?? 0 }}</b><small>Unique employees</small></div></article>
                                <article class="roster-metric"><span class="roster-metric-icon roster-icon-patterns">◷</span><div><span class="eyebrow">ACTIVE PATTERNS</span><b>{{ shiftRoster.meta.active_patterns ?? 0 }}</b><small>Available shift templates</small></div></article>
                                <article class="roster-metric roster-metric-note"><span class="roster-metric-icon roster-icon-scope">✓</span><div><span class="eyebrow">ACCESS SCOPE</span><b>{{ canManageOrganization ? 'Company roster' : user.role === 'manager' ? 'Your team' : 'Your schedule' }}</b><small>Scoped to your access</small></div></article>
                            </section>

                            <section class="panel roster-panel">
                                <div class="roster-toolbar">
                                    <div class="roster-week-nav">
                                        <button class="icon-button" aria-label="Previous week" @click="moveRosterWeek(-1)">‹</button>
                                        <div><span class="eyebrow">WEEK OF</span><b>{{ rosterDayLabel(rosterWeekStart, { month: 'long', day: 'numeric', year: 'numeric' }) }}</b></div>
                                        <button class="icon-button" aria-label="Next week" @click="moveRosterWeek(1)">›</button>
                                        <button class="button button-soft roster-today-button" @click="goToCurrentRosterWeek">Today</button>
                                    </div>
                                    <nav class="roster-tabs" aria-label="Roster workspace">
                                        <button type="button" :class="{ active: rosterTab === 'week' }" @click="rosterTab = 'week'">Weekly roster</button>
                                        <button type="button" :class="{ active: rosterTab === 'patterns' }" @click="rosterTab = 'patterns'">Shift patterns <span>{{ shiftRoster.patterns.length }}</span></button>
                                    </nav>
                                    <label v-if="rosterTab === 'week'" class="table-search roster-search"><span>⌕</span><input v-model="rosterSearch" aria-label="Search roster" placeholder="Search people or shifts"></label>
                                </div>

                                <template v-if="rosterTab === 'week'">
                                    <section v-if="showRosterAssignmentForm && canManageOrganization" class="roster-form-panel">
                                        <form @submit.prevent="saveRosterAssignment">
                                            <div class="roster-form-heading"><div><span class="eyebrow">BUILD THE WEEK</span><h2>Assign a shift</h2><p>Each employee can have one shift assignment per date.</p></div><button type="button" class="icon-button" aria-label="Close assignment form" @click="closeRosterAssignmentForm">×</button></div>
                                            <div class="roster-form-grid">
                                                <label>Employee<select v-model="rosterAssignmentForm.employee_id" required><option value="" disabled>Select employee</option><option v-for="employee in shiftRoster.employees" :key="employee.id" :value="String(employee.id)">{{ fullName(employee) }} · {{ employee.employee_code }}</option></select></label>
                                                <label>Shift pattern<select v-model="rosterAssignmentForm.shift_pattern_id" required><option value="" disabled>Select shift</option><option v-for="pattern in activeShiftPatterns" :key="pattern.id" :value="String(pattern.id)">{{ pattern.name }} · {{ formatRosterTime(pattern.start_time) }}–{{ formatRosterTime(pattern.end_time) }}</option></select></label>
                                                <label>Shift date<input v-model="rosterAssignmentForm.shift_date" type="date" :min="localDateInput(new Date())" required></label>
                                                <label>Handover note <span class="muted">optional</span><input v-model.trim="rosterAssignmentForm.notes" maxlength="1000" placeholder="Team or coverage note"></label>
                                            </div>
                                            <div v-if="rosterFormError" class="inline-error" role="alert">{{ rosterFormError }}</div>
                                            <div class="roster-form-footer"><span>Approved leave and duplicate employee/day assignments are blocked.</span><div><button type="button" class="button button-soft" @click="closeRosterAssignmentForm">Cancel</button><button type="submit" class="button button-primary" :disabled="busy || !shiftRoster.employees.length || !activeShiftPatterns.length">Save assignment <span>→</span></button></div></div>
                                        </form>
                                    </section>

                                    <div v-if="!shiftRoster.employees.length" class="roster-empty"><span class="empty-icon">♙</span><b>No employee records in your scope</b><p>Employees will appear here when they are linked to your workspace.</p></div>
                                    <div v-else-if="!activeShiftPatterns.length" class="roster-empty"><span class="empty-icon">◷</span><b>{{ canManageOrganization ? 'Create a shift pattern to start scheduling' : 'No active shift patterns yet' }}</b><p>{{ canManageOrganization ? 'Define working hours once, then reuse the pattern across weekly assignments.' : 'Your schedule will appear here after HR creates shift patterns and assigns your shifts.' }}</p><button v-if="canManageOrganization" class="button button-primary" @click="rosterTab = 'patterns'; showShiftPatternForm = true">Create shift pattern</button></div>
                                    <div v-else class="roster-week-grid">
                                        <article v-for="date in rosterWeekDays" :key="date" class="roster-day" :class="{ 'roster-day-today': isCurrentRosterDay(date) }">
                                            <header class="roster-day-heading"><div><span>{{ rosterDayLabel(date, { weekday: 'short' }) }}</span><b>{{ rosterDayLabel(date, { day: 'numeric', month: 'short' }) }}</b></div><small>{{ rosterDayAssignments(date).length }} {{ rosterDayAssignments(date).length === 1 ? 'shift' : 'shifts' }}</small></header>
                                            <div v-if="rosterDayAssignments(date).length" class="roster-day-assignments">
                                                <article v-for="assignment in rosterDayAssignments(date)" :key="assignment.id" class="roster-assignment">
                                                    <div class="roster-assignment-top"><span class="roster-assignment-dot"></span><b>{{ assignment.shift_pattern?.name }}</b><button v-if="canManageOrganization && String(assignment.shift_date).slice(0, 10) >= localDateInput(new Date())" class="roster-remove" :aria-label="`Remove ${fullName(assignment.employee)} from roster`" :disabled="busy" @click="removeRosterAssignment(assignment)">×</button></div>
                                                    <span class="roster-assignment-time">{{ formatRosterTime(assignment.shift_pattern?.start_time) }} – {{ formatRosterTime(assignment.shift_pattern?.end_time) }}</span>
                                                    <div class="roster-assignment-person"><span class="avatar">{{ initials(assignment.employee) }}</span><span><b>{{ fullName(assignment.employee) }}</b><small>{{ assignment.employee?.employee_code }} · {{ assignment.employee?.department || 'Team not set' }}</small></span></div>
                                                    <span v-if="assignment.employee?.branch?.name" class="roster-assignment-branch">⌖ {{ assignment.employee.branch.name }}</span>
                                                    <p v-if="assignment.notes" class="roster-assignment-note">{{ assignment.notes }}</p>
                                                </article>
                                            </div>
                                            <div v-else class="roster-day-empty"><span>—</span><small>No shifts</small></div>
                                            <button v-if="canManageOrganization && activeShiftPatterns.length && shiftRoster.employees.length && date >= localDateInput(new Date())" class="roster-day-add" :disabled="busy" @click="openRosterAssignmentForm(); rosterAssignmentForm.shift_date = date">＋ Add shift</button>
                                        </article>
                                    </div>
                                    <div v-if="rosterSearch && !filteredRosterAssignments.length" class="roster-search-empty"><b>No assignments match “{{ rosterSearch }}”</b><span>Try another name, employee code, department, or shift pattern.</span></div>
                                </template>


                                <template v-else>
                                    <div class="roster-pattern-toolbar"><div><h2>Shift patterns</h2><p>Reusable working-hour templates available to your branch.</p></div><button v-if="canManageOrganization" class="button button-primary" @click="showShiftPatternForm = !showShiftPatternForm; shiftPatternFormError = ''">＋ Add shift pattern</button></div>
                                    <section v-if="showShiftPatternForm && canManageOrganization" class="roster-form-panel">
                                        <form @submit.prevent="saveShiftPattern">
                                            <div class="roster-form-heading"><div><span class="eyebrow">DEFINE WORKING HOURS</span><h2>Create a shift pattern</h2><p>Overnight shifts are supported when the end time is earlier than the start time.</p></div><button type="button" class="icon-button" aria-label="Close shift pattern form" @click="showShiftPatternForm = false; shiftPatternFormError = ''">×</button></div>
                                            <div class="roster-form-grid">
                                                <label>Pattern name<input v-model.trim="shiftPatternForm.name" maxlength="120" required placeholder="Day shift"></label>
                                                <label>Code<input v-model.trim="shiftPatternForm.code" maxlength="32" required placeholder="DAY-01"></label>
                                                <label>Branch<select v-model="shiftPatternForm.branch_id"><option value="">All branches</option><option v-for="branch in shiftRoster.branches" :key="branch.id" :value="String(branch.id)">{{ branch.name }}</option></select></label>
                                                <label>Start time<input v-model="shiftPatternForm.start_time" type="time" required></label>
                                                <label>End time<input v-model="shiftPatternForm.end_time" type="time" required></label>
                                                <label>Unpaid break (minutes)<input v-model.number="shiftPatternForm.break_minutes" type="number" min="0" max="240" required></label>
                                            </div>
                                            <div v-if="shiftPatternFormError" class="inline-error" role="alert">{{ shiftPatternFormError }}</div>
                                            <div class="roster-form-footer"><span>Overnight duration is calculated across midnight; no attendance rules are changed.</span><div><button type="button" class="button button-soft" @click="showShiftPatternForm = false">Cancel</button><button type="submit" class="button button-primary" :disabled="busy">Create pattern <span>→</span></button></div></div>
                                        </form>
                                    </section>
                                    <div v-if="shiftRoster.patterns.length" class="roster-pattern-list">
                                        <article v-for="pattern in shiftRoster.patterns" :key="pattern.id" class="roster-pattern-card">
                                            <span class="roster-pattern-icon">◷</span>
                                            <div class="roster-pattern-copy"><div><b>{{ pattern.name }}</b><span class="status-pill" :class="pattern.status === 'active' ? 'status-foundation' : 'status-planned'">{{ pattern.status === 'active' ? 'Active' : 'Archived' }}</span></div><small>{{ pattern.code }} · {{ pattern.branch?.name || 'All branches' }}</small></div>
                                            <div class="roster-pattern-time"><b>{{ formatRosterTime(pattern.start_time) }} – {{ formatRosterTime(pattern.end_time) }}</b><small>{{ pattern.break_minutes }} min break · {{ shiftDurationLabel(pattern) }} scheduled</small></div>
                                            <button v-if="canManageOrganization" class="button button-soft roster-pattern-action" :disabled="busy" @click="toggleShiftPattern(pattern)">{{ pattern.status === 'active' ? 'Archive' : 'Activate' }}</button>
                                        </article>
                                    </div>
                                    <div v-else class="roster-empty"><span class="empty-icon">◷</span><b>Your shift library is ready</b><p>Create your first pattern to make weekly scheduling quick and consistent.</p><button v-if="canManageOrganization" class="button button-primary" @click="showShiftPatternForm = true">Create the first shift</button></div>
                                </template>
                            </section>
                            <div class="roster-boundary-note"><span>ⓘ</span><p>Roster assignments are planning records only. Attendance, holiday calendars, automated rotations, shift swaps, overtime, and payroll are not changed by this workspace.</p><span>{{ canManageOrganization ? 'Managed by HR & administrators' : 'Read-only access' }}</span></div>
                        </template>

                        <template v-else>
                            <section class="panel recruitment-pipeline-panel">
                                <div class="directory-toolbar recruitment-pipeline-toolbar">
                                    <div><h2>Talent pipeline</h2><span class="muted">Showing {{ recruitment.candidates.length }} of {{ recruitment.candidate_pagination.total }} matching candidates · move each person forward with a clear next step</span></div>
                                    <div class="recruitment-filters">
                                        <label><span class="visually-hidden">Filter candidates by requisition</span><select v-model="recruitmentRequestFilter"><option value="all">All requisitions</option><option v-for="requisition in recruitment.requisitions" :key="requisition.id" :value="String(requisition.id)">{{ requisition.code }} · {{ requisition.title }}</option></select></label>
                                        <label><span class="visually-hidden">Filter candidates by stage</span><select v-model="recruitmentStageFilter"><option value="all">All stages</option><option v-for="stage in [...recruitmentStages, { id: 'withdrawn', label: 'Withdrawn' }]" :key="stage.id" :value="stage.id">{{ stage.label }}</option></select></label>
                                        <button class="button button-primary recruitment-add-candidate" :disabled="!openRecruitmentRequisitions.length" @click="openCandidateForm()">＋ Add candidate</button>
                                    </div>
                                </div>
                                <template v-if="filteredRecruitmentCandidates.length">
                                    <div class="recruitment-board">
                                    <section v-for="stage in [...recruitmentStages, { id: 'withdrawn', label: 'Withdrawn', icon: '↘' }]" :key="stage.id" class="recruitment-stage-column">
                                        <header class="recruitment-stage-heading"><span class="recruitment-stage-title"><i :class="`stage-icon-${stage.id}`">{{ stage.icon }}</i>{{ stage.label }}</span><span class="recruitment-stage-count">{{ filteredRecruitmentCandidates.filter((candidate) => candidate.stage === stage.id).length }}</span></header>
                                        <div v-if="filteredRecruitmentCandidates.some((candidate) => candidate.stage === stage.id)" class="recruitment-candidate-list">
                                            <article v-for="candidate in filteredRecruitmentCandidates.filter((entry) => entry.stage === stage.id)" :key="candidate.id" class="recruitment-candidate-card">
                                                <div class="recruitment-candidate-card-top"><span class="avatar">{{ initials(candidate) }}</span><span class="recruitment-source">{{ candidate.source }}</span></div>
                                                <h3>{{ fullName(candidate) }}</h3>
                                                <p class="recruitment-candidate-email">{{ candidate.email }}</p>
                                                <div class="recruitment-candidate-role">{{ candidate.requisition?.title }}<small>{{ candidate.requisition?.code }}</small></div>
                                                <div class="recruitment-candidate-meta"><span>Added {{ formatDate(candidate.applied_at) }}</span><span v-if="candidate.rating" :aria-label="`Rating ${candidate.rating} out of 5`">{{ '★'.repeat(candidate.rating) }}<i>{{ '☆'.repeat(5 - candidate.rating) }}</i></span><span v-else>Not rated</span></div>
                                                <div v-if="candidate.notes" class="recruitment-candidate-note">{{ candidate.notes }}</div>
                                                <div v-if="candidate.stage === 'hired'" class="recruitment-hire-link">Employee <b>{{ candidate.hired_employee?.employee_code || 'Created' }}</b></div>
                                                <template v-else-if="!['rejected', 'withdrawn'].includes(candidate.stage)">
                                                    <details class="recruitment-feedback">
                                                        <summary>Recruiter notes &amp; rating</summary>
                                                        <label :for="`candidate-rating-${candidate.id}`">Candidate rating</label>
                                                        <select :id="`candidate-rating-${candidate.id}`" v-model="candidateFeedbackDrafts[candidate.id].rating"><option value="">Not rated</option><option :value="1">1 · Early screen</option><option :value="2">2 · Some match</option><option :value="3">3 · Good match</option><option :value="4">4 · Strong match</option><option :value="5">5 · Exceptional</option></select>
                                                        <label :for="`candidate-notes-${candidate.id}`">Job-related feedback</label>
                                                        <textarea :id="`candidate-notes-${candidate.id}`" v-model.trim="candidateFeedbackDrafts[candidate.id].notes" rows="3" maxlength="4000" placeholder="Keep recruiter notes factual and job-related."></textarea>
                                                        <button class="button button-soft recruitment-stage-save" :disabled="busy" @click="saveCandidateFeedback(candidate)">Save feedback</button>
                                                    </details>
                                                    <div class="recruitment-stage-action"><label class="visually-hidden" :for="`candidate-stage-${candidate.id}`">Move {{ fullName(candidate) }} to stage</label><select :id="`candidate-stage-${candidate.id}`" v-model="candidateStageDrafts[candidate.id]"><option v-for="nextStage in candidateMoveOptions(candidate)" :key="nextStage.id" :value="nextStage.id" :disabled="nextStage.id === candidate.stage">{{ nextStage.id === candidate.stage ? `Current · ${nextStage.label}` : nextStage.label }}</option></select><button class="button button-soft recruitment-stage-save" :disabled="busy || candidateStageDrafts[candidate.id] === candidate.stage" @click="updateCandidateStage(candidate)">Move</button></div>
                                                    <button v-if="candidate.stage === 'offer'" class="button button-primary recruitment-hire-button" :disabled="busy" @click="openHireDialog(candidate)">Convert to employee <span>→</span></button>
                                                    <div v-if="candidateActionErrors[candidate.id]" class="inline-error recruitment-candidate-error" role="alert">{{ candidateActionErrors[candidate.id] }}</div>
                                                </template>
                                            </article>
                                        </div>
                                        <div v-else class="recruitment-stage-empty">No candidates here yet</div>
                                        </section>
                                    </div>
                                    <div v-if="recruitment.candidate_pagination.current_page < recruitment.candidate_pagination.last_page" class="recruitment-load-more"><span>{{ recruitment.candidates.length }} of {{ recruitment.candidate_pagination.total }} matching candidates loaded</span><button class="button button-soft" :disabled="busy" @click="loadMoreCandidates">{{ busy ? 'Loading…' : 'Load more candidates' }} <span>↓</span></button></div>
                                </template>
                                <div v-else class="recruitment-empty recruitment-pipeline-empty"><span class="recruitment-empty-mark">♙</span><b>{{ recruitment.metrics.active_candidates ? 'No candidates match these filters' : 'Your next great hire starts here' }}</b><p>{{ recruitment.metrics.active_candidates ? 'Adjust the search, stage, or requisition filters to see more people.' : 'Add a candidate to an open requisition to create your first pipeline card.' }}</p><button v-if="!recruitment.metrics.active_candidates && openRecruitmentRequisitions.length" class="button button-primary" @click="openCandidateForm">Add your first candidate <span>→</span></button><button v-else-if="!openRecruitmentRequisitions.length" class="button button-primary" @click="openRequisitionForm">Create an open requisition <span>→</span></button></div>
                            </section>
                            <div class="recruitment-integrations-note"><span>ⓘ</span><p>Pipeline movement, private recruiter notes, ratings, and employee conversion are persisted. Interview calendar invites, email sequences, background checks, and resume attachments are not connected.</p></div>
                        </template>
                    </template>

                <template v-else-if="activeSlug === 'onboarding-offboarding'">
                    <section class="lifecycle-hero">
                        <div class="lifecycle-hero-copy">
                            <span class="eyebrow lifecycle-eyebrow">PEOPLE EXPERIENCE <span>•</span> EMPLOYEE LIFECYCLE</span>
                            <h1>A thoughtful start.<br>A clear finish.</h1>
                            <p>Give every employee a consistent first day and every exit a well-managed handover.</p>
                            <button class="button lifecycle-hero-button" @click="openLifecycleForm()"><span>＋</span> Start a journey</button>
                        </div>
                        <div class="lifecycle-hero-aside">
                            <span class="lifecycle-orbit" aria-hidden="true">N</span>
                            <span class="lifecycle-hero-label">ONE SHARED CHECKLIST</span>
                            <b>Clear owners.<br>Visible progress.</b>
                            <small>Private to HR and administrators</small>
                        </div>
                    </section>

                    <section class="lifecycle-metrics" aria-label="Lifecycle metrics">
                        <article class="lifecycle-metric"><span class="lifecycle-metric-icon lifecycle-icon-onboard">✳</span><div><span class="eyebrow">ONBOARDING</span><b>{{ lifecycle.metrics.active_onboarding }}</b><small>Active journeys</small></div></article>
                        <article class="lifecycle-metric"><span class="lifecycle-metric-icon lifecycle-icon-exit">↗</span><div><span class="eyebrow">OFFBOARDING</span><b>{{ lifecycle.metrics.active_offboarding }}</b><small>Active journeys</small></div></article>
                        <article class="lifecycle-metric" :class="{ 'lifecycle-metric-alert': lifecycle.metrics.overdue_tasks > 0 }"><span class="lifecycle-metric-icon lifecycle-icon-overdue">◷</span><div><span class="eyebrow">OVERDUE STEPS</span><b>{{ lifecycle.metrics.overdue_tasks }}</b><small>Need attention</small></div></article>
                        <article class="lifecycle-metric"><span class="lifecycle-metric-icon lifecycle-icon-soon">▦</span><div><span class="eyebrow">DUE IN 7 DAYS</span><b>{{ lifecycle.metrics.tasks_due_soon }}</b><small>Upcoming checklist steps</small></div></article>
                    </section>

                    <section v-if="showLifecycleForm" class="panel lifecycle-form-panel">
                        <form class="lifecycle-form" @submit.prevent="startLifecycleCase">
                            <div class="lifecycle-form-heading"><div><span class="eyebrow">CREATE A WORKFLOW</span><h2>Start an employee journey</h2><p>A tailored checklist will be generated from the effective date.</p></div><button type="button" class="icon-button" aria-label="Close form" @click="showLifecycleForm = false">×</button></div>
                            <div class="lifecycle-form-grid">
                                <label>Journey type<select v-model="lifecycleForm.type" required><option value="onboarding">Onboarding · employee joining</option><option value="offboarding">Offboarding · employee exit</option></select></label>
                                <label>Employee<select v-model="lifecycleForm.employee_id" required><option value="" disabled>Select an active employee</option><option v-for="employee in availableLifecycleEmployees" :key="employee.id" :value="String(employee.id)">{{ fullName(employee) }} · {{ employee.employee_code }}</option></select></label>
                                <label>{{ lifecycleForm.type === 'onboarding' ? 'Joining date' : 'Last working day' }}<input v-model="lifecycleForm.effective_date" type="date" required></label>
                                <label class="lifecycle-form-note">Internal coordination note<textarea v-model.trim="lifecycleForm.notes" maxlength="2000" rows="2" placeholder="Optional context for the HR team"></textarea></label>
                            </div>
                            <div v-if="!availableLifecycleEmployees.length" class="inline-error" role="status">No active employees are eligible for another {{ lifecycleForm.type }} workflow.</div>
                            <div v-if="lifecycleFormError" class="inline-error" role="alert">{{ lifecycleFormError }}</div>
                            <div class="lifecycle-form-footer"><span>Each journey includes dated steps for HR, the manager, and relevant support teams.</span><div><button type="button" class="button button-soft" @click="showLifecycleForm = false">Cancel</button><button type="submit" class="button button-primary" :disabled="busy || !availableLifecycleEmployees.length">Create checklist <span>→</span></button></div></div>
                        </form>
                    </section>

                    <section class="lifecycle-workspace">
                        <div class="panel lifecycle-case-panel">
                            <div class="lifecycle-toolbar">
                                <nav class="lifecycle-tabs" aria-label="Employee journey type">
                                    <button v-for="tab in [{ id: 'all', label: 'All journeys' }, { id: 'onboarding', label: 'Onboarding' }, { id: 'offboarding', label: 'Offboarding' }]" :key="tab.id" type="button" :class="{ active: lifecycleTab === tab.id }" @click="setLifecycleTab(tab.id)">{{ tab.label }}<span>{{ tab.id === 'all' ? lifecycle.cases.length : lifecycle.cases.filter((caseItem) => caseItem.type === tab.id).length }}</span></button>
                                </nav>
                                <label class="lifecycle-search"><span>⌕</span><input v-model="lifecycleSearch" aria-label="Search lifecycle journeys" placeholder="Find an employee"></label>
                                <select v-model="lifecycleStatusFilter" class="lifecycle-status-filter" aria-label="Filter by workflow status"><option value="all">All statuses</option><option value="in_progress">In progress</option><option value="completed">Completed</option><option value="overdue">Has overdue steps</option></select>
                                <button class="button button-soft lifecycle-export" :disabled="!filteredLifecycleCases.length" @click="exportLifecycleCsv">Export view <span>↓</span></button>
                            </div>
                            <div class="lifecycle-case-layout">
                                <div class="lifecycle-case-list">
                                    <button v-for="caseItem in filteredLifecycleCases" :key="caseItem.id" type="button" class="lifecycle-case-card" :class="{ selected: selectedLifecycleCaseId === caseItem.id }" @click="selectedLifecycleCaseId = caseItem.id">
                                        <span class="lifecycle-case-avatar" :class="`lifecycle-case-avatar-${caseItem.type}`">{{ initials(caseItem.employee) }}</span>
                                        <span class="lifecycle-case-copy"><b>{{ fullName(caseItem.employee) }}</b><small>{{ caseItem.employee?.job_title || caseItem.employee?.department || 'Employee' }} · {{ caseItem.employee?.employee_code }}</small><span class="lifecycle-case-meta"><i :class="`lifecycle-type-dot lifecycle-dot-${caseItem.type}`"></i>{{ caseItem.type }}<i>·</i>{{ formatDate(caseItem.effective_date) }}</span><span class="lifecycle-mini-progress"><i :style="{ width: `${lifecycleCaseProgress(caseItem)}%` }"></i></span></span>
                                        <span class="lifecycle-case-end"><span class="status-pill" :class="caseItem.status === 'completed' ? 'status-foundation' : 'status-pending'">{{ caseItem.status === 'in_progress' ? 'In progress' : 'Completed' }}</span><small>{{ lifecycleCaseProgress(caseItem) }}%</small></span>
                                    </button>
                                    <div v-if="!filteredLifecycleCases.length" class="lifecycle-empty-list"><span class="lifecycle-empty-icon">✳</span><b>{{ lifecycle.cases.length ? 'No journeys match these filters' : 'Your lifecycle workspace is ready' }}</b><p>{{ lifecycle.cases.length ? 'Try another employee name, journey type, or status.' : 'Create the first onboarding or offboarding checklist to make every step visible.' }}</p><button v-if="!lifecycle.cases.length" class="button button-primary" @click="openLifecycleForm()">Start the first journey <span>→</span></button></div>
                                </div>

                                <article v-if="selectedLifecycleCase" class="lifecycle-detail">
                                    <div class="lifecycle-detail-heading">
                                        <div class="lifecycle-detail-person"><span class="lifecycle-case-avatar lifecycle-detail-avatar" :class="`lifecycle-case-avatar-${selectedLifecycleCase.type}`">{{ initials(selectedLifecycleCase.employee) }}</span><div><span class="eyebrow">{{ selectedLifecycleCase.type === 'onboarding' ? 'EMPLOYEE ONBOARDING' : 'EMPLOYEE OFFBOARDING' }}</span><h2>{{ fullName(selectedLifecycleCase.employee) }}</h2><p>{{ selectedLifecycleCase.employee?.job_title || 'Employee' }} · {{ selectedLifecycleCase.employee?.department || 'Unassigned team' }} · {{ selectedLifecycleCase.employee?.employee_code }}</p></div></div>
                                        <span class="status-pill" :class="selectedLifecycleCase.status === 'completed' ? 'status-foundation' : 'status-pending'">{{ selectedLifecycleCase.status === 'in_progress' ? 'In progress' : 'Completed' }}</span>
                                    </div>
                                    <div class="lifecycle-detail-meta"><span><small>{{ selectedLifecycleCase.type === 'onboarding' ? 'JOINING DATE' : 'LAST WORKING DAY' }}</small><b>{{ formatDate(selectedLifecycleCase.effective_date) }}</b></span><span><small>WORK LOCATION</small><b>{{ selectedLifecycleCase.employee?.branch?.name || 'Company-wide' }}</b></span><span><small>CHECKLIST</small><b>{{ selectedLifecycleCase.tasks.filter((task) => task.status === 'completed').length }} / {{ selectedLifecycleCase.tasks.length }} done</b></span></div>
                                    <div class="lifecycle-progress-block"><div><span class="eyebrow">JOURNEY PROGRESS</span><b>{{ selectedLifecycleProgress }}%</b></div><div class="lifecycle-progress-track"><i :style="{ width: `${selectedLifecycleProgress}%` }"></i></div></div>
                                    <div v-if="selectedLifecycleCase.notes" class="lifecycle-case-note"><span>HR NOTE</span><p>{{ selectedLifecycleCase.notes }}</p></div>
                                    <div class="lifecycle-task-heading"><div><span class="eyebrow">THE CHECKLIST</span><h3>Small steps, a smooth transition.</h3></div><span>{{ selectedLifecycleCase.tasks.length }} steps</span></div>
                                    <div class="lifecycle-task-list">
                                        <article v-for="(task, taskIndex) in selectedLifecycleCase.tasks" :key="task.id" class="lifecycle-task" :class="{ 'lifecycle-task-done': task.status === 'completed', 'lifecycle-task-overdue': isLifecycleTaskOverdue(task) }">
                                            <span class="lifecycle-task-index">{{ task.status === 'completed' ? '✓' : String(taskIndex + 1).padStart(2, '0') }}</span>
                                            <div class="lifecycle-task-main"><div class="lifecycle-task-title-row"><b>{{ task.title }}</b><span v-if="isLifecycleTaskOverdue(task)" class="lifecycle-overdue-label">Overdue</span></div><div class="lifecycle-task-meta"><span>{{ task.owner_team }}</span><i>·</i><span>Due {{ formatDate(task.due_date) }}</span></div><label class="lifecycle-task-note">Update / handover note<textarea v-model.trim="lifecycleTaskDrafts[task.id].notes" rows="2" maxlength="2000" :disabled="selectedLifecycleCase.status === 'completed'" placeholder="Optional note for the next owner"></textarea></label><div v-if="lifecycleTaskErrors[task.id]" class="inline-error" role="alert">{{ lifecycleTaskErrors[task.id] }}</div></div>
                                            <div class="lifecycle-task-action"><label class="visually-hidden" :for="`lifecycle-status-${task.id}`">Status for {{ task.title }}</label><select :id="`lifecycle-status-${task.id}`" v-model="lifecycleTaskDrafts[task.id].status" :disabled="selectedLifecycleCase.status === 'completed'"><option value="pending">To do</option><option value="in_progress">In progress</option><option value="completed">Done</option></select><button class="button button-soft" :disabled="busy || selectedLifecycleCase.status === 'completed' || (lifecycleTaskDrafts[task.id].status === task.status && lifecycleTaskDrafts[task.id].notes === (task.notes || ''))" @click="saveLifecycleTask(task)">Save</button></div>
                                        </article>
                                    </div>
                                    <div class="lifecycle-detail-footer">
                                        <span v-if="selectedLifecycleCase.status === 'completed'" class="lifecycle-completed-note">Journey closed {{ formatDate(selectedLifecycleCase.completed_at) }}.</span>
                                        <span v-else-if="selectedLifecycleTasksComplete" class="lifecycle-ready-note">All checklist steps are done. Review and close this journey.</span>
                                        <span v-else>{{ selectedLifecycleCase.tasks.length - selectedLifecycleCase.tasks.filter((task) => task.status === 'completed').length }} checklist steps remaining</span>
                                        <button v-if="selectedLifecycleCase.status === 'in_progress' && selectedLifecycleTasksComplete" class="button button-primary" @click="lifecycleCloseOpen = true">{{ selectedLifecycleCase.type === 'offboarding' ? 'Review & finalize exit' : 'Complete onboarding' }} <span>→</span></button>
                                    </div>
                                </article>
                                <div v-else class="lifecycle-detail-empty"><span class="lifecycle-empty-icon">↖</span><b>Select a journey to review progress</b><p>Checklist details and handover notes are visible to authorized HR staff.</p></div>
                            </div>
                        </div>
                    </section>
                    <div class="lifecycle-privacy-note"><span>ⓘ</span><p>Employee lifecycle records are restricted to HR and administrators. Checklist steps do not connect to document storage, payroll processing, account provisioning, or external providers.</p><span class="lifecycle-completed-count">{{ lifecycle.metrics.completed_this_month }} completed this month</span></div>
                </template>

                <template v-else-if="activeSlug === 'payroll'">
                    <template v-if="canManageOrganization">
                        <section class="page-heading payroll-heading">
                            <div>
                                <span class="eyebrow">PAY <span class="dot-separator">•</span> CONTROLLED PAYROLL WORKSPACE</span>
                                <h1>Payroll</h1>
                                <p>Prepare employee pay inputs, route a documented review, and export a controlled payroll workpaper.</p>
                            </div>
                            <div class="heading-actions">
                                <span class="status-pill status-foundation"><i></i> Input &amp; approval workflow</span>
                                <button class="button button-primary" @click="openPayrollCycleForm">＋ Create payroll cycle</button>
                            </div>
                        </section>

                        <section class="payroll-metrics" aria-label="Payroll cycle summary">
                            <article class="payroll-metric"><span class="payroll-metric-icon payroll-icon-cycles">▤</span><div><span class="eyebrow">CYCLES</span><b>{{ payrollData.meta.cycle_count ?? 0 }}</b><small>Recent cycles</small></div></article>
                            <article class="payroll-metric"><span class="payroll-metric-icon payroll-icon-draft">✎</span><div><span class="eyebrow">DRAFT</span><b>{{ payrollData.meta.draft_count ?? 0 }}</b><small>Inputs in progress</small></div></article>
                            <article class="payroll-metric"><span class="payroll-metric-icon payroll-icon-review">◷</span><div><span class="eyebrow">IN REVIEW</span><b>{{ payrollData.meta.review_count ?? 0 }}</b><small>Awaiting decision</small></div></article>
                            <article class="payroll-metric"><span class="payroll-metric-icon payroll-icon-approved">✓</span><div><span class="eyebrow">APPROVED</span><b>{{ payrollData.meta.approved_count ?? 0 }}</b><small>Not marked as paid</small></div></article>
                        </section>

                        <section class="panel payroll-panel">
                            <div class="payroll-toolbar">
                                <nav class="payroll-tabs" aria-label="Payroll workspace">
                                    <button type="button" :class="{ active: payrollTab !== 'detail' && payrollTab !== 'create' }" @click="payrollTab = 'cycles'; showPayrollCycleForm = false">Payroll cycles <span>{{ payrollData.cycles.length }}</span></button>
                                    <button v-if="selectedPayrollCycle" type="button" :class="{ active: payrollTab === 'detail' }" @click="payrollTab = 'detail'">Cycle details</button>
                                </nav>
                                <div v-if="payrollTab === 'cycles' || payrollTab === 'detail'" class="payroll-filters">
                                    <label><span class="visually-hidden">Filter payroll cycles by status</span><select v-model="payrollStatusFilter"><option value="all">All statuses</option><option value="draft">Draft</option><option value="changes_requested">Changes requested</option><option value="in_review">In review</option><option value="approved">Approved</option></select></label>
                                    <label class="table-search payroll-search"><span>⌕</span><input v-model="payrollSearch" aria-label="Search payroll cycles or employees" placeholder="Search period, branch, employee"></label>
                                </div>
                                <div v-if="payrollTab === 'detail' && selectedPayrollCycle" class="payroll-toolbar-actions">
                                    <button class="button button-soft" :disabled="payrollExporting" @click="exportPayrollWorkpaper">↓ Export workpaper</button>
                                    <button v-if="['draft', 'changes_requested'].includes(selectedPayrollCycle.status)" class="button button-primary" :disabled="busy || !selectedPayrollTotals.ready || selectedPayrollTotals.ready !== selectedPayrollCycle.items.length" @click="submitPayrollCycle">Submit for review →</button>
                                    <button v-if="selectedPayrollCycle.status === 'in_review'" class="button button-soft" :disabled="busy" @click="openPayrollReview('return')">Request changes</button>
                                    <button v-if="selectedPayrollCycle.status === 'in_review'" class="button button-primary" :disabled="busy" @click="openPayrollReview('approve')">Approve cycle</button>
                                </div>
                            </div>

                            <section v-if="showPayrollCycleForm" class="payroll-create-form">
                                <div class="payroll-create-heading"><div><span class="eyebrow">START A CONTROLLED CYCLE</span><h2>Create a payroll cycle</h2><p>Select one currency and explicitly choose the employees to include. Pay amounts are entered separately.</p></div><button type="button" class="icon-button" aria-label="Close payroll cycle form" @click="showPayrollCycleForm = false; payrollFormError = ''">×</button></div>
                                <div class="payroll-cycle-fields">
                                    <label>Period start<input v-model="payrollCycleForm.period_start" type="date" required></label>
                                    <label>Period end<input v-model="payrollCycleForm.period_end" type="date" :min="payrollCycleForm.period_start" required></label>
                                    <label>Planned pay date<input v-model="payrollCycleForm.pay_date" type="date" :min="payrollCycleForm.period_start"></label>
                                    <label>Currency<select v-model="payrollCycleForm.currency" required><option value="AED">AED · UAE dirham</option><option value="INR">INR · Indian rupee</option><option value="SAR">SAR · Saudi riyal</option></select></label>
                                    <label v-if="!user.branch_id">Branch scope<select v-model="payrollCycleForm.branch_id"><option value="">All branches</option><option v-for="branch in payrollData.branches" :key="branch.id" :value="String(branch.id)">{{ branch.name }}</option></select></label>
                                </div>
                                <div class="payroll-employee-picker">
                                    <div class="payroll-picker-heading"><div><h3>Employees included</h3><span>{{ payrollCycleForm.employee_ids.length }} selected · {{ payrollScopeEmployees.length }} available in {{ payrollCycleForm.currency }}</span></div><div><label class="table-search payroll-picker-search"><span>⌕</span><input v-model="payrollEmployeeSearch" aria-label="Search available employees" placeholder="Find an employee"></label><button type="button" class="text-link" :disabled="!eligiblePayrollEmployees.length" @click="toggleAllPayrollEmployees">{{ eligiblePayrollEmployees.length && eligiblePayrollEmployees.every((employee) => payrollCycleForm.employee_ids.includes(employee.id)) ? 'Deselect visible' : 'Select visible' }}</button></div></div>
                                    <div v-if="eligiblePayrollEmployees.length" class="payroll-employee-list">
                                        <label v-for="employee in eligiblePayrollEmployees" :key="employee.id" class="payroll-employee-option">
                                            <input type="checkbox" :checked="payrollCycleForm.employee_ids.includes(employee.id)" @change="togglePayrollEmployee(employee.id)">
                                            <span class="avatar">{{ initials(employee) }}</span>
                                            <span><b>{{ fullName(employee) }}</b><small>{{ employee.employee_code }} · {{ employee.department || 'Team not set' }}</small></span>
                                            <span class="payroll-employee-currency">{{ employee.currency }}</span>
                                        </label>
                                    </div>
                                    <div v-else class="payroll-picker-empty">{{ payrollEmployeeSearch ? 'No employees match this search.' : `No active employees are assigned to ${payrollCycleForm.currency} in this branch scope.` }}</div>
                                </div>
                                <div v-if="payrollFormError" class="inline-error" role="alert">{{ payrollFormError }}</div>
                                <div class="payroll-form-footer"><span>Creating a cycle does not read or copy monthly salary values.</span><div><button class="button button-soft" type="button" @click="showPayrollCycleForm = false">Cancel</button><button class="button button-primary" :disabled="busy || !payrollCycleForm.employee_ids.length" @click="createPayrollCycle">Create draft <span>→</span></button></div></div>
                            </section>

                            <template v-if="payrollTab === 'detail' && selectedPayrollCycle">
                                <section class="payroll-cycle-summary">
                                    <div class="payroll-cycle-title"><span class="payroll-cycle-mark">▤</span><div><span class="eyebrow">{{ selectedPayrollCycle.currency }} PAYROLL WORKPAPER</span><h2>{{ formatDate(selectedPayrollCycle.period_start) }} – {{ formatDate(selectedPayrollCycle.period_end) }}</h2><p>{{ selectedPayrollCycle.branch?.name || 'All branches' }}<span v-if="selectedPayrollCycle.pay_date"> · Planned pay date {{ formatDate(selectedPayrollCycle.pay_date) }}</span></p></div><span class="status-pill" :class="`payroll-status-${selectedPayrollCycle.status}`">{{ selectedPayrollCycle.status.replace('_', ' ') }}</span></div>
                                    <div class="payroll-totals">
                                        <div><span>EMPLOYEES</span><b>{{ selectedPayrollCycle.items.length }}</b></div>
                                        <div><span>INPUTS SAVED</span><b>{{ selectedPayrollTotals.ready }} / {{ selectedPayrollCycle.items.length }}</b></div>
                                        <div><span>GROSS ARITHMETIC TOTAL</span><b>{{ formatPayrollMoney(selectedPayrollTotals.base + selectedPayrollTotals.earnings, selectedPayrollCycle.currency) }}</b></div>
                                        <div><span>NET ARITHMETIC TOTAL</span><b>{{ formatPayrollMoney(selectedPayrollTotals.net, selectedPayrollCycle.currency) }}</b></div>
                                    </div>
                                </section>
                                <div v-if="selectedPayrollCycle.review_note" class="payroll-review-note"><b>Reviewer note</b><p>{{ selectedPayrollCycle.review_note }}</p></div>
                                <div class="payroll-calculation-boundary"><span>ⓘ</span><p>Gross and net here are arithmetic totals from manually entered amounts only. No statutory tax, pension, insurance, overtime, EOSB, WPS, or payment rules are applied.</p></div>
                                <div v-if="selectedPayrollCycle.items.length" class="payroll-table-wrap">
                                    <table class="payroll-table">
                                        <thead><tr><th>EMPLOYEE</th><th>BASE PAY</th><th>OTHER EARNINGS</th><th>MANUAL DEDUCTIONS</th><th>GROSS TOTAL</th><th>NET TOTAL</th><th>INPUT NOTE</th><th></th></tr></thead>
                                        <tbody>
                                            <tr v-for="item in filteredPayrollItems" :key="item.id">
                                                <td><div class="payroll-person"><span class="avatar">{{ initials({ first_name: item.employee_name }) }}</span><span><b>{{ item.employee_name }}</b><small>{{ item.employee_code }} · {{ item.department || 'Team not set' }}</small></span></div></td>
                                                <td><label class="visually-hidden" :for="`payroll-base-${item.id}`">Base pay for {{ item.employee_name }}</label><input :id="`payroll-base-${item.id}`" v-model="payrollItemDrafts[item.id].base_pay" class="payroll-amount-input" type="number" min="0" max="9999999999.99" step="0.01" :disabled="!['draft', 'changes_requested'].includes(selectedPayrollCycle.status)" placeholder="0.00"></td>
                                                <td><label class="visually-hidden" :for="`payroll-earnings-${item.id}`">Other earnings for {{ item.employee_name }}</label><input :id="`payroll-earnings-${item.id}`" v-model="payrollItemDrafts[item.id].other_earnings" class="payroll-amount-input" type="number" min="0" max="9999999999.99" step="0.01" :disabled="!['draft', 'changes_requested'].includes(selectedPayrollCycle.status)" placeholder="0.00"></td>
                                                <td><label class="visually-hidden" :for="`payroll-deductions-${item.id}`">Manual deductions for {{ item.employee_name }}</label><input :id="`payroll-deductions-${item.id}`" v-model="payrollItemDrafts[item.id].manual_deductions" class="payroll-amount-input" type="number" min="0" max="9999999999.99" step="0.01" :disabled="!['draft', 'changes_requested'].includes(selectedPayrollCycle.status)" placeholder="0.00"></td>
                                                <td class="payroll-total-cell">{{ formatPayrollMoney(item.gross_pay, selectedPayrollCycle.currency) }}</td>
                                                <td class="payroll-total-cell payroll-net-cell">{{ formatPayrollMoney(item.net_pay, selectedPayrollCycle.currency) }}</td>
                                                <td><label class="visually-hidden" :for="`payroll-notes-${item.id}`">Input note for {{ item.employee_name }}</label><input :id="`payroll-notes-${item.id}`" v-model.trim="payrollItemDrafts[item.id].notes" class="payroll-note-input" maxlength="2000" :disabled="!['draft', 'changes_requested'].includes(selectedPayrollCycle.status)" placeholder="Optional"></td>
                                                <td><button v-if="['draft', 'changes_requested'].includes(selectedPayrollCycle.status)" class="button button-soft payroll-save-button" :disabled="busy || !payrollItemChanged(item)" @click="savePayrollItem(item)">Save</button><span v-else class="payroll-locked-label">Locked</span><span v-if="payrollItemErrors[item.id]" class="payroll-row-error" role="alert">{{ payrollItemErrors[item.id] }}</span></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <div v-if="!filteredPayrollItems.length" class="payroll-picker-empty">No employee records match this search.</div>
                                </div>
                                <div v-else class="payroll-empty"><span class="empty-icon">♙</span><b>No employee lines in this cycle</b><p>Cycles are created with explicit employee selections. Create another cycle with the required people.</p></div>
                                <div class="payroll-approval-footer">
                                    <div class="payroll-approval-history"><span class="eyebrow">REVIEW HISTORY</span><p>Created by <b>{{ selectedPayrollCycle.created_by?.name || 'HR' }}</b><span v-if="selectedPayrollCycle.submitted_by"> · Submitted by <b>{{ selectedPayrollCycle.submitted_by.name }}</b></span><span v-if="selectedPayrollCycle.reviewed_by"> · Reviewed by <b>{{ selectedPayrollCycle.reviewed_by.name }}</b> on {{ formatDate(selectedPayrollCycle.reviewed_at) }}</span></p></div>
                                    <span v-if="selectedPayrollCycle.status === 'approved'" class="payroll-approved-note">Approved {{ formatDate(selectedPayrollCycle.reviewed_at) }} · not paid</span>
                                    <span v-else-if="selectedPayrollCycle.status === 'in_review'" class="payroll-waiting-note">Inputs are locked while this cycle is in review.</span>
                                    <span v-else-if="selectedPayrollCycle.status === 'changes_requested'" class="payroll-returned-note">Update requested inputs and resubmit for review.</span>
                                    <span v-else class="payroll-draft-note">Save every employee input before submitting.</span>
                                </div>
                            </template>

                            <template v-else-if="payrollTab === 'cycles' && !showPayrollCycleForm">
                                <div v-if="visiblePayrollCycles.length" class="payroll-cycle-list">
                                    <article v-for="cycle in visiblePayrollCycles" :key="cycle.id" class="payroll-cycle-card">
                                        <span class="payroll-cycle-mark">▤</span>
                                        <div class="payroll-cycle-card-main"><span class="eyebrow">{{ cycle.currency }} PAYROLL CYCLE</span><h3>{{ formatDate(cycle.period_start) }} – {{ formatDate(cycle.period_end) }}</h3><p>{{ cycle.branch?.name || 'All branches' }} · {{ cycle.items.length }} {{ cycle.items.length === 1 ? 'employee' : 'employees' }}<span v-if="cycle.pay_date"> · Pay date {{ formatDate(cycle.pay_date) }}</span></p></div>
                                        <div class="payroll-cycle-card-total"><span>NET ARITHMETIC TOTAL</span><b>{{ formatPayrollMoney(cycle.items.reduce((total, item) => total + Number(item.net_pay || 0), 0), cycle.currency) }}</b><small>{{ cycle.items.filter((item) => item.net_pay !== null).length }} / {{ cycle.items.length }} inputs saved</small></div>
                                        <span class="status-pill" :class="`payroll-status-${cycle.status}`">{{ cycle.status.replace('_', ' ') }}</span>
                                        <button class="button button-soft payroll-open-button" @click="selectPayrollCycle(cycle)">Review cycle →</button>
                                    </article>
                                </div>
                                <div v-else class="payroll-empty"><span class="empty-icon">▤</span><b>{{ payrollData.cycles.length ? 'No cycles match your filters' : 'Your payroll workspace is ready' }}</b><p>{{ payrollData.cycles.length ? 'Adjust the search or status filter to find a cycle.' : 'Create a draft cycle, select employees, then enter and review compensation inputs.' }}</p><button v-if="!payrollData.cycles.length" class="button button-primary" @click="openPayrollCycleForm">Create your first payroll cycle</button></div>
                            </template>

                            <div v-if="payrollTab === 'create' && !showPayrollCycleForm" class="payroll-empty"><span class="empty-icon">▤</span><b>Start a new payroll cycle</b><p>Choose the payroll period, currency, and employees to include.</p><button class="button button-primary" @click="openPayrollCycleForm">Create payroll cycle</button></div>
                        </section>

                        <div class="payroll-safety-note"><span>ⓘ</span><p>India, UAE, and Saudi/GCC statutory rules have not been configured or verified. This workspace does not calculate statutory deductions, payslips, WPS/SIF files, or payments. Use approved local payroll guidance and verify each input before approval.</p><span>HR &amp; admin only</span></div>
                    </template>
                    <section v-else class="panel payroll-restricted"><span class="empty-icon">▣</span><b>Payroll access is restricted</b><p>Compensation records are visible to HR and administrators only.</p></section>
                </template>

                <template v-else-if="activeSlug === 'benefits'">
                    <template v-if="canManageOrganization">
                        <section class="benefits-hero">
                            <div>
                                <span class="eyebrow benefits-eyebrow">PEOPLE EXPERIENCE <span>•</span> BENEFITS STUDIO</span>
                                <h1>Care, made easier to manage.</h1>
                                <p>Shape benefit programs, document enrollment choices, and keep every review traceable.</p>
                            </div>
                            <div class="benefits-hero-actions">
                                <button class="button benefits-secondary-button" @click="openBenefitPlanForm()">＋ Create plan</button>
                                <button class="button benefits-primary-button" :disabled="!benefitsData.plans.some((plan) => plan.status === 'active') || !benefitsData.employees.length" @click="openBenefitEnrollmentForm">＋ Enroll an employee <span>→</span></button>
                            </div>
                        </section>

                        <section class="benefit-metrics" aria-label="Benefit workspace summary">
                            <article><span class="benefit-metric-mark">✳</span><div><span class="eyebrow">ACTIVE PLANS</span><b>{{ benefitsData.meta.active_plans ?? 0 }}</b><small>Available programs</small></div></article>
                            <article><span class="benefit-metric-mark benefit-metric-people">♙</span><div><span class="eyebrow">ENROLLMENTS</span><b>{{ benefitsData.meta.active_enrollments ?? 0 }}</b><small>Current and in progress</small></div></article>
                            <article><span class="benefit-metric-mark benefit-metric-review">◷</span><div><span class="eyebrow">IN REVIEW</span><b>{{ benefitsData.meta.pending_review ?? 0 }}</b><small>Awaiting HR decision</small></div></article>
                            <article><span class="benefit-metric-mark benefit-metric-attention">✎</span><div><span class="eyebrow">NEEDS ATTENTION</span><b>{{ benefitsData.meta.needs_attention ?? 0 }}</b><small>Drafts or returned</small></div></article>
                        </section>

                        <section class="panel benefits-panel">
                            <div class="benefits-toolbar">
                                <nav class="benefits-tabs" aria-label="Benefits workspace">
                                    <button type="button" :class="{ active: benefitsTab === 'overview' }" @click="benefitsTab = 'overview'">Overview</button>
                                    <button type="button" :class="{ active: benefitsTab === 'plans' }" @click="benefitsTab = 'plans'">Plans &amp; settings <span>{{ benefitsData.plans.length }}</span></button>
                                    <button type="button" :class="{ active: benefitsTab === 'enrollments' }" @click="benefitsTab = 'enrollments'">Enrollments <span>{{ benefitsData.enrollments.length }}</span></button>
                                </nav>
                                <div v-if="benefitsTab !== 'overview'" class="benefits-toolbar-filters">
                                    <label v-if="benefitsTab === 'enrollments'"><span class="visually-hidden">Filter enrollments by status</span><select v-model="benefitStatusFilter"><option value="all">All statuses</option><option value="draft">Draft</option><option value="changes_requested">Changes requested</option><option value="in_review">In review</option><option value="approved">Approved</option><option value="declined">Declined</option><option value="ended">Ended</option></select></label>
                                    <label class="table-search benefits-search"><span>⌕</span><input v-model="benefitsSearch" :aria-label="benefitsTab === 'plans' ? 'Search benefit plans' : 'Search benefit enrollments'" :placeholder="benefitsTab === 'plans' ? 'Search plans or providers' : 'Search employee or plan'"></label>
                                    <button v-if="benefitsTab === 'enrollments'" class="button button-soft benefits-export" :disabled="benefitsExporting" @click="exportBenefitsCsv">↓ Export CSV</button>
                                </div>
                            </div>

                            <section v-if="showBenefitPlanForm" class="benefit-plan-form">
                                <div class="benefit-form-heading"><div><span class="eyebrow">PROGRAM SETTINGS</span><h2>{{ editingBenefitPlanId ? 'Edit benefit plan' : 'Create a benefit plan' }}</h2><p>Document a program and eligibility notes. This does not enroll staff or determine legal entitlement.</p></div><button type="button" class="icon-button" aria-label="Close benefit plan settings" @click="showBenefitPlanForm = false; benefitFormError = ''">×</button></div>
                                <form class="benefit-form-grid" @submit.prevent="saveBenefitPlan">
                                    <label>Plan name<input v-model.trim="benefitPlanForm.name" maxlength="140" required placeholder="Medical cover"></label>
                                    <label>Program category<select v-model="benefitPlanForm.category" required><option value="allowance">Allowance</option><option value="insurance">Insurance</option><option value="retirement">Retirement</option><option value="wellbeing">Wellbeing</option><option value="other">Other</option></select></label>
                                    <label>Currency<select v-model="benefitPlanForm.currency" required><option value="AED">AED · UAE dirham</option><option value="INR">INR · Indian rupee</option><option value="SAR">SAR · Saudi riyal</option></select></label>
                                    <label v-if="!user.branch_id">Branch scope<select v-model="benefitPlanForm.branch_id" :disabled="Boolean(editingBenefitPlanId)"><option value="">Company-wide</option><option v-for="branch in benefitsData.branches" :key="branch.id" :value="String(branch.id)">{{ branch.name }}</option></select><small v-if="editingBenefitPlanId" class="benefit-field-hint">Scope is fixed after plan creation.</small></label>
                                    <label>Provider (optional)<input v-model.trim="benefitPlanForm.provider" maxlength="180" placeholder="Provider or internal program"></label>
                                    <label v-if="editingBenefitPlanId">Plan status<select v-model="benefitPlanForm.status"><option value="active">Active</option><option value="archived">Archived</option></select></label>
                                    <label class="benefit-form-wide">Program description<textarea v-model.trim="benefitPlanForm.description" rows="2" maxlength="4000" placeholder="Describe coverage or the program in plain language"></textarea></label>
                                    <label class="benefit-form-wide">Eligibility and policy notes<textarea v-model.trim="benefitPlanForm.eligibility_notes" rows="2" maxlength="4000" placeholder="Document internally approved eligibility. No rules are applied automatically."></textarea></label>
                                    <div v-if="benefitFormError" class="inline-error benefit-form-wide" role="alert">{{ benefitFormError }}</div>
                                    <div class="benefit-form-footer benefit-form-wide"><span>Only HR/admin can configure benefit plans.</span><div><button type="button" class="button button-soft" @click="showBenefitPlanForm = false">Cancel</button><button type="submit" class="button button-primary" :disabled="busy">{{ editingBenefitPlanId ? 'Save plan settings' : 'Create plan' }}</button></div></div>
                                </form>
                            </section>

                            <section v-if="showBenefitEnrollmentForm" class="benefit-plan-form">
                                <div class="benefit-form-heading"><div><span class="eyebrow">EMPLOYEE ENROLLMENT</span><h2>Start an enrollment draft</h2><p>Amounts and dates are manually recorded. The employee is not notified and no provider is contacted.</p></div><button type="button" class="icon-button" aria-label="Close enrollment form" @click="showBenefitEnrollmentForm = false; benefitEnrollmentError = ''">×</button></div>
                                <form class="benefit-form-grid" @submit.prevent="createBenefitEnrollment">
                                    <label>Benefit plan<select v-model="benefitEnrollmentForm.benefit_plan_id" required><option value="" disabled>Select plan</option><option v-for="plan in benefitsData.plans.filter((entry) => entry.status === 'active')" :key="plan.id" :value="String(plan.id)">{{ plan.name }} · {{ plan.currency }}</option></select></label>
                                    <label>Employee<select v-model="benefitEnrollmentForm.employee_id" required><option value="" disabled>Select employee</option><option v-for="employee in benefitEnrollmentEmployees" :key="employee.id" :value="String(employee.id)">{{ fullName(employee) }} · {{ employee.employee_code }}</option></select></label>
                                    <label>Effective from<input v-model="benefitEnrollmentForm.effective_start" type="date" required></label>
                                    <label>Effective through (optional)<input v-model="benefitEnrollmentForm.effective_end" type="date" :min="benefitEnrollmentForm.effective_start"></label>
                                    <label class="benefit-form-wide">HR note<textarea v-model.trim="benefitEnrollmentForm.notes" rows="2" maxlength="2000" placeholder="Optional eligibility or enrollment context"></textarea></label>
                                    <div v-if="benefitEnrollmentError" class="inline-error benefit-form-wide" role="alert">{{ benefitEnrollmentError }}</div>
                                    <div class="benefit-form-footer benefit-form-wide"><span>Currency must match the employee's profile.</span><div><button type="button" class="button button-soft" @click="showBenefitEnrollmentForm = false">Cancel</button><button type="submit" class="button button-primary" :disabled="busy || !benefitEnrollmentForm.benefit_plan_id || !benefitEnrollmentForm.employee_id">Create enrollment draft</button></div></div>
                                </form>
                            </section>

                            <template v-if="benefitsTab === 'overview'">
                                <div class="benefit-overview-grid">
                                    <section class="benefit-overview-card benefit-program-card">
                                        <div class="benefit-section-heading"><div><span class="eyebrow">PROGRAM PORTFOLIO</span><h2>Benefits that fit your people.</h2><p>Manage program details and keep enrollment history in one place.</p></div><button class="text-link" @click="benefitsTab = 'plans'">View all plans →</button></div>
                                        <div v-if="benefitsData.plans.length" class="benefit-program-list">
                                            <article v-for="plan in benefitsData.plans.slice(0, 4)" :key="plan.id" class="benefit-program-row">
                                                <span class="benefit-program-icon" :class="`benefit-category-${plan.category}`">{{ plan.category === 'insurance' ? '✚' : plan.category === 'allowance' ? '＋' : plan.category === 'retirement' ? '⌂' : '✳' }}</span>
                                                <span class="benefit-program-copy"><b>{{ plan.name }}</b><small>{{ plan.category }}<span v-if="plan.provider"> · {{ plan.provider }}</span><span v-if="plan.branch"> · {{ plan.branch.name }}</span></small></span>
                                                <span class="benefit-program-members">{{ plan.active_enrollment_count }} enrolled</span>
                                                <span class="status-pill" :class="plan.status === 'active' ? 'benefit-status-active' : 'status-planned'">{{ plan.status }}</span>
                                            </article>
                                        </div>
                                        <div v-else class="benefit-empty-inline"><span class="empty-icon">✳</span><b>Build your benefit portfolio</b><p>Create a plan to document your programs and eligibility settings.</p><button class="button button-primary" @click="openBenefitPlanForm()">Create first benefit plan</button></div>
                                    </section>
                                    <section class="benefit-overview-card benefit-review-card">
                                        <div class="benefit-section-heading"><div><span class="eyebrow">REVIEW QUEUE</span><h2>Thoughtful reviews, clearly recorded.</h2><p>Review enrollment inputs without triggering a contribution or provider action.</p></div><button class="text-link" @click="benefitsTab = 'enrollments'; benefitStatusFilter = 'in_review'">Open queue →</button></div>
                                        <div v-if="benefitsData.enrollments.some((enrollment) => enrollment.status === 'in_review')" class="benefit-queue-list">
                                            <button v-for="enrollment in benefitsData.enrollments.filter((entry) => entry.status === 'in_review').slice(0, 5)" :key="enrollment.id" class="benefit-queue-row" @click="selectedBenefitEnrollmentId = enrollment.id; benefitsTab = 'enrollments'">
                                                <span class="avatar">{{ initials({ first_name: enrollment.employee_name }) }}</span><span><b>{{ enrollment.employee_name }}</b><small>{{ enrollment.plan?.name }} · {{ enrollment.plan?.currency }}</small></span><span class="benefit-queue-date">From {{ formatDate(enrollment.effective_start) }} →</span>
                                            </button>
                                        </div>
                                        <div v-else class="benefit-empty-inline benefit-queue-empty"><span class="benefit-empty-check">✓</span><b>Your review queue is clear</b><p>Submitted enrollments will appear here for an HR decision.</p><button class="button button-soft" :disabled="!benefitsData.plans.some((plan) => plan.status === 'active')" @click="openBenefitEnrollmentForm">Start an enrollment</button></div>
                                    </section>
                                </div>
                                <div class="benefit-policy-boundary"><span>ⓘ</span><p>Plan and enrollment values are internal records only. No country-specific eligibility, tax, insurance, pension, payroll deductions, or provider integrations are applied.</p><span>HR &amp; admin only</span></div>
                            </template>

                            <template v-else-if="benefitsTab === 'plans'">
                                <div v-if="visibleBenefitPlans.length" class="benefit-plan-list">
                                    <article v-for="plan in visibleBenefitPlans" :key="plan.id" class="benefit-plan-card">
                                        <span class="benefit-program-icon" :class="`benefit-category-${plan.category}`">{{ plan.category === 'insurance' ? '✚' : plan.category === 'allowance' ? '＋' : plan.category === 'retirement' ? '⌂' : '✳' }}</span>
                                        <div class="benefit-plan-main"><span class="eyebrow">{{ plan.currency }} <span class="dot-separator">•</span> {{ plan.category.toUpperCase() }}</span><h3>{{ plan.name }}</h3><p>{{ plan.description || 'No description added.' }}</p><small>{{ plan.provider || 'Internal program' }} · {{ plan.branch?.name || 'Company-wide' }} · {{ plan.active_enrollment_count }} active or pending</small><p v-if="plan.eligibility_notes" class="benefit-plan-eligibility"><b>Eligibility notes</b> · {{ plan.eligibility_notes }}</p></div>
                                        <span class="status-pill" :class="plan.status === 'active' ? 'benefit-status-active' : 'status-planned'">{{ plan.status }}</span>
                                        <div class="benefit-plan-actions"><button v-if="!user.branch_id || plan.branch_id" class="button button-soft" @click="openBenefitPlanForm(plan)">⚙ Configure</button><button v-if="plan.status === 'active'" class="button button-primary" @click="openBenefitEnrollmentForm(); benefitEnrollmentForm.benefit_plan_id = String(plan.id)">＋ Enroll</button></div>
                                    </article>
                                </div>
                                <div v-else class="payroll-empty"><span class="empty-icon">✳</span><b>{{ benefitsData.plans.length ? 'No plans match your search' : 'Your first program starts here' }}</b><p>{{ benefitsData.plans.length ? 'Try another provider, category, or plan name.' : 'Create settings for an allowance, insurance, retirement, or wellbeing program.' }}</p><button v-if="!benefitsData.plans.length" class="button button-primary" @click="openBenefitPlanForm()">Create benefit plan</button></div>
                            </template>

                            <section v-else-if="benefitsTab === 'enrollments'" class="benefit-enrollment-layout">
                                <div class="benefit-enrollment-list-panel">
                                    <div class="benefit-list-heading"><span class="eyebrow">ENROLLMENT REGISTER</span><b>{{ visibleBenefitEnrollments.length }} records</b></div>
                                    <div v-if="visibleBenefitEnrollments.length" class="benefit-enrollment-list">
                                        <button v-for="enrollment in visibleBenefitEnrollments" :key="enrollment.id" class="benefit-enrollment-option" :class="{ active: selectedBenefitEnrollmentId === enrollment.id }" @click="selectedBenefitEnrollmentId = enrollment.id">
                                            <span class="avatar">{{ initials({ first_name: enrollment.employee_name }) }}</span><span><b>{{ enrollment.employee_name }}</b><small>{{ enrollment.employee_code }} · {{ enrollment.plan?.name }}</small><small>{{ formatDate(enrollment.effective_start) }} <span v-if="enrollment.effective_end">– {{ formatDate(enrollment.effective_end) }}</span></small></span><span class="status-pill" :class="`benefit-status-${enrollment.status}`">{{ enrollment.status.replace('_', ' ') }}</span>
                                        </button>
                                    </div>
                                    <div v-else class="benefit-empty-inline"><b>{{ benefitsData.enrollments.length ? 'No enrollments match these filters' : 'No enrollments yet' }}</b><p>{{ benefitsData.enrollments.length ? 'Clear the search or status filter.' : 'Create an enrollment draft from an active plan.' }}</p><button v-if="!benefitsData.enrollments.length && benefitsData.plans.some((plan) => plan.status === 'active')" class="button button-primary" @click="openBenefitEnrollmentForm">Start enrollment</button></div>
                                </div>
                                <article v-if="selectedBenefitEnrollment" class="benefit-enrollment-detail">
                                    <div class="benefit-enrollment-detail-head"><div class="benefit-program-icon" :class="`benefit-category-${selectedBenefitEnrollment.plan?.category}`">✳</div><div><span class="eyebrow">{{ selectedBenefitEnrollment.plan?.currency }} <span class="dot-separator">•</span> ENROLLMENT WORKPAPER</span><h2>{{ selectedBenefitEnrollment.employee_name }}</h2><p>{{ selectedBenefitEnrollment.employee_code }} · {{ selectedBenefitEnrollment.department || 'Team not set' }} · {{ selectedBenefitEnrollment.plan?.name }}</p></div><span class="status-pill" :class="`benefit-status-${selectedBenefitEnrollment.status}`">{{ selectedBenefitEnrollment.status.replace('_', ' ') }}</span></div>
                                    <div class="benefit-detail-meta"><span><small>PROGRAM</small><b>{{ selectedBenefitEnrollment.plan?.category }}</b></span><span><small>PROVIDER</small><b>{{ selectedBenefitEnrollment.plan?.provider || 'Internal' }}</b></span><span><small>WORK LOCATION</small><b>{{ selectedBenefitEnrollment.branch?.name || 'Company-wide' }}</b></span></div>
                                    <div v-if="selectedBenefitEnrollment.review_note" class="payroll-review-note"><b>Reviewer note</b><p>{{ selectedBenefitEnrollment.review_note }}</p></div>
                                    <div class="benefit-contribution-grid">
                                        <label>Employee contribution · manual<input v-model="benefitContributionDrafts[selectedBenefitEnrollment.id].employee_contribution" type="number" min="0" max="9999999999.99" step="0.01" :disabled="!['draft', 'changes_requested'].includes(selectedBenefitEnrollment.status)" placeholder="0.00"><small>Enter the approved amount; do not infer a rate.</small></label>
                                        <label>Employer contribution · manual<input v-model="benefitContributionDrafts[selectedBenefitEnrollment.id].employer_contribution" type="number" min="0" max="9999999999.99" step="0.01" :disabled="!['draft', 'changes_requested'].includes(selectedBenefitEnrollment.status)" placeholder="0.00"><small>Manual record only; no payroll or provider action.</small></label>
                                        <label>Effective from<input v-model="benefitContributionDrafts[selectedBenefitEnrollment.id].effective_start" type="date" required :disabled="!['draft', 'changes_requested'].includes(selectedBenefitEnrollment.status)"></label>
                                        <label>Effective through<input v-model="benefitContributionDrafts[selectedBenefitEnrollment.id].effective_end" type="date" :min="benefitContributionDrafts[selectedBenefitEnrollment.id].effective_start" :disabled="!['draft', 'changes_requested'].includes(selectedBenefitEnrollment.status)"></label>
                                        <label class="benefit-notes-field">HR note<textarea v-model.trim="benefitContributionDrafts[selectedBenefitEnrollment.id].notes" rows="2" maxlength="2000" :disabled="!['draft', 'changes_requested'].includes(selectedBenefitEnrollment.status)" placeholder="Internal notes about this enrollment"></textarea></label>
                                    </div>
                                    <div v-if="benefitContributionErrors[selectedBenefitEnrollment.id]" class="inline-error" role="alert">{{ benefitContributionErrors[selectedBenefitEnrollment.id] }}</div>
                                    <div class="benefit-detail-footer">
                                        <span v-if="selectedBenefitEnrollment.status === 'approved'" class="payroll-approved-note">Approved {{ formatDate(selectedBenefitEnrollment.reviewed_at) }} · no payment processed</span>
                                        <span v-else-if="selectedBenefitEnrollment.status === 'in_review'" class="payroll-waiting-note">Enrollment inputs are locked for review.</span>
                                        <span v-else-if="selectedBenefitEnrollment.status === 'changes_requested'" class="payroll-returned-note">Update requested inputs and resubmit.</span>
                                        <span v-else class="payroll-draft-note">Amounts and eligibility must be verified by HR.</span>
                                        <div class="benefit-detail-actions">
                                            <button v-if="['draft', 'changes_requested'].includes(selectedBenefitEnrollment.status)" class="button button-soft" :disabled="busy || !benefitContributionChanged(selectedBenefitEnrollment)" @click="saveBenefitContribution(selectedBenefitEnrollment)">Save inputs</button>
                                            <button v-if="['draft', 'changes_requested'].includes(selectedBenefitEnrollment.status)" class="button button-primary" :disabled="busy || selectedBenefitEnrollment.employee_contribution === null || selectedBenefitEnrollment.employer_contribution === null || benefitContributionChanged(selectedBenefitEnrollment)" @click="submitBenefitEnrollment(selectedBenefitEnrollment)">Submit for review →</button>
                                            <button v-if="selectedBenefitEnrollment.status === 'in_review'" class="button button-soft" @click="openBenefitReview(selectedBenefitEnrollment, 'return')">Request changes</button>
                                            <button v-if="selectedBenefitEnrollment.status === 'in_review'" class="button button-primary" @click="openBenefitReview(selectedBenefitEnrollment, 'approve')">Approve</button>
                                            <button v-if="selectedBenefitEnrollment.status === 'in_review'" class="button button-soft" @click="openBenefitReview(selectedBenefitEnrollment, 'decline')">Decline</button>
                                            <button v-if="selectedBenefitEnrollment.status === 'approved'" class="button button-soft" @click="openBenefitEnd(selectedBenefitEnrollment)">Record end date</button>
                                        </div>
                                    </div>
                                </article>
                                <div v-else class="benefit-enrollment-detail benefit-empty-inline"><b>Select an enrollment to review</b><p>Use the register to view program settings and update a draft.</p></div>
                            </section>
                        </section>
                        <div class="benefit-policy-boundary"><span>ⓘ</span><p>Benefits settings and contributions are manual records. The workspace does not determine legal entitlements, tax treatment, insurance coverage, pension contributions, deductions, or provider eligibility.</p><span>HR &amp; admin only</span></div>
                    </template>
                    <section v-else class="panel payroll-restricted"><span class="empty-icon">▣</span><b>Benefit records are restricted</b><p>Program settings, employee enrollments, and contribution inputs are visible to HR and administrators only.</p></section>
                </template>

                <template v-else-if="activeSlug === 'separation-settlement'">
                    <template v-if="canManageOrganization">
                        <section class="separation-hero">
                            <div class="separation-hero-copy"><span class="eyebrow separation-eyebrow">PEOPLE OPERATIONS <span>•</span> EXIT &amp; SETTLEMENT</span><h1>A clear close, handled with care.</h1><p>Connect completed exit clearances to a reviewed, manually prepared settlement workpaper.</p><div class="separation-hero-actions"><button class="button separation-primary-button" :disabled="!eligibleSettlementCases.length" @click="openSettlementForm">＋ Start settlement workpaper <span>→</span></button><button class="button separation-secondary-button" @click="navigate('onboarding-offboarding')">Review exit checklists</button></div></div>
                            <div class="separation-hero-aside"><span class="separation-hero-icon">↗</span><span class="eyebrow">A CONTROLLED HANDOVER</span><b>Clearances first.<br>Review always.</b><small>Manual inputs · documented decisions · no payment action</small></div>
                        </section>
                        <section class="separation-metrics" aria-label="Separation summary">
                            <article><span class="separation-metric-icon">▤</span><div><span class="eyebrow">WORKPAPERS</span><b>{{ separationData.meta.settlement_count ?? 0 }}</b><small>Saved settlement records</small></div></article>
                            <article><span class="separation-metric-icon separation-metric-draft">✎</span><div><span class="eyebrow">IN PREPARATION</span><b>{{ separationData.meta.draft_count ?? 0 }}</b><small>Draft or returned</small></div></article>
                            <article><span class="separation-metric-icon separation-metric-review">◷</span><div><span class="eyebrow">IN REVIEW</span><b>{{ separationData.meta.review_count ?? 0 }}</b><small>Awaiting decision</small></div></article>
                            <article><span class="separation-metric-icon separation-metric-clear">✓</span><div><span class="eyebrow">CLEARANCES READY</span><b>{{ separationData.meta.clearance_ready_count ?? 0 }}</b><small>Completed exit checklists</small></div></article>
                        </section>
                        <section class="panel separation-panel">
                            <div class="separation-toolbar"><nav class="separation-tabs" aria-label="Separation workspace"><button :class="{ active: separationTab === 'overview' }" @click="separationTab = 'overview'">Overview</button><button :class="{ active: separationTab === 'workpapers' || separationTab === 'detail' }" @click="separationTab = 'workpapers'">Settlement workpapers <span>{{ separationData.settlements.length }}</span></button></nav><div v-if="separationTab !== 'overview' && separationTab !== 'detail'" class="separation-filters"><label><span class="visually-hidden">Filter settlements by status</span><select v-model="separationStatusFilter"><option value="all">All statuses</option><option value="draft">Draft</option><option value="changes_requested">Changes requested</option><option value="in_review">In review</option><option value="approved">Approved</option></select></label><label class="table-search separation-search"><span>⌕</span><input v-model="separationSearch" aria-label="Search settlement workpapers" placeholder="Search employee or status"></label></div><div v-if="separationTab === 'detail' && selectedSettlement" class="separation-toolbar-actions"><button class="button button-soft" :disabled="settlementExporting" @click="exportSettlementCsv">↓ Export workpaper</button><button class="button button-soft" @click="separationTab = 'workpapers'">← All workpapers</button></div></div>

                            <section v-if="showSettlementForm" class="settlement-create-form">
                                <div class="benefit-form-heading"><div><span class="eyebrow">LINK TO EXIT CLEARANCES</span><h2>Start a settlement workpaper</h2><p>Choose an employee's offboarding record. Clearance completion is required before review submission.</p></div><button type="button" class="icon-button" aria-label="Close settlement form" @click="showSettlementForm = false; settlementFormError = ''">×</button></div>
                                <form class="settlement-create-fields" @submit.prevent="createSettlement">
                                    <label>Offboarding case<select v-model="settlementForm.lifecycle_case_id" required><option value="" disabled>Select an exit case</option><option v-for="caseItem in eligibleSettlementCases" :key="caseItem.id" :value="String(caseItem.id)">{{ caseItem.employee?.first_name }} {{ caseItem.employee?.last_name }} · {{ caseItem.employee?.employee_code }} · {{ formatDate(caseItem.effective_date) }} · {{ caseItem.status }}</option></select></label>
                                    <label>Internal context<textarea v-model.trim="settlementForm.notes" rows="2" maxlength="2000" placeholder="Optional handover context"></textarea></label>
                                    <div v-if="settlementFormError" class="inline-error" role="alert">{{ settlementFormError }}</div>
                                    <div class="payroll-form-footer"><span>Currency is taken from the employee record; no pay components are prefilled.</span><div><button type="button" class="button button-soft" @click="showSettlementForm = false">Cancel</button><button type="submit" class="button button-primary" :disabled="busy || !settlementForm.lifecycle_case_id">Create draft workpaper</button></div></div>
                                </form>
                            </section>

                            <template v-if="separationTab === 'overview'">
                                <div class="separation-overview-grid">
                                    <article class="separation-overview-card"><div class="separation-section-heading"><div><span class="eyebrow">SETTLEMENT REVIEW</span><h2>Final details, never assumed.</h2><p>Prepare only after exit clearances are complete and reviewed.</p></div><button class="text-link" @click="separationTab = 'workpapers'">View workpapers →</button></div><div class="separation-overview-stats"><span><b>{{ separationData.meta.review_count ?? 0 }}</b><small>Pending review</small></span><span><b>{{ eligibleSettlementCases.length }}</b><small>Exit cases available</small></span><span><b>{{ separationData.meta.clearance_ready_count ?? 0 }}</b><small>Clearances complete</small></span></div><button class="button separation-primary-button" :disabled="!eligibleSettlementCases.length" @click="openSettlementForm">Start a workpaper <span>→</span></button></article>
                                    <article class="separation-overview-card separation-steps-card"><span class="eyebrow">REVIEW SEQUENCE</span><h2>One considered step at a time.</h2><div class="separation-step"><span>01</span><div><b>Complete the exit checklist</b><small>Asset return, handover, access, and other existing checklist items.</small></div><span class="status-pill status-foundation">Clearance</span></div><div class="separation-step"><span>02</span><div><b>Record manual workpaper lines</b><small>Enter only approved earning and recovery amounts.</small></div><span class="status-pill status-planned">Manual</span></div><div class="separation-step"><span>03</span><div><b>Document and approve</b><small>Reviewer decision is recorded; no payment is triggered.</small></div><span class="status-pill status-planned">Review</span></div></article>
                                </div>
                                <div class="separation-policy-note"><span>ⓘ</span><p>There are no gratuity/EOSB, leave encashment, notice, tax, loan, or other statutory formulas configured. Totals are arithmetic only: manually entered additions less manually entered recoveries. Approval is not payment.</p><span>HR &amp; admin only</span></div>
                            </template>

                            <template v-else-if="separationTab === 'workpapers'">
                                <div class="separation-workpaper-list">
                                    <article v-for="settlement in visibleSettlements" :key="settlement.id" class="separation-workpaper-row">
                                        <span class="separation-person-icon">{{ initials({ first_name: settlement.employee_name }) }}</span>
                                        <div class="separation-workpaper-main"><span class="eyebrow">{{ settlement.currency }} <span class="dot-separator">•</span> SEPARATION WORKPAPER</span><h3>{{ settlement.employee_name }}</h3><p>{{ settlement.employee_code }} · {{ settlement.department || 'Team not set' }} · Last working day {{ formatDate(settlement.offboarding_case?.effective_date) }}</p></div>
                                        <div class="separation-clearance-state"><span class="separation-clearance-dot" :class="{ complete: settlementClearancesComplete(settlement) }"></span><span><b>{{ settlementClearancesComplete(settlement) ? 'Clearances complete' : 'Clearances outstanding' }}</b><small>{{ settlement.offboarding_case?.tasks?.filter((task) => task.status === 'completed').length || 0 }} / {{ settlement.offboarding_case?.tasks?.length || 0 }} steps</small></span></div>
                                        <span class="status-pill" :class="`payroll-status-${settlement.status}`">{{ settlement.status.replace('_', ' ') }}</span>
                                        <button class="button button-soft separation-open-button" @click="selectSettlement(settlement)">Open workpaper →</button>
                                    </article>
                                    <div v-if="!visibleSettlements.length" class="payroll-empty"><span class="empty-icon">↗</span><b>{{ separationData.settlements.length ? 'No workpapers match these filters' : 'No settlement workpapers yet' }}</b><p>{{ separationData.settlements.length ? 'Adjust the search or status filter.' : eligibleSettlementCases.length ? 'Start a workpaper from an existing offboarding case when ready.' : 'Complete an employee offboarding checklist first, then start a linked settlement workpaper.' }}</p><button v-if="!separationData.settlements.length && eligibleSettlementCases.length" class="button button-primary" @click="openSettlementForm">Start first settlement workpaper</button><button v-else-if="!eligibleSettlementCases.length" class="button button-soft" @click="navigate('onboarding-offboarding')">Open offboarding checklist</button></div>
                                </div>
                            </template>

                            <section v-else-if="separationTab === 'detail' && selectedSettlement" class="settlement-detail">
                                <div class="settlement-summary">
                                    <div class="settlement-person-heading"><span class="separation-person-icon">{{ initials({ first_name: selectedSettlement.employee_name }) }}</span><div><span class="eyebrow">{{ selectedSettlement.currency }} <span class="dot-separator">•</span> MANUAL SETTLEMENT WORKPAPER</span><h2>{{ selectedSettlement.employee_name }}</h2><p>{{ selectedSettlement.employee_code }} · {{ selectedSettlement.department || 'Team not set' }} · Last working day {{ formatDate(selectedSettlement.offboarding_case?.effective_date) }}</p></div><span class="status-pill" :class="`payroll-status-${selectedSettlement.status}`">{{ selectedSettlement.status.replace('_', ' ') }}</span></div>
                                    <div class="settlement-total-grid"><div><span>MANUAL EARNINGS</span><b>{{ formatPayrollMoney(selectedSettlementTotals.earnings, selectedSettlement.currency) }}</b></div><div><span>MANUAL RECOVERIES</span><b>{{ formatPayrollMoney(selectedSettlementTotals.recoveries, selectedSettlement.currency) }}</b></div><div><span>NET ARITHMETIC TOTAL</span><b :class="{ 'settlement-negative': selectedSettlementTotals.net < 0 }">{{ formatPayrollMoney(selectedSettlementTotals.net, selectedSettlement.currency) }}</b></div></div>
                                </div>
                                <div class="settlement-clearance-panel"><div class="settlement-subheading"><div><span class="eyebrow">EXIT CLEARANCES</span><h3>{{ settlementClearancesComplete(selectedSettlement) ? 'Every clearance is recorded.' : 'Clearance review is still in progress.' }}</h3></div><button class="text-link" @click="navigate('onboarding-offboarding')">Open lifecycle record →</button></div><div class="settlement-clearance-list"><div v-for="task in selectedSettlement.offboarding_case?.tasks || []" :key="task.id" class="settlement-clearance-row"><span :class="task.status === 'completed' ? 'settlement-task-done' : 'settlement-task-pending'">{{ task.status === 'completed' ? '✓' : '·' }}</span><span><b>{{ task.title }}</b><small>{{ task.owner_team }} · Due {{ formatDate(task.due_date) }}</small><small v-if="task.notes" class="settlement-task-note">{{ task.notes }}</small></span><span class="status-pill" :class="task.status === 'completed' ? 'benefit-status-active' : 'status-planned'">{{ task.status.replace('_', ' ') }}</span></div></div></div>
                                <div v-if="selectedSettlement.review_note" class="payroll-review-note"><b>Reviewer note</b><p>{{ selectedSettlement.review_note }}</p></div>
                                <div class="settlement-boundary-note"><span>ⓘ</span><p>Enter only individually verified amounts. These line items do not calculate final pay, gratuity/EOSB, leave, notice, tax, or statutory deductions.</p></div>
                                <div class="settlement-lines-heading"><div><span class="eyebrow">MANUAL WORKPAPER LINES</span><h3>Document each addition and recovery.</h3></div><div v-if="['draft', 'changes_requested'].includes(selectedSettlement.status)" class="settlement-add-actions"><button class="button button-soft" @click="addSettlementLine('earning')">＋ Add earning</button><button class="button button-soft" @click="addSettlementLine('recovery')">＋ Add recovery</button></div></div>
                                <div v-if="settlementItemDrafts[selectedSettlement.id]?.length" class="settlement-line-list">
                                    <article v-for="(line, index) in settlementItemDrafts[selectedSettlement.id]" :key="index" class="settlement-line">
                                        <label>Type<select v-model="line.category" :disabled="!['draft', 'changes_requested'].includes(selectedSettlement.status)"><option value="earning">Manual earning</option><option value="recovery">Manual recovery</option></select></label>
                                        <label>Component description<input v-model.trim="line.description" maxlength="180" :disabled="!['draft', 'changes_requested'].includes(selectedSettlement.status)" placeholder="Approved adjustment name"></label>
                                        <label>Amount · {{ selectedSettlement.currency }}<input v-model="line.amount" type="number" min="0.01" max="9999999999.99" step="0.01" :disabled="!['draft', 'changes_requested'].includes(selectedSettlement.status)" placeholder="0.00"></label>
                                        <label>Supporting note<input v-model.trim="line.notes" maxlength="1000" :disabled="!['draft', 'changes_requested'].includes(selectedSettlement.status)" placeholder="Optional reference"></label>
                                        <button v-if="['draft', 'changes_requested'].includes(selectedSettlement.status)" type="button" class="icon-button settlement-remove-line" :aria-label="`Remove settlement line ${index + 1}`" @click="removeSettlementLine(index)">×</button>
                                    </article>
                                </div>
                                <div v-else class="settlement-lines-empty"><b>No manual components recorded.</b><span>Add each independently approved item; no default legal amounts are supplied.</span></div>
                                <div v-if="settlementItemError" class="inline-error" role="alert">{{ settlementItemError }}</div>
                                <div class="settlement-detail-footer">
                                    <span v-if="selectedSettlement.status === 'approved'" class="payroll-approved-note">Approved {{ formatDate(selectedSettlement.reviewed_at) }} · no payment action</span><span v-else-if="selectedSettlement.status === 'in_review'" class="payroll-waiting-note">Workpaper is locked for review.</span><span v-else-if="selectedSettlement.status === 'changes_requested'" class="payroll-returned-note">Update requested lines and resubmit.</span><span v-else class="payroll-draft-note">{{ settlementClearancesComplete(selectedSettlement) ? 'Clearances complete. Verify every line before submission.' : 'Complete all exit checklist tasks before submitting for review.' }}</span>
                                    <div class="settlement-detail-actions">
                                        <button v-if="['draft', 'changes_requested'].includes(selectedSettlement.status)" class="button button-soft" :disabled="busy || !settlementItemDrafts[selectedSettlement.id]?.length" @click="saveSettlementItems">Save workpaper lines</button>
                                        <button v-if="['draft', 'changes_requested'].includes(selectedSettlement.status)" class="button button-primary" :disabled="busy || !selectedSettlement.items.length || !settlementClearancesComplete(selectedSettlement) || settlementItemsChanged(selectedSettlement)" @click="submitSettlement">Submit for review →</button>
                                        <button v-if="selectedSettlement.status === 'in_review'" class="button button-soft" @click="openSettlementReview('return')">Request changes</button>
                                        <button v-if="selectedSettlement.status === 'in_review'" class="button button-primary" @click="openSettlementReview('approve')">Approve workpaper</button>
                                    </div>
                                </div>
                            </section>
                        </section>
                        <div class="separation-policy-note"><span>ⓘ</span><p>Final settlement is a manually prepared, reviewable workpaper only. No statutory computation, bank instruction, payroll settlement, or payment status is produced.</p><span>HR &amp; admin only</span></div>
                    </template>
                    <section v-else class="panel payroll-restricted"><span class="empty-icon">▣</span><b>Separation records are restricted</b><p>Exit clearances and settlement workpapers are visible to HR and administrators only.</p></section>
                </template>

                <template v-else-if="activeSlug === 'performance'">
                    <template v-if="canManageOrganization">
                        <section class="growth-hero performance-hero">
                            <div><span class="eyebrow">GROWTH &amp; DEVELOPMENT <span>•</span> PERFORMANCE</span><h1>Make progress visible.</h1><p>Set clear review periods, document outcomes, and keep every decision human and traceable.</p></div>
                            <div class="growth-hero-actions"><button class="button growth-outline-button" :disabled="!performanceData.employees.length" @click="exportPerformanceReviews">↓ Export reviews</button><button class="button growth-primary-button" :disabled="!performanceData.employees.length" @click="openPerformanceCycleForm">＋ Create review cycle <span>→</span></button></div>
                        </section>
                        <section class="growth-metrics" aria-label="Performance workspace summary">
                            <article><span class="growth-metric-icon">◷</span><div><span class="eyebrow">OPEN CYCLES</span><b>{{ performanceData.meta.open_cycles ?? 0 }}</b><small>Active review periods</small></div></article>
                            <article><span class="growth-metric-icon growth-metric-review">✎</span><div><span class="eyebrow">NEEDS ATTENTION</span><b>{{ performanceData.meta.reviews_in_progress ?? 0 }}</b><small>Drafts and reviews</small></div></article>
                            <article><span class="growth-metric-icon growth-metric-complete">✓</span><div><span class="eyebrow">APPROVED REVIEWS</span><b>{{ performanceData.meta.reviews_approved ?? 0 }}</b><small>Recorded decisions</small></div></article>
                        </section>
                        <section class="panel growth-panel">
                            <div class="growth-toolbar">
                                <nav class="growth-tabs" aria-label="Performance workspace"><button :class="{ active: performanceTab === 'overview' }" @click="performanceTab = 'overview'; selectedPerformanceCycleId = null">Overview</button><button :class="{ active: performanceTab === 'cycles' }" @click="performanceTab = 'cycles'; selectedPerformanceCycleId = null">Review cycles <span>{{ performanceData.cycles.length }}</span></button><button :class="{ active: performanceTab === 'reviews' }" @click="performanceTab = 'reviews'">Review records <span>{{ performanceData.reviews.length }}</span></button></nav>
                                <div v-if="performanceTab === 'reviews'" class="growth-toolbar-filters"><label><span class="visually-hidden">Filter reviews by status</span><select v-model="performanceStatusFilter"><option value="all">All statuses</option><option value="draft">Draft</option><option value="changes_requested">Changes requested</option><option value="in_review">In review</option><option value="approved">Approved</option></select></label><label class="table-search growth-search"><span>⌕</span><input v-model="performanceSearch" aria-label="Search performance reviews" placeholder="Search employee or cycle"></label><button class="button button-soft growth-export" :disabled="performanceExporting" @click="exportPerformanceReviews">↓ CSV</button></div>
                            </div>
                            <section v-if="showPerformanceCycleForm" class="growth-form">
                                <div class="growth-form-heading"><div><span class="eyebrow">REVIEW CYCLE SETUP</span><h2>Start with a clear review window.</h2><p>Creates one private draft per selected employee. No scores or employment decisions are generated automatically.</p></div><button class="icon-button" aria-label="Close review cycle form" @click="showPerformanceCycleForm = false; performanceCycleError = ''">×</button></div>
                                <form class="growth-form-grid" @submit.prevent="createPerformanceCycle">
                                    <label>Cycle name<input v-model.trim="performanceCycleForm.title" maxlength="140" required placeholder="Annual growth review"></label>
                                    <label v-if="!user.branch_id">Branch scope<select v-model="performanceCycleForm.branch_id"><option value="">All company branches</option><option v-for="branch in performanceData.branches" :key="branch.id" :value="String(branch.id)">{{ branch.name }}</option></select></label>
                                    <label>Review period begins<input v-model="performanceCycleForm.period_start" type="date" required></label>
                                    <label>Review period ends<input v-model="performanceCycleForm.period_end" type="date" :min="performanceCycleForm.period_start" required></label>
                                    <label>Reviews due by<input v-model="performanceCycleForm.due_date" type="date" :min="performanceCycleForm.period_end"></label>
                                    <label class="growth-field-wide">Purpose and guidance<textarea v-model.trim="performanceCycleForm.description" rows="2" maxlength="4000" placeholder="Share context and expectations for reviewers"></textarea></label>
                                    <fieldset class="growth-employee-picker growth-field-wide"><legend>Employees included <span>{{ performanceCycleForm.employee_ids.length }} selected</span></legend><div class="growth-picker-tools"><span>Only active employees in the selected scope are listed.</span><button type="button" class="text-link" @click="toggleAllPerformanceEmployees">{{ performanceCycleForm.employee_ids.length === performanceCycleEmployees.length ? 'Clear selection' : 'Select all' }}</button></div><div v-if="performanceCycleEmployees.length" class="growth-employee-options"><label v-for="employee in performanceCycleEmployees" :key="employee.id"><input type="checkbox" :checked="performanceCycleForm.employee_ids.includes(employee.id)" @change="togglePerformanceEmployee(employee.id)"><span><b>{{ fullName(employee) }}</b><small>{{ employee.employee_code }} · {{ employee.department || 'Team not set' }}</small></span><small>{{ employee.branch?.name || 'Company' }}</small></label></div><div v-else class="growth-picker-empty">No active employees are available in this scope.</div></fieldset>
                                    <div v-if="performanceCycleError" class="inline-error growth-field-wide" role="alert">{{ performanceCycleError }}</div>
                                    <div class="growth-form-footer growth-field-wide"><span>Review text and ratings are manually entered; use your approved internal review policy.</span><div><button type="button" class="button button-soft" @click="showPerformanceCycleForm = false">Cancel</button><button type="submit" class="button button-primary" :disabled="busy || !performanceCycleForm.employee_ids.length">Create cycle &amp; drafts</button></div></div>
                                </form>
                            </section>
                            <template v-if="performanceTab === 'overview'">
                                <div class="growth-overview-grid">
                                    <article class="growth-overview-card"><div class="growth-section-heading"><div><span class="eyebrow">REVIEW PROGRAM</span><h2>Consistent conversations, grounded in evidence.</h2><p>Run a review cycle with explicit participants, a defined period, and a documented decision.</p></div><button class="text-link" @click="performanceTab = 'cycles'">View all cycles →</button></div><div class="growth-mini-stats"><span><b>{{ performanceData.cycles.length }}</b><small>Total cycles</small></span><span><b>{{ performanceData.reviews.length }}</b><small>Individual reviews</small></span><span><b>{{ performanceData.meta.reviews_approved ?? 0 }}</b><small>Approved</small></span></div><button class="button button-primary" :disabled="!performanceData.employees.length" @click="openPerformanceCycleForm">Create a review cycle →</button></article>
                                    <article class="growth-overview-card"><div class="growth-section-heading"><div><span class="eyebrow">REVIEW QUEUE</span><h2>Review the work, not just the number.</h2><p>Pending drafts, returned records, and submitted reviews stay easy to find.</p></div><button class="text-link" @click="performanceTab = 'reviews'; performanceStatusFilter = 'in_review'; selectedPerformanceCycleId = null">Open queue →</button></div><div v-if="performanceData.reviews.some((review) => review.status === 'in_review')" class="growth-queue-list"><button v-for="review in performanceData.reviews.filter((entry) => entry.status === 'in_review').slice(0, 4)" :key="review.id" class="growth-queue-row" @click="selectedPerformanceReviewId = review.id; selectedPerformanceCycleId = null; performanceTab = 'reviews'"><span class="avatar">{{ initials({ first_name: review.employee_name }) }}</span><span><b>{{ review.employee_name }}</b><small>{{ review.cycle?.title }} · Rating {{ review.rating }}/5</small></span><span>Review →</span></button></div><div v-else class="growth-empty-inline"><span class="growth-empty-symbol">✓</span><b>No reviews awaiting approval</b><p>Submitted review records will appear here for an HR decision.</p></div></article>
                                </div>
                                <div class="growth-boundary-note"><span>ⓘ</span><p>Ratings are human-entered and descriptive only. No ranking, promotion, compensation, disciplinary, or termination recommendation is generated.</p><span>HR &amp; admin only</span></div>
                            </template>
                            <section v-else-if="performanceTab === 'cycles'" class="growth-cycle-list">
                                <article v-for="cycle in performanceData.cycles" :key="cycle.id" class="growth-cycle-card"><span class="growth-cycle-mark">✳</span><div class="growth-cycle-copy"><span class="eyebrow">{{ cycle.branch?.name || 'COMPANY-WIDE' }} <span class="dot-separator">•</span> {{ formatDate(cycle.period_start) }} – {{ formatDate(cycle.period_end) }}</span><h3>{{ cycle.title }}</h3><p>{{ cycle.description || 'No review guidance added.' }}</p><small>{{ cycle.reviews_count }} participants · {{ cycle.completed_review_count }} approved<span v-if="cycle.due_date"> · Due {{ formatDate(cycle.due_date) }}</span></small></div><span class="status-pill" :class="cycle.status === 'open' ? 'benefit-status-active' : 'status-planned'">{{ cycle.status }}</span><button class="button button-soft growth-cycle-open" @click="openPerformanceCycle(cycle)">Open reviews →</button><button v-if="(!user.branch_id || cycle.branch_id) && cycle.status === 'open' && cycle.reviews_count === cycle.completed_review_count" class="button button-soft growth-cycle-status" :disabled="busy" @click="updatePerformanceCycleStatus(cycle, 'closed')">Close cycle</button><button v-else-if="(!user.branch_id || cycle.branch_id) && cycle.status === 'closed'" class="button button-soft growth-cycle-status" :disabled="busy" @click="updatePerformanceCycleStatus(cycle, 'open')">Reopen</button></article>
                                <div v-if="!performanceData.cycles.length" class="growth-empty-state"><span class="empty-icon">◷</span><b>Start with a review cycle</b><p>Set the review period, include employees explicitly, and create private draft records.</p><button class="button button-primary" :disabled="!performanceData.employees.length" @click="openPerformanceCycleForm">Create first cycle</button></div>
                            </section>
                            <section v-else-if="performanceTab === 'reviews'" class="growth-review-layout">
                                <div class="growth-review-list-panel"><div class="growth-review-list-heading"><span class="eyebrow">{{ selectedPerformanceCycleId ? performanceData.cycles.find((cycle) => cycle.id === selectedPerformanceCycleId)?.title || 'CYCLE REVIEWS' : 'INDIVIDUAL REVIEW REGISTER' }}</span><button v-if="selectedPerformanceCycleId" class="text-link" @click="selectedPerformanceCycleId = null">Show all</button><b v-else>{{ visiblePerformanceReviews.length }} records</b></div><button v-for="review in visiblePerformanceReviews" :key="review.id" class="growth-review-option" :class="{ active: selectedPerformanceReviewId === review.id }" @click="selectedPerformanceReviewId = review.id"><span class="avatar">{{ initials({ first_name: review.employee_name }) }}</span><span><b>{{ review.employee_name }}</b><small>{{ review.employee_code }} · {{ review.department || 'Team not set' }}</small><small>{{ review.cycle?.title }}</small></span><span class="status-pill" :class="`benefit-status-${review.status}`">{{ review.status.replace('_', ' ') }}</span></button><div v-if="!visiblePerformanceReviews.length" class="growth-empty-inline"><b>No reviews match</b><p>{{ performanceData.reviews.length ? 'Adjust the search, cycle, or status filter.' : 'Create a cycle to start your first review.' }}</p></div></div>
                                <article v-if="selectedPerformanceReview" class="growth-review-detail"><div class="growth-detail-heading"><span class="avatar">{{ initials({ first_name: selectedPerformanceReview.employee_name }) }}</span><div><span class="eyebrow">{{ selectedPerformanceReview.cycle?.title }} <span class="dot-separator">•</span> {{ formatDate(selectedPerformanceReview.cycle?.period_start) }} – {{ formatDate(selectedPerformanceReview.cycle?.period_end) }}</span><h2>{{ selectedPerformanceReview.employee_name }}</h2><p>{{ selectedPerformanceReview.employee_code }} · {{ selectedPerformanceReview.department || 'Team not set' }}</p></div><span class="status-pill" :class="`benefit-status-${selectedPerformanceReview.status}`">{{ selectedPerformanceReview.status.replace('_', ' ') }}</span></div><div v-if="selectedPerformanceReview.review_note" class="payroll-review-note"><b>Reviewer note</b><p>{{ selectedPerformanceReview.review_note }}</p></div>
                                    <div v-if="performanceReviewDrafts[selectedPerformanceReview.id]" class="growth-review-fields"><label>Manual review rating<select v-model="performanceReviewDrafts[selectedPerformanceReview.id].rating" :disabled="!['draft', 'changes_requested'].includes(selectedPerformanceReview.status)"><option value="">Not rated</option><option value="1">1 · Needs support</option><option value="2">2 · Developing</option><option value="3">3 · Consistent</option><option value="4">4 · Strong</option><option value="5">5 · Exceptional</option></select><small>Human-entered descriptive input; no automatic score.</small></label><label>Goals and expectations<textarea v-model.trim="performanceReviewDrafts[selectedPerformanceReview.id].goals" rows="3" maxlength="4000" :disabled="!['draft', 'changes_requested'].includes(selectedPerformanceReview.status)" placeholder="What outcomes were agreed for this period?"></textarea></label><label>Results and evidence<textarea v-model.trim="performanceReviewDrafts[selectedPerformanceReview.id].results" rows="3" maxlength="4000" :disabled="!['draft', 'changes_requested'].includes(selectedPerformanceReview.status)" placeholder="Record outcomes and supporting context"></textarea></label><label>Recognized strengths<textarea v-model.trim="performanceReviewDrafts[selectedPerformanceReview.id].strengths" rows="2" maxlength="4000" :disabled="!['draft', 'changes_requested'].includes(selectedPerformanceReview.status)" placeholder="Specific strengths and contributions"></textarea></label><label>Development focus<textarea v-model.trim="performanceReviewDrafts[selectedPerformanceReview.id].development_focus" rows="2" maxlength="4000" :disabled="!['draft', 'changes_requested'].includes(selectedPerformanceReview.status)" placeholder="Agreed next steps and support"></textarea></label></div>
                                    <div v-if="performanceReviewErrors[selectedPerformanceReview.id]" class="inline-error" role="alert">{{ performanceReviewErrors[selectedPerformanceReview.id] }}</div><div class="growth-detail-footer"><span v-if="selectedPerformanceReview.status === 'approved'" class="payroll-approved-note">Approved {{ formatDate(selectedPerformanceReview.reviewed_at) }} · recorded only</span><span v-else-if="selectedPerformanceReview.status === 'in_review'" class="payroll-waiting-note">Locked while awaiting HR review.</span><span v-else class="payroll-draft-note">Manual narrative and rating; no employment action is triggered.</span><div class="growth-detail-actions"><button v-if="['draft', 'changes_requested'].includes(selectedPerformanceReview.status)" class="button button-soft" :disabled="busy || !performanceReviewChanged(selectedPerformanceReview)" @click="savePerformanceReview(selectedPerformanceReview)">Save draft</button><button v-if="['draft', 'changes_requested'].includes(selectedPerformanceReview.status)" class="button button-primary" :disabled="busy || performanceReviewChanged(selectedPerformanceReview) || !selectedPerformanceReview.rating || !selectedPerformanceReview.goals || !selectedPerformanceReview.results" @click="submitPerformanceReview(selectedPerformanceReview)">Submit for review →</button><button v-if="selectedPerformanceReview.status === 'in_review'" class="button button-soft" @click="openPerformanceReviewDecision(selectedPerformanceReview, 'return')">Request changes</button><button v-if="selectedPerformanceReview.status === 'in_review'" class="button button-primary" @click="openPerformanceReviewDecision(selectedPerformanceReview, 'approve')">Approve review</button></div></div>
                                </article><div v-else class="growth-review-detail growth-empty-inline"><b>Select an employee review</b><p>Choose an entry from the register to review its narrative and decision status.</p></div>
                            </section>
                        </section>
                        <div class="growth-boundary-note"><span>ⓘ</span><p>Performance records are confidential internal workpapers. Ratings are manually entered and do not determine pay, promotion, discipline, or termination.</p><span>HR &amp; admin only</span></div>
                    </template>
                    <section v-else class="panel payroll-restricted"><span class="empty-icon">▣</span><b>Performance records are restricted</b><p>Review cycle and employee review records are available to HR and administrators only.</p></section>
                </template>

                <template v-else-if="activeSlug === 'training'">
                    <template v-if="canManageOrganization">
                        <section class="growth-hero training-hero"><div><span class="eyebrow">GROWTH &amp; DEVELOPMENT <span>•</span> LEARNING STUDIO</span><h1>Build skills with intention.</h1><p>Plan learning programs, assign the right people, and keep completion history in one place.</p></div><div class="growth-hero-actions"><button class="button growth-outline-button" :disabled="!trainingData.enrollments.length" @click="exportTrainingEnrollments">↓ Export register</button><button class="button growth-primary-button" :disabled="!trainingData.programs.some((program) => program.status === 'active') || !trainingData.employees.length" @click="openTrainingEnrollmentForm">＋ Assign learning <span>→</span></button><button class="button growth-secondary-button" @click="openTrainingProgramForm">＋ New program</button></div></section>
                        <section class="growth-metrics" aria-label="Training workspace summary"><article><span class="growth-metric-icon">▤</span><div><span class="eyebrow">ACTIVE PROGRAMS</span><b>{{ trainingData.meta.active_programs ?? 0 }}</b><small>Available learning</small></div></article><article><span class="growth-metric-icon growth-metric-review">◷</span><div><span class="eyebrow">IN PROGRESS</span><b>{{ trainingData.meta.active_enrollments ?? 0 }}</b><small>Assigned or underway</small></div></article><article><span class="growth-metric-icon growth-metric-complete">✓</span><div><span class="eyebrow">COMPLETED</span><b>{{ trainingData.meta.completed_enrollments ?? 0 }}</b><small>Recorded completions</small></div></article><article><span class="growth-metric-icon growth-metric-overdue">!</span><div><span class="eyebrow">PAST DUE</span><b>{{ trainingData.meta.overdue_enrollments ?? 0 }}</b><small>Manual follow-up needed</small></div></article></section>
                        <section class="panel growth-panel">
                            <div class="growth-toolbar"><nav class="growth-tabs" aria-label="Training workspace"><button :class="{ active: trainingTab === 'overview' }" @click="trainingTab = 'overview'">Overview</button><button :class="{ active: trainingTab === 'catalog' }" @click="trainingTab = 'catalog'">Program catalog <span>{{ trainingData.programs.length }}</span></button><button :class="{ active: trainingTab === 'enrollments' }" @click="trainingTab = 'enrollments'">Assignments <span>{{ trainingData.enrollments.length }}</span></button></nav><div v-if="trainingTab !== 'overview'" class="growth-toolbar-filters"><label v-if="trainingTab === 'enrollments'"><span class="visually-hidden">Filter assignments by status</span><select v-model="trainingStatusFilter"><option value="all">All statuses</option><option value="assigned">Assigned</option><option value="in_progress">In progress</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select></label><label class="table-search growth-search"><span>⌕</span><input v-model="trainingSearch" :aria-label="trainingTab === 'catalog' ? 'Search training programs' : 'Search assignments'" :placeholder="trainingTab === 'catalog' ? 'Search programs or providers' : 'Search employee or program'"></label><button v-if="trainingTab === 'enrollments'" class="button button-soft growth-export" :disabled="trainingExporting" @click="exportTrainingEnrollments">↓ CSV</button></div></div>
                            <section v-if="showTrainingProgramForm" class="growth-form"><div class="growth-form-heading"><div><span class="eyebrow">PROGRAM SETTINGS</span><h2>{{ editingTrainingProgramId ? 'Edit learning program' : 'Create a learning program' }}</h2><p>Maintain a simple catalog. Completion and credentials are recorded manually; no provider is contacted.</p></div><button class="icon-button" aria-label="Close training program form" @click="showTrainingProgramForm = false; trainingProgramError = ''">×</button></div><form class="growth-form-grid" @submit.prevent="saveTrainingProgram"><label>Program name<input v-model.trim="trainingProgramForm.title" maxlength="160" required placeholder="Manager essentials"></label><label>Category<select v-model="trainingProgramForm.category"><option value="leadership">Leadership</option><option value="technical">Technical</option><option value="compliance">Compliance</option><option value="safety">Safety</option><option value="onboarding">Onboarding</option><option value="professional">Professional development</option><option value="other">Other</option></select></label><label>Delivery format<select v-model="trainingProgramForm.delivery_mode"><option value="classroom">Classroom</option><option value="virtual">Virtual</option><option value="self_paced">Self-paced</option><option value="blended">Blended</option></select></label><label>Provider (optional)<input v-model.trim="trainingProgramForm.provider" maxlength="180" placeholder="Internal or external provider"></label><label>Starts on<input v-model="trainingProgramForm.start_date" type="date"></label><label>Target completion date<input v-model="trainingProgramForm.due_date" type="date" :min="trainingProgramForm.start_date"></label><label>Enrollment capacity<input v-model="trainingProgramForm.capacity" type="number" min="1" max="100000" placeholder="No limit"></label><label v-if="editingTrainingProgramId">Program status<select v-model="trainingProgramForm.status"><option value="active">Active</option><option value="archived">Archived</option></select></label><label v-if="!user.branch_id">Branch scope<select v-model="trainingProgramForm.branch_id" :disabled="Boolean(editingTrainingProgramId)"><option value="">Company-wide</option><option v-for="branch in trainingData.branches" :key="branch.id" :value="String(branch.id)">{{ branch.name }}</option></select></label><label class="growth-field-wide">Program outline<textarea v-model.trim="trainingProgramForm.description" rows="2" maxlength="4000" placeholder="Summarize learning outcomes and audience"></textarea></label><div v-if="trainingProgramError" class="inline-error growth-field-wide" role="alert">{{ trainingProgramError }}</div><div class="growth-form-footer growth-field-wide"><span>Use internal policy to determine requirements and credit.</span><div><button type="button" class="button button-soft" @click="showTrainingProgramForm = false">Cancel</button><button type="submit" class="button button-primary" :disabled="busy">{{ editingTrainingProgramId ? 'Save settings' : 'Create program' }}</button></div></div></form></section>
                            <section v-if="showTrainingEnrollmentForm" class="growth-form"><div class="growth-form-heading"><div><span class="eyebrow">EMPLOYEE ASSIGNMENT</span><h2>Assign a learning program.</h2><p>Creates a manual assignment record; it does not register the learner with a provider.</p></div><button class="icon-button" aria-label="Close training assignment form" @click="showTrainingEnrollmentForm = false; trainingEnrollmentError = ''">×</button></div><form class="growth-form-grid" @submit.prevent="createTrainingEnrollment"><label>Learning program<select v-model="trainingEnrollmentForm.training_program_id" required><option value="" disabled>Select an active program</option><option v-for="program in trainingData.programs.filter((entry) => entry.status === 'active')" :key="program.id" :value="String(program.id)">{{ program.title }}{{ program.due_date ? ` · Due ${formatDate(program.due_date)}` : '' }}</option></select></label><label>Employee<select v-model="trainingEnrollmentForm.employee_id" required><option value="" disabled>Select employee</option><option v-for="employee in trainingEnrollmentEmployees" :key="employee.id" :value="String(employee.id)">{{ fullName(employee) }} · {{ employee.employee_code }}</option></select></label><label class="growth-field-wide">Assignment note<textarea v-model.trim="trainingEnrollmentForm.notes" rows="2" maxlength="2000" placeholder="Optional context or follow-up"></textarea></label><div v-if="trainingEnrollmentError" class="inline-error growth-field-wide" role="alert">{{ trainingEnrollmentError }}</div><div class="growth-form-footer growth-field-wide"><span>Capacity and duplicate assignments are checked before saving.</span><div><button type="button" class="button button-soft" @click="showTrainingEnrollmentForm = false">Cancel</button><button type="submit" class="button button-primary" :disabled="busy || !trainingEnrollmentForm.training_program_id || !trainingEnrollmentForm.employee_id">Assign employee</button></div></div></form></section>
                            <template v-if="trainingTab === 'overview'"><div class="growth-overview-grid"><article class="growth-overview-card"><div class="growth-section-heading"><div><span class="eyebrow">LEARNING CATALOG</span><h2>Learning that moves with your team.</h2><p>Keep internal and provider-led programs organized with clear owners, dates, and formats.</p></div><button class="text-link" @click="trainingTab = 'catalog'">Browse catalog →</button></div><div v-if="trainingData.programs.length" class="training-program-preview"><article v-for="program in trainingData.programs.filter((entry) => entry.status === 'active').slice(0, 4)" :key="program.id"><span class="training-program-icon">✳</span><span><b>{{ program.title }}</b><small>{{ program.category }} · {{ program.delivery_mode.replace('_', ' ') }}<span v-if="program.due_date"> · {{ formatDate(program.due_date) }}</span></small></span><b class="training-preview-count">{{ program.active_enrollment_count }} assigned</b></article></div><div v-else class="growth-empty-inline"><span class="growth-empty-symbol">▤</span><b>Your catalog is ready to grow</b><p>Create a program before assigning employees.</p><button class="button button-primary" @click="openTrainingProgramForm">Create first program</button></div></article><article class="growth-overview-card"><div class="growth-section-heading"><div><span class="eyebrow">LEARNING MOMENTUM</span><h2>Progress, made practical.</h2><p>Monitor assignments and record completion when it is confirmed.</p></div><button class="text-link" @click="trainingTab = 'enrollments'">View assignments →</button></div><div class="growth-mini-stats"><span><b>{{ trainingData.meta.active_enrollments ?? 0 }}</b><small>Assigned / in progress</small></span><span><b>{{ trainingData.meta.completed_enrollments ?? 0 }}</b><small>Completions</small></span><span><b>{{ trainingData.meta.overdue_enrollments ?? 0 }}</b><small>Past due</small></span></div><button class="button button-primary" :disabled="!trainingData.programs.some((program) => program.status === 'active') || !trainingData.employees.length" @click="openTrainingEnrollmentForm">Assign learning →</button></article></div><div class="growth-boundary-note"><span>ⓘ</span><p>Assignments and completions are internal records. No attendance is inferred, certificates are not issued, and external providers are not contacted.</p><span>HR &amp; admin only</span></div></template>
                            <template v-else-if="trainingTab === 'catalog'"><div class="training-program-grid"><article v-for="program in visibleTrainingPrograms" :key="program.id" class="training-program-card"><div class="training-program-card-top"><span class="training-program-icon">✳</span><span class="status-pill" :class="program.status === 'active' ? 'benefit-status-active' : 'status-planned'">{{ program.status }}</span></div><span class="eyebrow">{{ program.category.toUpperCase() }} <span class="dot-separator">•</span> {{ program.delivery_mode.replace('_', ' ').toUpperCase() }}</span><h3>{{ program.title }}</h3><p>{{ program.description || 'No program outline added.' }}</p><div class="training-program-meta"><span>{{ program.provider || 'Internal program' }}</span><span>{{ program.branch?.name || 'Company-wide' }}</span><span v-if="program.start_date">Starts {{ formatDate(program.start_date) }}</span><span v-if="program.due_date">Due {{ formatDate(program.due_date) }}</span></div><div class="training-capacity"><span><b>{{ program.active_enrollment_count }}</b> assigned<span v-if="program.capacity"> of {{ program.capacity }}</span></span><span>{{ program.completed_enrollment_count }} completed</span></div><div class="training-program-actions"><button v-if="!user.branch_id || program.branch_id" class="button button-soft" @click="openTrainingProgramForm(program)">⚙ Configure</button><button v-if="program.status === 'active'" class="button button-primary" :disabled="!trainingData.employees.length || (program.capacity && program.active_enrollment_count >= program.capacity)" @click="openTrainingEnrollmentForm(program)">＋ Assign employee</button></div></article><div v-if="!visibleTrainingPrograms.length" class="growth-empty-state"><span class="empty-icon">▤</span><b>{{ trainingData.programs.length ? 'No programs match your search' : 'Create your first learning program' }}</b><p>{{ trainingData.programs.length ? 'Try another title, provider, or category.' : 'Document a course or development program before making assignments.' }}</p><button v-if="!trainingData.programs.length" class="button button-primary" @click="openTrainingProgramForm">Create program</button></div></div></template>
                            <section v-else-if="trainingTab === 'enrollments'" class="growth-review-layout training-assignment-layout"><div class="growth-review-list-panel"><div class="growth-review-list-heading"><span class="eyebrow">LEARNING REGISTER</span><b>{{ visibleTrainingEnrollments.length }} records</b></div><button v-for="enrollment in visibleTrainingEnrollments" :key="enrollment.id" class="growth-review-option" :class="{ active: selectedTrainingEnrollmentId === enrollment.id }" @click="selectedTrainingEnrollmentId = enrollment.id; trainingEnrollmentUpdateError = ''"><span class="avatar">{{ initials({ first_name: enrollment.employee_name }) }}</span><span><b>{{ enrollment.employee_name }}</b><small>{{ enrollment.employee_code }} · {{ enrollment.department || 'Team not set' }}</small><small>{{ enrollment.program?.title }}</small></span><span class="status-pill" :class="`benefit-status-${enrollment.status}`">{{ enrollment.status.replace('_', ' ') }}</span></button><div v-if="!visibleTrainingEnrollments.length" class="growth-empty-inline"><b>{{ trainingData.enrollments.length ? 'No assignments match' : 'No learning assigned yet' }}</b><p>{{ trainingData.enrollments.length ? 'Adjust the status or search filter.' : 'Select a program and employee to create the first assignment.' }}</p><button v-if="!trainingData.enrollments.length && trainingData.programs.some((program) => program.status === 'active')" class="button button-primary" @click="openTrainingEnrollmentForm">Assign learning</button></div></div><article v-if="selectedTrainingEnrollment" class="growth-review-detail"><div class="growth-detail-heading"><span class="training-program-icon">✳</span><div><span class="eyebrow">{{ selectedTrainingEnrollment.program?.category }} <span class="dot-separator">•</span> {{ selectedTrainingEnrollment.program?.delivery_mode.replace('_', ' ') }}</span><h2>{{ selectedTrainingEnrollment.employee_name }}</h2><p>{{ selectedTrainingEnrollment.employee_code }} · {{ selectedTrainingEnrollment.program?.title }}</p></div><span class="status-pill" :class="`benefit-status-${selectedTrainingEnrollment.status}`">{{ selectedTrainingEnrollment.status.replace('_', ' ') }}</span></div><div class="training-detail-facts"><span><small>PROVIDER</small><b>{{ selectedTrainingEnrollment.program?.provider || 'Internal program' }}</b></span><span><small>PROGRAM WINDOW</small><b>{{ selectedTrainingEnrollment.program?.start_date ? formatDate(selectedTrainingEnrollment.program.start_date) : 'Flexible' }}<span v-if="selectedTrainingEnrollment.program?.due_date"> – {{ formatDate(selectedTrainingEnrollment.program.due_date) }}</span></b></span><span><small>ASSIGNED BRANCH</small><b>{{ selectedTrainingEnrollment.branch?.name || 'Company-wide' }}</b></span><span><small>COMPLETION</small><b>{{ selectedTrainingEnrollment.completed_at ? formatDate(selectedTrainingEnrollment.completed_at) : 'Not recorded' }}</b></span></div><label class="training-detail-label">Assignment status<select v-model="trainingEnrollmentDrafts[selectedTrainingEnrollment.id].status" :disabled="selectedTrainingEnrollment.status === 'completed' || selectedTrainingEnrollment.status === 'cancelled'"><option v-for="status in trainingStatusOptions(selectedTrainingEnrollment)" :key="status" :value="status">{{ status.replace('_', ' ') }}</option></select></label><label class="training-detail-label">Completion / follow-up note<textarea v-model.trim="trainingEnrollmentDrafts[selectedTrainingEnrollment.id].notes" rows="3" maxlength="2000" :disabled="selectedTrainingEnrollment.status === 'completed' || selectedTrainingEnrollment.status === 'cancelled'" placeholder="Record a verified completion or follow-up note"></textarea></label><div v-if="trainingEnrollmentUpdateError" class="inline-error" role="alert">{{ trainingEnrollmentUpdateError }}</div><div class="growth-detail-footer"><span class="payroll-draft-note">Completion is manually recorded; no certificate or attendance is inferred.</span><div class="growth-detail-actions"><button v-if="!['completed', 'cancelled'].includes(selectedTrainingEnrollment.status)" class="button button-primary" :disabled="busy || !trainingEnrollmentChanged(selectedTrainingEnrollment)" @click="saveTrainingEnrollment(selectedTrainingEnrollment)">Save assignment</button></div></div></article><div v-else class="growth-review-detail growth-empty-inline"><b>Select a learning assignment</b><p>Review the program details and update its recorded status.</p></div></section>
                        </section>
                        <div class="growth-boundary-note"><span>ⓘ</span><p>Training assignments and completion dates are manually recorded. Course delivery, attendance, credentials, accreditation, and provider integrations are not automated.</p><span>HR &amp; admin only</span></div>
                    </template>
                    <section v-else class="panel payroll-restricted"><span class="empty-icon">▣</span><b>Training records are restricted</b><p>Program settings, employee assignments, and completion records are available to HR and administrators only.</p></section>
                </template>

                <template v-else-if="activeSlug === 'employee-self-service'">
                    <section v-if="selfServiceEmployee" class="page-heading self-service-heading">
                        <div>
                            <span class="eyebrow">MY WORKSPACE <span class="dot-separator">•</span> EMPLOYEE SELF-SERVICE</span>
                            <h1>Welcome back, {{ selfServiceEmployee.first_name }}.</h1>
                            <p>Your profile, time, and leave information in one private place.</p>
                        </div>
                        <div class="self-service-heading-actions">
                            <span class="self-service-date">{{ new Date().toLocaleDateString([], { weekday: 'long', month: 'long', day: 'numeric' }) }}</span>
                            <button class="button button-primary self-service-punch" :disabled="busy || selfServiceEmployee.status !== 'active' || Boolean(employeeAttendance?.check_out)" @click="attendanceAction">
                                {{ employeeAttendance?.check_out ? 'Shift complete' : employeeAttendance?.check_in ? 'Check out' : 'Check in' }}
                                <span aria-hidden="true">{{ employeeAttendance?.check_in && !employeeAttendance?.check_out ? '↗' : '→' }}</span>
                            </button>
                        </div>
                    </section>

                    <section v-if="!selfServiceEmployee" class="panel self-service-unlinked" role="status">
                        <span class="self-service-empty-icon">◎</span>
                        <div><span class="eyebrow">EMPLOYEE ACCOUNT LINK NEEDED</span><h2>Your employee profile is not linked yet.</h2><p>Ask your HR administrator to link your sign-in to an active employee record. No other employee information is shown here.</p></div>
                    </section>

                    <template v-else>
                        <nav class="self-service-tabs" aria-label="Employee self-service sections">
                            <button :class="{ active: selfServiceTab === 'overview' }" @click="selfServiceTab = 'overview'">Overview</button>
                            <button :class="{ active: selfServiceTab === 'profile' }" @click="selfServiceTab = 'profile'">My profile</button>
                            <button :class="{ active: selfServiceTab === 'leave' }" @click="selfServiceTab = 'leave'">Leave <span>{{ selfServicePendingLeaveCount }}</span></button>
                            <button :class="{ active: selfServiceTab === 'attendance' }" @click="selfServiceTab = 'attendance'">Attendance</button>
                        </nav>

                        <template v-if="selfServiceTab === 'overview'">
                            <section class="self-service-metrics" aria-label="Personal overview">
                                <article class="self-service-metric"><span class="self-service-metric-icon">◷</span><div><span class="eyebrow">LAST 30 DAYS</span><b>{{ selfServiceData.attendance.length }}</b><small>recorded days</small></div></article>
                                <article class="self-service-metric"><span class="self-service-metric-icon">▤</span><div><span class="eyebrow">LEAVE AVAILABLE</span><b>{{ selfServiceAvailableLeaveDays.toLocaleString() }}</b><small>days across balances</small></div></article>
                                <article class="self-service-metric"><span class="self-service-metric-icon">↗</span><div><span class="eyebrow">REQUESTS IN REVIEW</span><b>{{ selfServicePendingLeaveCount }}</b><small>awaiting a decision</small></div></article>
                                <article class="self-service-metric"><span class="self-service-metric-icon">◎</span><div><span class="eyebrow">PROFILE DETAILS</span><b>{{ selfServiceContactComplete }}/2</b><small>contact fields provided</small></div></article>
                            </section>

                            <section class="self-service-overview-grid">
                                <article class="panel self-service-panel self-service-profile-card">
                                    <div class="self-service-panel-heading"><div><span class="eyebrow">YOUR PROFILE</span><h2>{{ fullName(selfServiceEmployee) }}</h2><p>{{ selfServiceEmployee.job_title || 'Role not set' }} <span>·</span> {{ selfServiceEmployee.department || 'Department not set' }}</p></div><span class="avatar self-service-avatar">{{ initials(selfServiceEmployee) }}</span></div>
                                    <div class="self-service-profile-details">
                                        <div><small>Employee ID</small><b>{{ selfServiceEmployee.employee_code || '—' }}</b></div>
                                        <div><small>Work email</small><b>{{ selfServiceEmployee.work_email || 'Not provided' }}</b></div>
                                        <div><small>Phone</small><b>{{ selfServiceEmployee.phone || 'Not provided' }}</b></div>
                                        <div><small>Manager</small><b>{{ selfServiceEmployee.manager?.name || 'Not assigned' }}</b></div>
                                        <div><small>Location</small><b>{{ selfServiceEmployee.branch?.name || 'Not assigned' }}</b></div>
                                        <div><small>Start date</small><b>{{ formatDate(selfServiceEmployee.join_date) }}</b></div>
                                    </div>
                                    <button class="text-link self-service-edit-link" @click="selfServiceTab = 'profile'">Review contact details →</button>
                                </article>

                                <article class="panel self-service-panel self-service-attendance-card">
                                    <div class="self-service-panel-heading"><div><span class="eyebrow">TODAY</span><h2>{{ employeeAttendance?.check_out ? 'Your shift is complete' : employeeAttendance?.check_in ? 'You are checked in' : 'Ready when you are' }}</h2><p>{{ employeeAttendance?.check_in ? `${formatTime(employeeAttendance.check_in)}${employeeAttendance.check_out ? ` – ${formatTime(employeeAttendance.check_out)}` : ' · Check out when you finish'}` : 'Record your attendance for today.' }}</p></div><span class="self-service-status-dot" :class="{ checked: employeeAttendance?.check_in }"></span></div>
                                    <button class="button button-primary button-wide" :disabled="busy || selfServiceEmployee.status !== 'active' || Boolean(employeeAttendance?.check_out)" @click="attendanceAction">{{ employeeAttendance?.check_out ? 'Attendance complete' : employeeAttendance?.check_in ? 'Check out' : 'Check in' }} <span aria-hidden="true">→</span></button>
                                    <div v-if="selfServiceAttendanceError" class="inline-error" role="alert">{{ selfServiceAttendanceError }}</div>
                                    <div v-if="selfServiceUpcomingLeave" class="self-service-next-leave"><span>UP NEXT</span><b>{{ selfServiceUpcomingLeave.leave_type?.name }} · {{ formatDate(selfServiceUpcomingLeave.start_date) }}</b><small>Approved leave</small></div>
                                    <div v-else class="self-service-next-leave self-service-next-leave-empty"><span>LEAVE PLANNER</span><b>No approved leave coming up</b><button class="text-link" @click="selfServiceTab = 'leave'">View balances or request leave →</button></div>
                                </article>
                            </section>

                            <section class="self-service-overview-grid self-service-lower-grid">
                                <article class="panel self-service-panel">
                                    <div class="self-service-section-title"><div><span class="eyebrow">LEAVE BALANCES</span><h2>Time away, at a glance.</h2></div><button class="text-link" @click="selfServiceTab = 'leave'">View leave →</button></div>
                                    <div v-if="selfServiceData.balances.length" class="self-service-balance-list">
                                        <div v-for="balance in selfServiceData.balances" :key="balance.id" class="self-service-balance-row"><span><b>{{ balance.leave_type?.name || 'Leave' }}</b><small>{{ Number(balance.used_days).toLocaleString() }} of {{ Number(balance.entitled_days).toLocaleString() }} days used</small></span><b>{{ Math.max(0, Number(balance.entitled_days) - Number(balance.used_days)).toLocaleString() }} <small>days left</small></b><span class="self-service-balance-track"><i :style="{ width: `${Math.min(100, Number(balance.entitled_days) > 0 ? (Number(balance.used_days) / Number(balance.entitled_days)) * 100 : 0)}%` }"></i></span></div>
                                    </div>
                                    <div v-else class="self-service-empty-inline"><b>No leave balance has been set up.</b><p>HR can configure your annual leave entitlements.</p></div>
                                </article>
                                <article class="panel self-service-panel">
                                    <div class="self-service-section-title"><div><span class="eyebrow">RECENT ACTIVITY</span><h2>Your latest attendance.</h2></div><button class="text-link" @click="selfServiceTab = 'attendance'">View history →</button></div>
                                    <div v-if="selfServiceData.attendance.length" class="self-service-activity-list"><div v-for="entry in selfServiceData.attendance.slice(0, 4)" :key="entry.id"><span class="self-service-activity-date">{{ formatDate(entry.attendance_date) }}</span><span><b>{{ formatTime(entry.check_in) }} – {{ formatTime(entry.check_out) }}</b><small>{{ entry.status || (entry.check_out ? 'Complete' : 'In progress') }}</small></span><span class="self-service-activity-mark">{{ entry.check_out ? '✓' : '·' }}</span></div></div>
                                    <div v-else class="self-service-empty-inline"><b>No attendance entries yet.</b><p>Your check-ins and check-outs will appear here.</p><button class="text-link" @click="attendanceAction">Record today’s check-in →</button></div>
                                </article>
                            </section>

                            <aside class="self-service-boundary-note"><span aria-hidden="true">ⓘ</span><p>Only your own profile, leave, and attendance are shown. Payslips, letters, and private documents will appear here when their secure workflows are available.</p></aside>
                        </template>

                        <section v-else-if="selfServiceTab === 'profile'" class="self-service-detail-grid">
                            <article class="panel self-service-panel self-service-identity-card"><span class="avatar self-service-avatar">{{ initials(selfServiceEmployee) }}</span><span class="eyebrow">EMPLOYEE PROFILE</span><h2>{{ fullName(selfServiceEmployee) }}</h2><p>{{ selfServiceEmployee.job_title || 'Role not set' }}</p><span class="self-service-employee-code">{{ selfServiceEmployee.employee_code || 'Employee ID unavailable' }}</span><div class="self-service-identity-divider"></div><div><small>Department</small><b>{{ selfServiceEmployee.department || 'Not assigned' }}</b></div><div><small>Branch</small><b>{{ selfServiceEmployee.branch?.name || 'Not assigned' }}</b></div><div><small>Reports to</small><b>{{ selfServiceEmployee.manager?.name || 'Not assigned' }}</b></div><div><small>Employment type</small><b>{{ selfServiceEmployee.employment_type || 'Not set' }}</b></div></article>
                            <article class="panel self-service-panel self-service-contact-panel"><div class="self-service-section-title"><div><span class="eyebrow">CONTACT INFORMATION</span><h2>Keep your details current.</h2><p>Only work email and phone can be edited here. Contact HR to change other employment records.</p></div></div><form class="self-service-contact-form" @submit.prevent="saveSelfServiceProfile"><label>Work email<input v-model.trim="selfServiceProfileForm.work_email" type="email" maxlength="190" autocomplete="email" placeholder="name@company.com"></label><label>Phone number<input v-model.trim="selfServiceProfileForm.phone" type="tel" maxlength="40" autocomplete="tel" placeholder="+971 50 000 0000"></label><div v-if="selfServiceProfileError" class="inline-error" role="alert">{{ selfServiceProfileError }}</div><div class="self-service-form-footer"><span>Changes update your employee contact record.</span><button class="button button-primary" type="submit" :disabled="busy || !selfServiceProfileChanged">{{ busy ? 'Saving…' : 'Save contact details' }}</button></div></form></article>
                        </section>

                        <section v-else-if="selfServiceTab === 'leave'" class="self-service-leave-grid">
                            <article class="panel self-service-panel"><div class="self-service-section-title"><div><span class="eyebrow">YOUR ENTITLEMENTS</span><h2>Leave balances</h2><p>Current-year entitlement and recorded usage.</p></div></div><div v-if="selfServiceData.balances.length" class="self-service-balance-list self-service-balance-list-large"><div v-for="balance in selfServiceData.balances" :key="balance.id" class="self-service-balance-row"><span><b>{{ balance.leave_type?.name || 'Leave' }}</b><small>{{ Number(balance.used_days).toLocaleString() }} of {{ Number(balance.entitled_days).toLocaleString() }} days used</small></span><b>{{ Math.max(0, Number(balance.entitled_days) - Number(balance.used_days)).toLocaleString() }} <small>days left</small></b><span class="self-service-balance-track"><i :style="{ width: `${Math.min(100, Number(balance.entitled_days) > 0 ? (Number(balance.used_days) / Number(balance.entitled_days)) * 100 : 0)}%` }"></i></span></div></div><div v-else class="self-service-empty-inline"><b>No balances are available yet.</b><p>Contact HR if you believe an entitlement should be visible.</p></div></article>
                            <article class="panel self-service-panel"><div class="self-service-section-title"><div><span class="eyebrow">NEW REQUEST</span><h2>Plan time away.</h2><p>Requests are sent to your configured approver for review.</p></div></div><form class="self-service-leave-form" @submit.prevent="submitSelfServiceLeaveRequest"><label>Leave type<select v-model="leaveForm.leave_type_id" required><option value="" disabled>Select leave type</option><option v-for="type in selfServiceData.leave_types" :key="type.id" :value="String(type.id)">{{ type.name }}{{ type.is_paid ? ' · Paid' : ' · Unpaid' }}</option></select></label><div class="self-service-date-fields"><label>First day<input v-model="leaveForm.start_date" type="date" :min="localDateInput(new Date())" required></label><label>Last day<input v-model="leaveForm.end_date" type="date" :min="leaveForm.start_date || localDateInput(new Date())" required></label></div><label>Reason for request<textarea v-model.trim="leaveForm.reason" rows="3" maxlength="2000" required placeholder="Add context for your approver"></textarea></label><div v-if="selfServiceLeaveError" class="inline-error" role="alert">{{ selfServiceLeaveError }}</div><button class="button button-primary" type="submit" :disabled="busy || !selfServiceData.leave_types.length">{{ busy ? 'Submitting…' : 'Submit leave request' }} <span aria-hidden="true">→</span></button></form></article>
                            <article class="panel self-service-panel self-service-request-panel"><div class="self-service-section-title"><div><span class="eyebrow">REQUEST HISTORY</span><h2>Your leave requests.</h2><p>Latest 20 requests and their current decisions.</p></div><span class="self-service-request-count">{{ selfServiceData.leave_requests.length }}</span></div><div v-if="selfServiceData.leave_requests.length" class="self-service-request-list"><article v-for="request in selfServiceData.leave_requests" :key="request.id"><span class="self-service-request-icon">▤</span><span class="self-service-request-main"><b>{{ request.leave_type?.name || 'Leave' }}</b><small>{{ formatDate(request.start_date) }} – {{ formatDate(request.end_date) }} <span>·</span> {{ Number(request.requested_days).toLocaleString() }} days</small><small v-if="request.reason" class="self-service-request-reason">{{ request.reason }}</small></span><span class="self-service-status-pill" :class="`self-service-status-${request.status}`">{{ request.status.replace('_', ' ') }}</span></article></div><div v-else class="self-service-empty-inline"><b>No leave requests yet.</b><p>Your submitted requests will be listed here.</p></div></article>
                        </section>

                        <section v-else class="panel self-service-panel self-service-attendance-history"><div class="self-service-section-title"><div><span class="eyebrow">PERSONAL TIME RECORD</span><h2>Attendance history</h2><p>Your latest 30 attendance entries. For corrections, contact your manager or HR.</p></div><button class="button button-soft" :disabled="busy || selfServiceEmployee.status !== 'active' || Boolean(employeeAttendance?.check_out)" @click="attendanceAction">{{ employeeAttendance?.check_out ? 'Shift complete' : employeeAttendance?.check_in ? 'Check out' : 'Check in' }}</button></div><div v-if="selfServiceAttendanceError" class="inline-error self-service-attendance-error" role="alert">{{ selfServiceAttendanceError }}</div><div v-if="selfServiceData.attendance.length" class="self-service-attendance-table-wrap"><table class="self-service-attendance-table"><thead><tr><th>Date</th><th>Check in</th><th>Check out</th><th>Status</th></tr></thead><tbody><tr v-for="entry in selfServiceData.attendance" :key="entry.id"><td>{{ formatDate(entry.attendance_date) }}</td><td>{{ formatTime(entry.check_in) }}</td><td>{{ formatTime(entry.check_out) }}</td><td><span class="self-service-status-pill" :class="entry.check_out ? 'self-service-status-approved' : 'self-service-status-pending'">{{ entry.status || (entry.check_out ? 'Complete' : 'In progress') }}</span></td></tr></tbody></table></div><div v-else class="self-service-empty-inline"><b>No attendance recorded yet.</b><p>Your personal punches will appear here after you check in.</p><button class="button button-primary" :disabled="busy || selfServiceEmployee.status !== 'active'" @click="attendanceAction">Record today’s check-in</button></div></section>
                    </template>
                </template>

                <template v-else-if="activeSlug === 'manager-self-service'">
                    <template v-if="user.role === 'manager'">
                        <section class="page-heading manager-service-heading">
                            <div><span class="eyebrow">MY WORKSPACE <span class="dot-separator">•</span> MANAGER SELF-SERVICE</span><h1>Lead your team with clarity.</h1><p>Team attendance, leave decisions, and people details — scoped to your direct reports.</p></div>
                            <span class="status-pill status-foundation">Manager workspace</span>
                        </section>
                        <nav class="self-service-tabs manager-service-tabs" aria-label="Manager self-service sections">
                            <button :class="{ active: managerServiceTab === 'overview' }" @click="managerServiceTab = 'overview'">Overview</button>
                            <button :class="{ active: managerServiceTab === 'team' }" @click="managerServiceTab = 'team'">My team <span>{{ managerTeam.length }}</span></button>
                            <button :class="{ active: managerServiceTab === 'leave' }" @click="managerServiceTab = 'leave'">Leave approvals <span>{{ managerPendingLeaveRequests.length }}</span></button>
                            <button :class="{ active: managerServiceTab === 'attendance' }" @click="managerServiceTab = 'attendance'">Attendance</button>
                        </nav>

                        <template v-if="managerServiceTab === 'overview'">
                            <section class="self-service-metrics manager-service-metrics" aria-label="Team overview">
                                <article class="self-service-metric"><span class="self-service-metric-icon">♙</span><div><span class="eyebrow">DIRECT REPORTS</span><b>{{ managerTeam.length }}</b><small>{{ managerTeam.filter((employee) => employee.status === 'active').length }} active employees</small></div></article>
                                <article class="self-service-metric"><span class="self-service-metric-icon">▤</span><div><span class="eyebrow">LEAVE TO REVIEW</span><b>{{ managerPendingLeaveRequests.length }}</b><small>pending decisions</small></div></article>
                                <article class="self-service-metric"><span class="self-service-metric-icon">◷</span><div><span class="eyebrow">CHECKED IN TODAY</span><b>{{ managerWorkingCount }}</b><small>recorded active punches</small></div></article>
                                <article class="self-service-metric"><span class="self-service-metric-icon">↗</span><div><span class="eyebrow">ON APPROVED LEAVE</span><b>{{ managerOnLeaveToday.length }}</b><small>direct reports today</small></div></article>
                            </section>
                            <section class="manager-service-overview-grid">
                                <article class="panel self-service-panel manager-service-team-panel">
                                    <div class="self-service-section-title"><div><span class="eyebrow">YOUR PEOPLE</span><h2>Direct reports</h2><p>People and attendance updates in your reporting scope.</p></div><button class="text-link" @click="managerServiceTab = 'team'">View team →</button></div>
                                    <div v-if="managerTeam.length" class="manager-service-team-list">
                                        <article v-for="employee in managerTeam.slice(0, 5)" :key="employee.id"><span class="avatar self-service-avatar">{{ initials(employee) }}</span><span class="manager-service-person"><b>{{ fullName(employee) }}</b><small>{{ employee.job_title || 'Role not set' }} · {{ employee.department || 'Team not set' }}</small></span><span class="self-service-status-pill" :class="managerTodayAttendance.some((entry) => entry.employee_id === employee.id) ? 'self-service-status-approved' : 'self-service-status-pending'">{{ managerTodayAttendance.some((entry) => entry.employee_id === employee.id) ? 'Punch recorded' : 'No punch yet' }}</span></article>
                                    </div>
                                    <div v-else class="self-service-empty-inline"><b>No direct reports are linked to your profile.</b><p>Ask HR to review your reporting line. Team data stays limited to direct reports.</p></div>
                                </article>
                                <article class="panel self-service-panel manager-service-approval-panel">
                                    <div class="self-service-section-title"><div><span class="eyebrow">DECISIONS AWAITING YOU</span><h2>Leave approvals</h2><p>Review requests from your direct reports.</p></div><button class="text-link" @click="managerServiceTab = 'leave'">Open queue →</button></div>
                                    <div v-if="managerPendingLeaveRequests.length" class="manager-service-pending-list">
                                        <article v-for="request in managerPendingLeaveRequests.slice(0, 4)" :key="request.id"><span class="avatar">{{ initials(request.employee) }}</span><span class="manager-service-pending-copy"><b>{{ fullName(request.employee) }}</b><small>{{ request.leave_type?.name }} · {{ Number(request.requested_days).toLocaleString() }} days</small><small>{{ formatDate(request.start_date) }} – {{ formatDate(request.end_date) }}</small></span><button class="button button-soft manager-service-review-button" @click="managerServiceTab = 'leave'">Review queue</button></article>
                                    </div>
                                    <div v-else class="self-service-empty-inline"><b>You’re all caught up.</b><p>New direct-report leave requests will appear here.</p></div>
                                </article>
                            </section>
                            <section class="panel self-service-panel manager-service-attendance-panel">
                                <div class="self-service-section-title"><div><span class="eyebrow">RECENT TEAM ATTENDANCE</span><h2>Today’s recorded punches.</h2><p>Missing punches are not marked as absences because shift schedules are not connected.</p></div><button class="text-link" @click="managerServiceTab = 'attendance'">Attendance history →</button></div>
                                <div v-if="managerTodayAttendance.length" class="manager-service-attendance-list"><div v-for="entry in managerTodayAttendance" :key="entry.id"><span class="avatar">{{ initials(entry.employee) }}</span><span class="manager-service-person"><b>{{ fullName(entry.employee) }}</b><small>{{ entry.employee?.department || 'Team not set' }}</small></span><span class="manager-service-time">{{ formatTime(entry.check_in) }}</span><span class="self-service-status-pill" :class="entry.check_out ? 'self-service-status-approved' : 'self-service-status-pending'">{{ entry.check_out ? `Finished ${formatTime(entry.check_out)}` : 'Working' }}</span></div></div>
                                <div v-else class="self-service-empty-inline"><b>No team punches have been recorded today.</b><p>This is a punch register, not a schedule or attendance compliance decision.</p><button class="text-link" @click="managerServiceTab = 'attendance'">Review recent attendance →</button></div>
                            </section>
                            <aside class="self-service-boundary-note"><span aria-hidden="true">ⓘ</span><p>Manager views and leave decisions are limited to your direct reports. Attendance indicators show saved punches only; schedules, absences, payroll, and team-wide actions are not inferred or exposed.</p></aside>
                        </template>

                        <section v-else-if="managerServiceTab === 'team'" class="panel self-service-panel manager-service-register">
                            <div class="self-service-section-title"><div><span class="eyebrow">REPORTING SCOPE</span><h2>My team</h2><p>{{ managerTeam.length }} direct reports · personal employment details are limited to what you need to lead your team.</p></div></div>
                            <div v-if="managerTeam.length" class="manager-service-team-register"><article v-for="employee in managerTeam" :key="employee.id"><span class="avatar self-service-avatar">{{ initials(employee) }}</span><span class="manager-service-person"><b>{{ fullName(employee) }}</b><small>{{ employee.employee_code }} · {{ employee.job_title || 'Role not set' }}</small></span><span class="manager-service-team-department">{{ employee.department || 'Team not set' }}</span><span><small>Today</small><b class="manager-service-team-status">{{ managerTodayAttendance.find((entry) => entry.employee_id === employee.id) ? 'Punch recorded' : 'No punch recorded' }}</b></span><span class="self-service-status-pill" :class="employee.status === 'active' ? 'self-service-status-approved' : 'self-service-status-pending'">{{ employee.status }}</span></article></div>
                            <div v-else class="self-service-empty-inline"><b>Your team list is empty.</b><p>Once HR links direct reports to your employee profile, they’ll appear here.</p></div>
                        </section>

                        <section v-else-if="managerServiceTab === 'leave'" class="panel self-service-panel manager-service-register">
                            <div class="self-service-section-title"><div><span class="eyebrow">TEAM REQUESTS</span><h2>Leave review queue</h2><p>Requests from your direct reports only. Approval records a leave decision and updates the configured balance.</p></div><span class="self-service-request-count">{{ managerPendingLeaveRequests.length }} pending</span></div>
                            <div v-if="managerTeamLeaveRequests.length" class="manager-service-leave-list"><article v-for="request in managerTeamLeaveRequests" :key="request.id"><span class="avatar">{{ initials(request.employee) }}</span><span class="manager-service-pending-copy"><b>{{ fullName(request.employee) }} <small>· {{ request.employee?.department || 'Team' }}</small></b><small>{{ request.leave_type?.name }} · {{ Number(request.requested_days).toLocaleString() }} working days</small><small>{{ formatDate(request.start_date) }} – {{ formatDate(request.end_date) }} <span>·</span> {{ request.reason }}</small><small v-if="request.review_note" class="manager-service-review-note">Review note: {{ request.review_note }}</small></span><span class="self-service-status-pill" :class="`self-service-status-${request.status === 'rejected' ? 'rejected' : request.status}`">{{ request.status.replace('_', ' ') }}</span><div v-if="request.status === 'pending'" class="manager-service-request-actions"><button class="button button-soft" :disabled="busy" @click="openManagerLeaveReview(request, 'rejected')">Decline</button><button class="button button-primary" :disabled="busy" @click="openManagerLeaveReview(request, 'approved')">Approve</button></div></article></div>
                            <div v-else class="self-service-empty-inline"><b>No leave requests from your team.</b><p>New requests will be listed here for your review.</p></div>
                        </section>

                        <section v-else class="panel self-service-panel manager-service-register">
                            <div class="self-service-section-title"><div><span class="eyebrow">TEAM TIME RECORD</span><h2>Attendance register</h2><p>Recorded punches for your direct reports over the last seven days. No schedule or absence status is inferred.</p></div><span class="self-service-request-count">{{ managerTeamAttendance.length }} records</span></div>
                            <div v-if="managerTeamAttendance.length" class="self-service-attendance-table-wrap"><table class="self-service-attendance-table manager-service-attendance-table"><thead><tr><th>Employee</th><th>Date</th><th>Check in</th><th>Check out</th><th>Record</th></tr></thead><tbody><tr v-for="entry in managerTeamAttendance" :key="entry.id"><td>{{ fullName(entry.employee) }}</td><td>{{ formatDate(entry.attendance_date) }}</td><td>{{ formatTime(entry.check_in) }}</td><td>{{ formatTime(entry.check_out) }}</td><td>{{ entry.check_out ? 'Complete' : 'In progress' }}</td></tr></tbody></table></div>
                            <div v-else class="self-service-empty-inline"><b>No attendance entries found for the last seven days.</b><p>Only recorded entries are listed. Ask HR if you need to correct a record.</p></div>
                        </section>
                    </template>
                    <section v-else class="panel payroll-restricted"><span class="empty-icon">♙</span><b>Manager workspace</b><p>This view is available to manager accounts with direct reports. Use the employee and attendance workspaces for other role-based access.</p></section>
                </template>

                <template v-else-if="activeSlug === 'expenses'">
                    <section class="page-heading expense-heading">
                        <div><span class="eyebrow">WORKPLACE <span class="dot-separator">•</span> EXPENSE CONTROL</span><h1>Expenses, handled clearly.</h1><p>Submit claims with supporting receipts and keep every decision auditable.</p></div>
                        <button v-if="canSubmitExpenses" class="button button-primary" @click="expenseFormOpen = !expenseFormOpen">{{ expenseFormOpen ? 'Close claim form' : '＋ New expense claim' }}</button>
                    </section>
                    <section class="growth-metrics expense-metrics" aria-label="Expense claim summary">
                        <article><span class="growth-metric-icon">▤</span><div><span class="eyebrow">CLAIMS IN SCOPE</span><b>{{ expenseData.meta.total }}</b><small>Visible in your access scope</small></div></article>
                        <article><span class="growth-metric-icon growth-metric-review">◷</span><div><span class="eyebrow">PENDING REVIEW</span><b>{{ expenseData.meta.pending }}</b><small>Awaiting a decision</small></div></article>
                        <article><span class="growth-metric-icon growth-metric-complete">✓</span><div><span class="eyebrow">APPROVED</span><b>{{ expenseData.meta.approved }}</b><small>Approval is not payment</small></div></article>
                        <article><span class="growth-metric-icon growth-metric-overdue">¤</span><div><span class="eyebrow">APPROVED VALUE</span><b class="expense-total-value">{{ expenseApprovedTotals }}</b><small>Grouped by claim currency</small></div></article>
                    </section>
                    <section class="panel expense-workspace">
                        <section v-if="expenseFormOpen" class="growth-form expense-form">
                            <div class="growth-form-heading"><div><span class="eyebrow">EMPLOYEE CLAIM</span><h2>Record an expense.</h2><p>Your claim is submitted immediately and becomes read-only while it is reviewed. Receipts are private and available only to you and authorized reviewers.</p></div><button type="button" class="icon-button" aria-label="Close expense claim form" @click="expenseFormOpen = false; expenseFormError = ''">×</button></div>
                            <form class="growth-form-grid" @submit.prevent="submitExpenseClaim">
                                <label>Category<select v-model="expenseForm.category" required><option v-for="category in expenseData.categories" :key="category" :value="category">{{ expenseCategoryLabel(category) }}</option></select></label>
                                <label>Merchant or provider<input v-model.trim="expenseForm.merchant" maxlength="140" required placeholder="e.g. City cab"></label>
                                <label>Date of expense<input v-model="expenseForm.expense_date" type="date" :max="localDateInput(new Date())" required></label>
                                <label>Amount ({{ user.employee?.currency || user.company?.currency || 'USD' }})<input v-model="expenseForm.amount" type="number" min="0.01" max="9999999999.99" step="0.01" inputmode="decimal" required placeholder="0.00"></label>
                                <label class="growth-field-wide">Business purpose<textarea v-model.trim="expenseForm.description" rows="3" maxlength="2000" required placeholder="Explain the business purpose and who or what it supported"></textarea></label>
                                <label class="growth-field-wide">Receipt (PDF, JPG, or PNG · up to 5 MB)<input type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" @change="expenseReceiptFile = $event.target.files?.[0] || null"><small v-if="expenseReceiptFile" class="expense-file-name">{{ expenseReceiptFile.name }}</small></label>
                                <div v-if="expenseFormError" class="inline-error growth-field-wide" role="alert">{{ expenseFormError }}</div>
                                <div class="growth-form-footer growth-field-wide"><span>Submitting locks this claim for review. Approval does not initiate reimbursement or a bank payment.</span><div><button type="button" class="button button-soft" @click="expenseFormOpen = false">Cancel</button><button type="submit" class="button button-primary" :disabled="busy">{{ busy ? 'Submitting…' : 'Submit claim' }}</button></div></div>
                            </form>
                        </section>
                        <div class="expense-toolbar">
                            <nav class="growth-tabs" aria-label="Expense claim views">
                                <button :class="{ active: expenseTab === 'all' }" @click="expenseTab = 'all'">All claims <span>{{ expenseData.meta.total }}</span></button>
                                <button v-if="canReviewExpenses" :class="{ active: expenseTab === 'review' }" @click="expenseTab = 'review'">My review queue <span>{{ expenseReviewQueue.length }}</span></button>
                            </nav>
                            <div class="expense-filters">
                                <label><span class="visually-hidden">Filter expense claims by status</span><select v-model="expenseStatusFilter"><option value="all">All statuses</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select></label>
                                <label class="table-search growth-search"><span>⌕</span><input v-model="expenseSearch" aria-label="Search expense claims" placeholder="Search person, merchant, purpose"></label>
                            </div>
                        </div>
                        <div v-if="expenseData.meta.truncated" class="expense-truncation-note">Showing the latest {{ expenseData.meta.claim_limit }} records in your access scope. Narrow results or contact HR for older claims.</div>
                        <div v-if="visibleExpenseClaims.length" class="expense-claim-list">
                            <article v-for="claim in visibleExpenseClaims" :key="claim.id" class="expense-claim-card">
                                <div class="expense-claim-main">
                                    <span class="expense-category-mark" aria-hidden="true">{{ claim.category === 'transport' ? '↗' : claim.category === 'meals' ? '◉' : claim.category === 'accommodation' ? '⌂' : '▤' }}</span>
                                    <div class="expense-claim-copy"><div class="expense-claim-title"><h2>{{ claim.merchant }}</h2><span class="self-service-status-pill" :class="`self-service-status-${claim.status}`">{{ claim.status }}</span></div><p>{{ expenseCategoryLabel(claim.category) }} · {{ formatDate(claim.expense_date) }}<template v-if="canReviewExpenses"> · {{ claim.employee_name }} ({{ claim.employee_code }})</template></p><p class="expense-description">{{ claim.description }}</p><small v-if="claim.review_note" class="expense-review-note">Review note: {{ claim.review_note }}<template v-if="claim.reviewer?.name"> · {{ claim.reviewer.name }}</template></small></div>
                                </div>
                                <div class="expense-claim-side"><b>{{ claim.currency }} {{ Number(claim.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</b><a v-if="claim.receipt_path" class="expense-receipt-link" :href="`/api/expenses/${claim.id}/receipt`">↓ Receipt <span class="visually-hidden">for {{ claim.merchant }}</span></a><span v-else class="expense-no-receipt">No receipt attached</span><div v-if="claim.can_review" class="expense-review-actions"><button class="button button-soft" :disabled="busy" @click="openExpenseReview(claim, 'reject')">Reject</button><button class="button button-primary" :disabled="busy" @click="openExpenseReview(claim, 'approve')">Approve</button></div><span v-else-if="claim.status === 'pending'" class="expense-pending-copy">Awaiting review</span></div>
                            </article>
                        </div>
                        <div v-else class="expense-empty-state"><span class="empty-icon">¤</span><b>{{ expenseTab === 'review' ? 'Your review queue is clear.' : expenseSearch || expenseStatusFilter !== 'all' ? 'No claims match these filters.' : 'No expense claims yet.' }}</b><p>{{ expenseTab === 'review' ? 'New claims from employees in your review scope will appear here.' : 'Submit a claim when you have a business expense to record.' }}</p><button v-if="canSubmitExpenses && expenseTab === 'all' && !expenseFormOpen" class="button button-primary" @click="expenseFormOpen = true">Create your first claim</button><button v-else-if="expenseSearch || expenseStatusFilter !== 'all'" class="button button-soft" @click="expenseSearch = ''; expenseStatusFilter = 'all'">Clear filters</button></div>
                    </section>
                    <p class="expense-boundary-note"><span>ⓘ</span> Approval records an internal decision only. Payment, reimbursement settlement, tax treatment, and accounting exports are not connected.</p>
                </template>

                <template v-else-if="activeSlug === 'loans-advances'">
                    <section class="page-heading loan-heading">
                        <div><span class="eyebrow">WORKPLACE <span class="dot-separator">•</span> LOANS &amp; ADVANCES</span><h1>Clear requests. Clear repayments.</h1><p>Track applications, approved schedules, and manually reconciled repayments.</p></div>
                        <button v-if="canApplyForLoan && !loanData.loans.some((loan) => loan.status === 'pending' || loan.status === 'approved')" class="button button-primary" @click="loanFormOpen = !loanFormOpen">{{ loanFormOpen ? 'Close application' : '＋ Request an advance' }}</button>
                    </section>
                    <section class="growth-metrics loan-metrics" aria-label="Loan and advance summary">
                        <article><span class="growth-metric-icon">▤</span><div><span class="eyebrow">REQUESTS IN SCOPE</span><b>{{ loanData.meta.total }}</b><small>Visible within your access scope</small></div></article>
                        <article><span class="growth-metric-icon growth-metric-review">◷</span><div><span class="eyebrow">PENDING REVIEW</span><b>{{ loanData.meta.pending }}</b><small>Awaiting an internal decision</small></div></article>
                        <article><span class="growth-metric-icon growth-metric-complete">✓</span><div><span class="eyebrow">APPROVED LOANS</span><b>{{ loanData.meta.approved }}</b><small>Approval is not disbursement</small></div></article>
                        <article><span class="growth-metric-icon growth-metric-overdue">¤</span><div><span class="eyebrow">OUTSTANDING SCHEDULES</span><b class="loan-total-value">{{ loanOutstandingTotals }}</b><small>Recorded schedule balance by currency</small></div></article>
                    </section>
                    <section class="panel loan-workspace">
                        <section v-if="loanFormOpen" class="growth-form loan-form">
                            <div class="growth-form-heading"><div><span class="eyebrow">EMPLOYEE APPLICATION</span><h2>Request a salary advance or loan.</h2><p>Submitting creates an internal request only. Terms, legal eligibility, and funding remain subject to company policy and authorized review.</p></div><button type="button" class="icon-button" aria-label="Close loan application" @click="loanFormOpen = false; loanFormError = ''">×</button></div>
                            <form class="growth-form-grid" @submit.prevent="submitLoanRequest">
                                <label>Amount requested ({{ user.employee?.currency || user.company?.currency || 'USD' }})<input v-model="loanForm.requested_amount" type="number" min="0.01" max="9999999999.99" step="0.01" inputmode="decimal" required placeholder="0.00"></label>
                                <label>Preferred installment count<input v-model="loanForm.requested_installments" type="number" min="1" max="60" step="1" required><small>Preference only; reviewer sets final terms.</small></label>
                                <label class="growth-field-wide">Purpose<textarea v-model.trim="loanForm.purpose" rows="3" maxlength="2000" required placeholder="Briefly explain the reason for this request"></textarea></label>
                                <div v-if="loanFormError" class="inline-error growth-field-wide" role="alert">{{ loanFormError }}</div>
                                <div class="growth-form-footer growth-field-wide"><span>No interest, eligibility, or statutory calculations are inferred. This app does not issue funds or deduct payroll.</span><div><button type="button" class="button button-soft" @click="loanFormOpen = false">Cancel</button><button type="submit" class="button button-primary" :disabled="busy">{{ busy ? 'Submitting…' : 'Submit request' }}</button></div></div>
                            </form>
                        </section>
                        <div class="loan-toolbar">
                            <nav class="growth-tabs" aria-label="Loan request views">
                                <button :class="{ active: loanTab === 'all' }" @click="loanTab = 'all'">All requests <span>{{ loanData.meta.total }}</span></button>
                                <button v-if="canReviewExpenses" :class="{ active: loanTab === 'review' }" @click="loanTab = 'review'">My review queue <span>{{ loanReviewQueue.length }}</span></button>
                            </nav>
                            <div class="loan-filters">
                                <label><span class="visually-hidden">Filter loan requests by status</span><select v-model="loanStatusFilter"><option value="all">All statuses</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="repaid">Repaid</option><option value="rejected">Rejected</option></select></label>
                                <label class="table-search growth-search"><span>⌕</span><input v-model="loanSearch" aria-label="Search loan requests" placeholder="Search employee or purpose"></label>
                            </div>
                        </div>
                        <div v-if="loanData.meta.truncated" class="expense-truncation-note">Showing the latest {{ loanData.meta.limit }} records in your access scope.</div>
                        <div v-if="visibleLoanAdvances.length" class="loan-list">
                            <article v-for="loan in visibleLoanAdvances" :key="loan.id" class="loan-card">
                                <div class="loan-card-header">
                                    <div class="loan-card-identity"><span class="expense-category-mark">¤</span><div>                                    <div class="loan-card-title"><h2>{{ loan.employee_name }}</h2><span class="self-service-status-pill" :class="loan.repayment_status === 'repaid' ? 'self-service-status-approved' : `self-service-status-${loan.status}`">{{ loan.repayment_status === 'repaid' ? 'repaid' : loan.status.replace('_', ' ') }}</span></div><p>{{ loan.employee_code }}<template v-if="loan.department"> · {{ loan.department }}</template><template v-if="canReviewExpenses"> · Submitted {{ formatDate(loan.created_at) }}</template></p></div></div>
                                    <div class="loan-card-amount"><b>{{ formatLoanMoney(loan.approved_amount || loan.requested_amount, loan.currency) }}</b><small>{{ loan.status === 'approved' ? `${formatLoanMoney(loan.total_repaid, loan.currency)} repaid · ${formatLoanMoney(loan.outstanding_amount, loan.currency)} outstanding` : `${loan.requested_installments} requested installments` }}</small></div>
                                </div>
                                <div class="loan-card-details"><p>{{ loan.purpose }}</p><small v-if="loan.review_note" class="expense-review-note">Review note: {{ loan.review_note }}<template v-if="loan.reviewer?.name"> · {{ loan.reviewer.name }}</template></small></div>
                                <div v-if="loan.can_review" class="loan-review-actions"><button class="button button-soft" :disabled="busy" @click="openLoanReview(loan, 'reject')">Reject</button><button class="button button-primary" :disabled="busy" @click="openLoanReview(loan, 'approve')">Approve &amp; set schedule</button></div>
                                <section v-if="loan.status === 'approved' && loan.installments?.length" class="loan-schedule">
                                    <div class="loan-schedule-heading"><div><span class="eyebrow">PROPOSED REPAYMENT PLAN</span><h3>{{ loan.repayment_installments }} installments <span>·</span> starts {{ formatDate(loan.first_due_date) }}</h3></div><span class="loan-schedule-total">{{ formatLoanMoney(loan.approved_amount, loan.currency) }}</span></div>
                                    <div class="loan-installment-list">
                                        <article v-for="installment in loan.installments" :key="installment.id" class="loan-installment">
                                            <div class="loan-installment-number"><span>{{ String(installment.sequence).padStart(2, '0') }}</span><div><b>{{ formatDate(installment.due_date) }}</b><small>{{ formatLoanMoney(installment.amount, loan.currency) }} scheduled</small></div></div>
                                            <div class="loan-installment-paid"><span>{{ formatLoanMoney(installment.repaid_amount, loan.currency) }} reconciled</span><b>{{ formatLoanMoney(installment.remaining_amount, loan.currency) }} remaining</b></div>
                                            <button v-if="loan.can_record_repayment && Number(installment.remaining_amount) > 0" class="button button-soft loan-record-button" :disabled="busy" @click="openLoanRepayment(installment)">{{ loanRepaymentInstallmentId === installment.id ? 'Close' : 'Record external repayment' }}</button>
                                            <form v-if="loanRepaymentInstallmentId === installment.id" class="loan-repayment-form" @submit.prevent="submitLoanRepayment(loan)">
                                                <label>Amount reconciled<input v-model="loanRepaymentForm.amount" type="number" min="0.01" :max="installment.remaining_amount" step="0.01" inputmode="decimal" required></label>
                                                <label>Recorded date<input v-model="loanRepaymentForm.recorded_date" type="date" :max="localDateInput(new Date())" required></label>
                                                <label>External reference (optional)<input v-model.trim="loanRepaymentForm.reference" maxlength="120" placeholder="Receipt or payroll reference"></label>
                                                <div v-if="loanRepaymentError" class="inline-error loan-repayment-error" role="alert">{{ loanRepaymentError }}</div>
                                                <div class="loan-repayment-submit"><span>Reconciliation record only; no payment is processed.</span><button type="submit" class="button button-primary" :disabled="busy">{{ busy ? 'Saving…' : 'Save repayment record' }}</button></div>
                                            </form>
                                        </article>
                                    </div>
                                </section>
                            </article>
                        </div>
                        <div v-else class="expense-empty-state"><span class="empty-icon">¤</span><b>{{ loanTab === 'review' ? 'Your review queue is clear.' : loanSearch || loanStatusFilter !== 'all' ? 'No requests match these filters.' : 'No loan requests yet.' }}</b><p>{{ loanTab === 'review' ? 'New requests from employees in your review scope will appear here.' : 'Submit a request to begin the review process.' }}</p><button v-if="canApplyForLoan && !loanData.loans.some((loan) => loan.status === 'pending' || loan.status === 'approved') && loanTab === 'all' && !loanFormOpen" class="button button-primary" @click="loanFormOpen = true">Start an application</button><button v-else-if="loanSearch || loanStatusFilter !== 'all'" class="button button-soft" @click="loanSearch = ''; loanStatusFilter = 'all'">Clear filters</button></div>
                    </section>
                    <p class="expense-boundary-note"><span>ⓘ</span> Repayment schedules use simple principal-only arithmetic and are not legal or financial advice. Reconciliation logs repayments made outside the app; there is no disbursement, payroll deduction, bank connection, or automated collection.</p>
                </template>

                <template v-else-if="activeSlug === 'travel'">
                    <section class="page-heading travel-heading">
                        <div><span class="eyebrow">WORKPLACE <span class="dot-separator">•</span> TRAVEL DESK</span><h1>Plan with clarity. Travel with confidence.</h1><p>Route requests, itinerary details, approved budgets, and post-trip reconciliation in one place.</p></div>
                        <button v-if="canRequestTravel" class="button button-primary" @click="travelFormOpen = !travelFormOpen">{{ travelFormOpen ? 'Close request' : '＋ Request business travel' }}</button>
                    </section>
                    <section class="growth-metrics travel-metrics" aria-label="Travel workspace summary">
                        <article><span class="growth-metric-icon">↗</span><div><span class="eyebrow">TRIPS IN SCOPE</span><b>{{ travelData.meta.total }}</b><small>Visible within your people scope</small></div></article>
                        <article><span class="growth-metric-icon growth-metric-review">◷</span><div><span class="eyebrow">AWAITING REVIEW</span><b>{{ travelData.meta.pending }}</b><small>Requests pending a decision</small></div></article>
                        <article><span class="growth-metric-icon growth-metric-complete">✓</span><div><span class="eyebrow">SUBMITTED REPORTS</span><b>{{ travelData.meta.report_submitted }}</b><small>Post-trip submissions in scope</small></div></article>
                        <article><span class="growth-metric-icon growth-metric-overdue">¤</span><div><span class="eyebrow">APPROVED BUDGETS</span><b class="travel-total-value">{{ travelApprovedTotals }}</b><small>By trip currency; no booking issued</small></div></article>
                    </section>
                    <section class="panel travel-workspace">
                        <section v-if="travelFormOpen" class="growth-form travel-form">
                            <div class="growth-form-heading"><div><span class="eyebrow">BUSINESS TRAVEL REQUEST</span><h2>Share the travel plan.</h2><p>Approval records an internal decision and budget ceiling. It does not book flights, hotels, visas, insurance, or ground transport.</p></div><button type="button" class="icon-button" aria-label="Close travel request form" @click="travelFormOpen = false; travelFormError = ''">×</button></div>
                            <form class="growth-form-grid" @submit.prevent="submitTravelRequest">
                                <label>From<input v-model.trim="travelForm.origin" maxlength="140" required placeholder="City or location"></label>
                                <label>Destination<input v-model.trim="travelForm.destination" maxlength="140" required placeholder="City or location"></label>
                                <label>Departure date<input v-model="travelForm.departure_date" type="date" :min="localDateInput(new Date())" required></label>
                                <label>Return date<input v-model="travelForm.return_date" type="date" :min="travelForm.departure_date || localDateInput(new Date())" required></label>
                                <label>Estimated total ({{ user.employee?.currency || user.company?.currency || 'USD' }})<input v-model="travelForm.estimated_budget" type="number" min="0.01" max="9999999999.99" step="0.01" inputmode="decimal" required placeholder="0.00"></label>
                                <label class="growth-field-wide">Business purpose<textarea v-model.trim="travelForm.purpose" rows="2" maxlength="2000" required placeholder="Business objective, meetings, or event"></textarea></label>
                                <label class="growth-field-wide">Proposed itinerary<textarea v-model.trim="travelForm.itinerary_notes" rows="3" maxlength="4000" placeholder="Optional travel legs, dates, meeting times, and booking references"></textarea></label>
                                <div v-if="travelFormError" class="inline-error growth-field-wide" role="alert">{{ travelFormError }}</div>
                                <div class="growth-form-footer growth-field-wide"><span>Do not enter passport, visa, health, or other sensitive identity information here.</span><div><button type="button" class="button button-soft" @click="travelFormOpen = false">Cancel</button><button type="submit" class="button button-primary" :disabled="busy">{{ busy ? 'Submitting…' : 'Submit for approval' }}</button></div></div>
                            </form>
                        </section>
                        <div class="travel-toolbar">
                            <nav class="growth-tabs" aria-label="Travel request views">
                                <button :class="{ active: travelTab === 'all' }" @click="travelTab = 'all'">All trips <span>{{ travelData.meta.total }}</span></button>
                                <button v-if="canReviewExpenses" :class="{ active: travelTab === 'review' }" @click="travelTab = 'review'">Review queue <span>{{ travelReviewQueue.length }}</span></button>
                                <button :class="{ active: travelTab === 'reports' }" @click="travelTab = 'reports'">Post-trip reports <span>{{ travelReports.length }}</span></button>
                            </nav>
                            <div class="travel-filters">
                                <label><span class="visually-hidden">Filter travel requests by status</span><select v-model="travelStatusFilter"><option value="all">All statuses</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option><option value="report_submitted">Report submitted</option><option value="reconciled">Reconciled</option></select></label>
                                <label class="table-search growth-search"><span>⌕</span><input v-model="travelSearch" aria-label="Search travel requests" placeholder="Search traveler, destination, purpose"></label>
                            </div>
                        </div>
                        <div v-if="travelData.meta.truncated" class="expense-truncation-note">Showing the latest {{ travelData.meta.limit }} trips in your access scope.</div>
                        <div v-if="visibleTravelTrips.length" class="travel-list">
                            <article v-for="trip in visibleTravelTrips" :key="trip.id" class="travel-card">
                                <div class="travel-card-header">
                                    <div class="travel-route">
                                        <span class="travel-route-point"><small>FROM</small><b>{{ trip.origin }}</b></span><span class="travel-route-line" aria-hidden="true">→</span><span class="travel-route-point"><small>DESTINATION</small><b>{{ trip.destination }}</b></span>
                                    </div>
                                    <span class="self-service-status-pill" :class="trip.status === 'reconciled' ? 'self-service-status-approved' : trip.status === 'rejected' ? 'self-service-status-rejected' : trip.status === 'pending' || trip.status === 'report_submitted' ? 'self-service-status-pending' : 'self-service-status-approved'">{{ travelStatusLabel(trip.status) }}</span>
                                </div>
                                <div class="travel-trip-meta"><span>{{ formatDate(trip.departure_date) }} – {{ formatDate(trip.return_date) }} <i>·</i> {{ travelDurationDays(trip.departure_date, trip.return_date) }} {{ travelDurationDays(trip.departure_date, trip.return_date) === 1 ? 'day' : 'days' }}</span><span v-if="canReviewExpenses">{{ trip.employee_name }} · {{ trip.employee_code }}</span></div>
                                <p class="travel-purpose">{{ trip.purpose }}</p>
                                <details v-if="trip.itinerary_notes" class="travel-itinerary"><summary>Proposed itinerary</summary><p>{{ trip.itinerary_notes }}</p></details>
                                <div class="travel-budget-row"><span><small>ESTIMATE</small><b>{{ formatLoanMoney(trip.estimated_budget, trip.currency) }}</b></span><span v-if="trip.approved_budget"><small>APPROVED CEILING</small><b>{{ formatLoanMoney(trip.approved_budget, trip.currency) }}</b></span><span v-if="trip.actual_total !== null"><small>REPORTED ACTUAL</small><b>{{ formatLoanMoney(trip.actual_total, trip.currency) }}</b></span><span v-if="trip.reviewer?.name"><small>REVIEWED BY</small><b>{{ trip.reviewer.name }}</b></span></div>
                                <small v-if="trip.review_note" class="expense-review-note">Review note: {{ trip.review_note }}</small>
                                <div v-if="trip.can_review" class="travel-card-actions"><button class="button button-soft" :disabled="busy" @click="openTravelReview(trip, 'reject')">Reject</button><button class="button button-primary" :disabled="busy" @click="openTravelReview(trip, 'approve')">Approve &amp; set budget</button></div>
                                <form v-if="trip.can_submit_report && travelReportId === trip.id" class="travel-inline-form" @submit.prevent="submitTravelReport(trip)">
                                    <div><span class="eyebrow">TRAVELER REPORT</span><h3>Report the completed trip.</h3><p>Report total actual spend, including any variance from the approved ceiling, with a concise explanation.</p></div>
                                    <label>Actual total ({{ trip.currency }})<input v-model="travelReportForm.actual_total" type="number" min="0" max="9999999999.99" step="0.01" inputmode="decimal" required placeholder="0.00"></label>
                                    <label class="travel-inline-wide">Post-trip notes<textarea v-model.trim="travelReportForm.post_trip_note" rows="3" maxlength="4000" required placeholder="Summarize outcomes and explain any budget variance"></textarea></label>
                                    <div v-if="travelReportError" class="inline-error travel-inline-wide" role="alert">{{ travelReportError }}</div>
                                    <div class="travel-inline-footer"><span>Report submission does not request or trigger reimbursement.</span><div><button type="button" class="button button-soft" @click="travelReportId = null; travelReportError = ''">Cancel</button><button type="submit" class="button button-primary" :disabled="busy">{{ busy ? 'Submitting…' : 'Submit trip report' }}</button></div></div>
                                </form>
                                <div v-else-if="trip.can_submit_report" class="travel-card-actions"><button class="button button-primary" @click="openTravelReport(trip)">Submit post-trip report</button></div>
                                <section v-if="trip.status === 'report_submitted' || trip.status === 'reconciled'" class="travel-report-summary"><div><span class="eyebrow">POST-TRIP REPORT</span><p>{{ trip.post_trip_note }}</p><small>Submitted {{ formatDate(trip.report_submitted_at) }}</small></div><div v-if="trip.reconciliation_note"><span class="eyebrow">HR RECONCILIATION</span><p>{{ trip.reconciliation_note }}</p><small v-if="trip.reconciler?.name">Reviewed by {{ trip.reconciler.name }} · {{ formatDate(trip.reconciled_at) }}</small></div></section>
                                <form v-if="trip.can_reconcile && travelReconcileId === trip.id" class="travel-reconcile-form" @submit.prevent="submitTravelReconcile(trip)">
                                    <label>Reconciliation note<textarea v-model.trim="travelReconcileNote" rows="2" maxlength="2000" required placeholder="Confirm review of the submitted report and budget variance"></textarea></label>
                                    <div v-if="travelReconcileError" class="inline-error" role="alert">{{ travelReconcileError }}</div>
                                    <div class="travel-inline-footer"><span>Marks the report reviewed; no payment is recorded.</span><div><button type="button" class="button button-soft" @click="travelReconcileId = null; travelReconcileError = ''">Cancel</button><button type="submit" class="button button-primary" :disabled="busy">{{ busy ? 'Saving…' : 'Confirm reconciliation' }}</button></div></div>
                                </form>
                                <div v-else-if="trip.can_reconcile" class="travel-card-actions"><button class="button button-soft" @click="openTravelReconcile(trip)">Reconcile report</button></div>
                            </article>
                        </div>
                        <div v-else class="expense-empty-state"><span class="empty-icon">↗</span><b>{{ travelTab === 'review' ? 'Your travel review queue is clear.' : travelTab === 'reports' ? 'No post-trip reports need attention.' : travelSearch || travelStatusFilter !== 'all' ? 'No trips match these filters.' : 'No travel requests yet.' }}</b><p>{{ travelTab === 'review' ? 'Pending requests from your review scope will appear here.' : travelTab === 'reports' ? 'Approved travelers can submit a report after their return date.' : 'Create a request when business travel needs approval.' }}</p><button v-if="canRequestTravel && travelTab === 'all' && !travelFormOpen" class="button button-primary" @click="travelFormOpen = true">Plan a business trip</button><button v-else-if="travelSearch || travelStatusFilter !== 'all'" class="button button-soft" @click="travelSearch = ''; travelStatusFilter = 'all'">Clear filters</button></div>
                    </section>
                    <p class="expense-boundary-note"><span>ⓘ</span> This is a manually maintained internal travel record. It does not make bookings, arrange visas, determine allowances, or initiate reimbursement or payments.</p>
                </template>

                <template v-else-if="activeSlug === 'disciplinary'">
                    <template v-if="canManageOrganization">
                        <section class="page-heading disciplinary-heading">
                            <div><span class="eyebrow">WORKPLACE <span class="dot-separator">•</span> RESTRICTED HR</span><h1>Employee relations, handled with care.</h1><p>Keep scoped case intake, investigation ownership, employee response, and documented resolution in one confidential workspace.</p></div>
                            <button class="button button-primary" @click="disciplinaryFormOpen = !disciplinaryFormOpen">{{ disciplinaryFormOpen ? 'Close intake form' : '＋ Open a case' }}</button>
                        </section>
                        <section class="growth-metrics disciplinary-metrics" aria-label="Restricted case summary">
                            <article><span class="growth-metric-icon">▤</span><div><span class="eyebrow">CASES IN SCOPE</span><b>{{ disciplinaryData.meta.total }}</b><small>Restricted to scoped HR and admins</small></div></article>
                            <article><span class="growth-metric-icon growth-metric-review">◷</span><div><span class="eyebrow">OPEN</span><b>{{ disciplinaryData.meta.open }}</b><small>Awaiting an assigned investigation</small></div></article>
                            <article><span class="growth-metric-icon growth-metric-complete">⌕</span><div><span class="eyebrow">UNDER INVESTIGATION</span><b>{{ disciplinaryData.meta.under_review }}</b><small>Response and review in progress</small></div></article>
                            <article><span class="growth-metric-icon">✓</span><div><span class="eyebrow">RESOLVED</span><b>{{ disciplinaryData.meta.resolved }}</b><small>Manual record; no automated finding</small></div></article>
                        </section>
                        <section class="panel disciplinary-workspace">
                            <section v-if="disciplinaryFormOpen" class="growth-form disciplinary-form">
                                <div class="growth-form-heading"><div><span class="eyebrow">CONFIDENTIAL CASE INTAKE</span><h2>Record a reported concern.</h2><p>Capture a neutral summary of reported facts. Creating a case does not establish a finding or authorize employment action.</p></div><button type="button" class="icon-button" aria-label="Close case intake form" @click="disciplinaryFormOpen = false; disciplinaryFormError = ''">×</button></div>
                                <form class="growth-form-grid" @submit.prevent="submitDisciplinaryCase">
                                    <label>Employee<select v-model="disciplinaryForm.employee_id" required><option value="" disabled>Select employee</option><option v-for="employee in disciplinaryData.employees" :key="employee.id" :value="String(employee.id)">{{ employee.first_name }} {{ employee.last_name }} · {{ employee.employee_code }}</option></select></label>
                                    <label>Concern category<select v-model="disciplinaryForm.case_type" required><option value="conduct">Conduct</option><option value="attendance">Attendance</option><option value="policy">Policy concern</option><option value="grievance">Grievance</option><option value="other">Other</option></select></label>
                                    <label>Incident date<input v-model="disciplinaryForm.incident_date" type="date" :max="localDateInput(new Date())" required></label>
                                    <label class="growth-field-wide">Reported concern summary<textarea v-model.trim="disciplinaryForm.summary" rows="4" maxlength="4000" required placeholder="Record the reported facts, source, and relevant context without drawing a conclusion"></textarea></label>
                                    <div v-if="disciplinaryFormError" class="inline-error growth-field-wide" role="alert">{{ disciplinaryFormError }}</div>
                                    <div class="growth-form-footer growth-field-wide"><span>Do not store identity documents, medical details, privileged communications, or evidence files here.</span><div><button type="button" class="button button-soft" @click="disciplinaryFormOpen = false">Cancel</button><button type="submit" class="button button-primary" :disabled="busy">{{ busy ? 'Saving…' : 'Create restricted case' }}</button></div></div>
                                </form>
                            </section>
                            <div class="disciplinary-toolbar">
                                <nav class="growth-tabs" aria-label="Restricted case views">
                                    <button :class="{ active: disciplinaryTab === 'all' }" @click="disciplinaryTab = 'all'">All cases <span>{{ disciplinaryData.meta.total }}</span></button>
                                    <button :class="{ active: disciplinaryTab === 'open' }" @click="disciplinaryTab = 'open'">Open <span>{{ disciplinaryData.meta.open }}</span></button>
                                    <button :class="{ active: disciplinaryTab === 'under_review' }" @click="disciplinaryTab = 'under_review'">Under investigation <span>{{ disciplinaryData.meta.under_review }}</span></button>
                                    <button :class="{ active: disciplinaryTab === 'resolved' }" @click="disciplinaryTab = 'resolved'">Resolved <span>{{ disciplinaryData.meta.resolved }}</span></button>
                                </nav>
                                <label class="table-search growth-search"><span>⌕</span><input v-model="disciplinarySearch" aria-label="Search restricted cases" placeholder="Search employee, category, or summary"></label>
                            </div>
                            <div v-if="disciplinaryData.meta.truncated" class="expense-truncation-note">Showing the latest {{ disciplinaryData.meta.limit }} cases in your access scope.</div>
                            <div v-if="filteredDisciplinaryCases.length" class="disciplinary-list">
                                <article v-for="caseItem in filteredDisciplinaryCases" :key="caseItem.id" class="disciplinary-card">
                                    <div class="disciplinary-card-header">
                                        <div><span class="eyebrow">CASE #{{ caseItem.id }} <span class="dot-separator">•</span> {{ disciplinaryTypeLabel(caseItem.case_type) }}</span><h2>{{ caseItem.employee?.first_name }} {{ caseItem.employee?.last_name }}</h2><p>{{ caseItem.employee?.employee_code }}<template v-if="caseItem.employee?.department"> · {{ caseItem.employee.department }}</template> · Incident {{ formatDate(caseItem.incident_date) }}</p></div>
                                        <span class="self-service-status-pill" :class="caseItem.status === 'resolved' ? 'self-service-status-approved' : caseItem.status === 'open' ? 'self-service-status-pending' : 'self-service-status-pending'">{{ disciplinaryStatusLabel(caseItem.status) }}</span>
                                    </div>
                                    <p class="disciplinary-summary">{{ caseItem.summary }}</p>
                                    <div class="disciplinary-owner"><span>Opened by <b>{{ caseItem.creator?.name || 'HR' }}</b></span><span v-if="caseItem.investigator">Investigator <b>{{ caseItem.investigator.name }}</b></span><span v-if="caseItem.resolver">Resolved by <b>{{ caseItem.resolver.name }}</b> · {{ formatDate(caseItem.resolved_at) }}</span></div>
                                    <section v-if="caseItem.investigation_notes || caseItem.employee_response || caseItem.resolution_summary" class="disciplinary-records">
                                        <div v-if="caseItem.investigation_notes"><span class="eyebrow">INVESTIGATION NOTES</span><p>{{ caseItem.investigation_notes }}</p></div>
                                        <div v-if="caseItem.employee_response"><span class="eyebrow">EMPLOYEE RESPONSE SUMMARY</span><p>{{ caseItem.employee_response }}</p><small v-if="caseItem.response_recorded_at">Recorded {{ formatDate(caseItem.response_recorded_at) }}</small></div>
                                        <div v-if="caseItem.resolution_summary"><span class="eyebrow">DOCUMENTED RESOLUTION</span><p>{{ caseItem.resolution_summary }}</p></div>
                                    </section>
                                    <div v-if="caseItem.status === 'open'" class="disciplinary-actions"><button class="button button-primary" :disabled="busy" @click="openDisciplinaryAction(caseItem, 'investigate')">Assign &amp; start investigation</button></div>
                                    <div v-else-if="caseItem.status === 'under_review'" class="disciplinary-actions"><button class="button button-soft" :disabled="busy" @click="openDisciplinaryAction(caseItem, 'response')">{{ caseItem.employee_response ? 'Update response summary' : 'Record employee response' }}</button><button class="button button-primary" :disabled="busy" @click="openDisciplinaryAction(caseItem, 'resolve')">Document resolution</button></div>
                                    <form v-if="disciplinaryActionId === caseItem.id" class="disciplinary-action-form" @submit.prevent="submitDisciplinaryAction(caseItem)">
                                        <template v-if="disciplinaryActionType === 'investigate'">
                                            <label>Assigned investigator<select v-model="disciplinaryActionForm.investigator_id" required><option v-for="investigator in disciplinaryData.investigators" :key="investigator.id" :value="String(investigator.id)">{{ investigator.name }} · {{ investigator.role }}</option></select></label>
                                            <label>Initial investigation notes<textarea v-model.trim="disciplinaryActionForm.investigation_notes" rows="3" maxlength="4000" placeholder="Outline the review steps or factual questions to follow up"></textarea></label>
                                        </template>
                                        <label v-else-if="disciplinaryActionType === 'response'">Employee response summary, as received<textarea v-model.trim="disciplinaryActionForm.employee_response" rows="4" maxlength="4000" required placeholder="Record the employee's response neutrally and accurately"></textarea></label>
                                        <label v-else>Resolution summary<textarea v-model.trim="disciplinaryActionForm.resolution_summary" rows="4" maxlength="4000" required placeholder="Document the manual review outcome and any agreed next steps"></textarea></label>
                                        <div v-if="disciplinaryActionError" class="inline-error" role="alert">{{ disciplinaryActionError }}</div>
                                        <div class="disciplinary-action-footer"><span>Outcomes are entered manually; the system makes no findings or recommendations.</span><div><button type="button" class="button button-soft" @click="disciplinaryActionId = null; disciplinaryActionError = ''">Cancel</button><button type="submit" class="button button-primary" :disabled="busy">{{ busy ? 'Saving…' : disciplinaryActionType === 'investigate' ? 'Start investigation' : disciplinaryActionType === 'response' ? 'Save response summary' : 'Resolve case' }}</button></div></div>
                                    </form>
                                </article>
                            </div>
                            <div v-else class="expense-empty-state"><span class="empty-icon">▤</span><b>{{ disciplinarySearch ? 'No cases match this search.' : disciplinaryData.meta.total ? 'No cases in this view.' : 'No restricted cases yet.' }}</b><p>{{ disciplinarySearch ? 'Try another employee, category, or summary term.' : 'Open a restricted case to begin documenting a reported concern.' }}</p><button v-if="!disciplinarySearch && !disciplinaryFormOpen" class="button button-primary" @click="disciplinaryFormOpen = true">Open a case</button><button v-else-if="disciplinarySearch" class="button button-soft" @click="disciplinarySearch = ''">Clear search</button></div>
                        </section>
                        <p class="expense-boundary-note"><span>ⓘ</span> This restricted tracker is not a legal process, finding, sanction, or termination tool. Use approved company procedures and applicable legal guidance; manage retention and employee access outside this foundation.</p>
                    </template>
                    <section v-else class="panel payroll-restricted"><span class="empty-icon">♙</span><b>Restricted HR workspace</b><p>Disciplinary case records are available only to HR and administrator accounts within their company or branch scope.</p></section>
                </template>

                <template v-else-if="activeSlug === 'health-safety'">
                    <template v-if="canManageOrganization">
                        <section class="page-heading safety-heading">
                            <div><span class="eyebrow">WORKPLACE <span class="dot-separator">•</span> HEALTH &amp; SAFETY</span><h1>Make safer work visible.</h1><p>Capture workplace observations, assign practical follow-up, and record closure for your authorized company or branch.</p></div>
                            <button class="button button-primary" @click="safetyFormOpen = !safetyFormOpen">{{ safetyFormOpen ? 'Close report form' : '＋ Record a safety item' }}</button>
                        </section>
                        <section class="growth-metrics safety-metrics" aria-label="Health and safety summary">
                            <article><span class="growth-metric-icon">▤</span><div><span class="eyebrow">RECORDS IN SCOPE</span><b>{{ safetyData.meta.total }}</b><small>Restricted to scoped HR and admins</small></div></article>
                            <article><span class="growth-metric-icon growth-metric-review">◷</span><div><span class="eyebrow">OPEN</span><b>{{ safetyData.meta.open }}</b><small>Awaiting an assigned action</small></div></article>
                            <article><span class="growth-metric-icon growth-metric-overdue">↗</span><div><span class="eyebrow">ACTION IN PROGRESS</span><b>{{ safetyData.meta.in_progress }}</b><small>Follow-up has an owner</small></div></article>
                            <article><span class="growth-metric-icon growth-metric-complete">✓</span><div><span class="eyebrow">RESOLVED</span><b>{{ safetyData.meta.resolved }}</b><small>Closure recorded manually</small></div></article>
                        </section>
                        <section class="panel safety-workspace">
                            <section v-if="safetyFormOpen" class="growth-form safety-form">
                                <div class="growth-form-heading"><div><span class="eyebrow">RESTRICTED SAFETY INTAKE</span><h2>Record an observation.</h2><p>Use this record to route a workplace incident, hazard, inspection finding, or PPE concern to HR follow-up.</p></div><button type="button" class="icon-button" aria-label="Close safety report form" @click="safetyFormOpen = false; safetyFormError = ''">×</button></div>
                                <form class="growth-form-grid" @submit.prevent="submitSafetyRecord">
                                    <label>Record type<select v-model="safetyForm.record_type" required><option value="incident">Incident</option><option value="hazard">Hazard</option><option value="inspection">Inspection finding</option><option value="ppe">PPE concern</option></select></label>
                                    <label>Occurred on<input v-model="safetyForm.occurred_on" type="date" :max="localDateInput(new Date())" required></label>
                                    <label>Location<input v-model.trim="safetyForm.location" maxlength="180" placeholder="Site, area, or equipment (optional)"></label>
                                    <label>Review priority (manual)<select v-model="safetyForm.priority" required><option value="low">Low</option><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option></select><small>Entered by the reporter; this app does not assess risk.</small></label>
                                    <label>Related employee (optional)<select v-model="safetyForm.employee_id"><option value="">Not employee-specific</option><option v-for="employee in safetyData.employees" :key="employee.id" :value="String(employee.id)">{{ employee.first_name }} {{ employee.last_name }} · {{ employee.employee_code }}</option></select></label>
                                    <label class="growth-field-wide">Observation summary<textarea v-model.trim="safetyForm.summary" rows="4" maxlength="4000" required placeholder="Describe the reported condition or event factually. Avoid medical or sensitive personal information."></textarea></label>
                                    <div v-if="safetyFormError" class="inline-error growth-field-wide" role="alert">{{ safetyFormError }}</div>
                                    <div class="growth-form-footer growth-field-wide"><span>For immediate danger, follow your site emergency procedure. Do not enter medical diagnoses, identity details, or evidence files.</span><div><button type="button" class="button button-soft" @click="safetyFormOpen = false">Cancel</button><button type="submit" class="button button-primary" :disabled="busy">{{ busy ? 'Saving…' : 'Save restricted record' }}</button></div></div>
                                </form>
                            </section>
                            <div class="safety-toolbar">
                                <nav class="growth-tabs" aria-label="Safety record views">
                                    <button :class="{ active: safetyTab === 'all' }" @click="safetyTab = 'all'">All records <span>{{ safetyData.meta.total }}</span></button>
                                    <button :class="{ active: safetyTab === 'open' }" @click="safetyTab = 'open'">Open <span>{{ safetyData.meta.open }}</span></button>
                                    <button :class="{ active: safetyTab === 'in_progress' }" @click="safetyTab = 'in_progress'">In progress <span>{{ safetyData.meta.in_progress }}</span></button>
                                    <button :class="{ active: safetyTab === 'resolved' }" @click="safetyTab = 'resolved'">Resolved <span>{{ safetyData.meta.resolved }}</span></button>
                                </nav>
                                <label class="table-search growth-search"><span>⌕</span><input v-model="safetySearch" aria-label="Search safety records" placeholder="Search category, employee, location"></label>
                            </div>
                            <div v-if="safetyData.meta.truncated" class="expense-truncation-note">Showing the latest {{ safetyData.meta.limit }} records in your access scope.</div>
                            <div v-if="filteredSafetyRecords.length" class="safety-list">
                                <article v-for="record in filteredSafetyRecords" :key="record.id" class="safety-card">
                                    <div class="safety-card-header">
                                        <div><span class="eyebrow">RECORD #{{ record.id }} <span class="dot-separator">•</span> {{ safetyTypeLabel(record.record_type) }}</span><h2>{{ record.location || 'Location not specified' }}</h2><p>{{ formatDate(record.occurred_on) }}<template v-if="record.employee"> · {{ record.employee.first_name }} {{ record.employee.last_name }} ({{ record.employee.employee_code }})</template></p></div>
                                        <div class="safety-card-status"><span class="self-service-status-pill" :class="record.status === 'resolved' ? 'self-service-status-approved' : 'self-service-status-pending'">{{ safetyStatusLabel(record.status) }}</span><span class="safety-priority" :class="`safety-priority-${record.priority}`">{{ safetyPriorityLabel(record.priority) }} priority · manual</span></div>
                                    </div>
                                    <p class="safety-summary">{{ record.summary }}</p>
                                    <div class="safety-owner"><span>Reported by <b>{{ record.reporter?.name || 'HR' }}</b></span><span v-if="record.assignee">Follow-up owner <b>{{ record.assignee.name }}</b></span><span v-if="record.resolver">Closed by <b>{{ record.resolver.name }}</b> · {{ formatDate(record.resolved_at) }}</span></div>
                                    <section v-if="record.action_plan || record.resolution_summary" class="safety-records">
                                        <div v-if="record.action_plan"><span class="eyebrow">FOLLOW-UP ACTION</span><p>{{ record.action_plan }}</p></div>
                                        <div v-if="record.resolution_summary"><span class="eyebrow">CLOSURE SUMMARY</span><p>{{ record.resolution_summary }}</p></div>
                                    </section>
                                    <div v-if="record.status === 'open'" class="safety-actions"><button class="button button-primary" :disabled="busy || !safetyData.assignees.length" @click="openSafetyAction(record, 'assign')">Assign follow-up</button></div>
                                    <div v-else-if="record.status === 'in_progress'" class="safety-actions"><button class="button button-primary" :disabled="busy" @click="openSafetyAction(record, 'resolve')">Record resolution</button></div>
                                    <form v-if="safetyActionId === record.id" class="safety-action-form" @submit.prevent="submitSafetyAction(record)">
                                        <label v-if="safetyActionType === 'assign'">HR follow-up owner<select v-model="safetyActionForm.assigned_to" required><option v-for="assignee in safetyData.assignees" :key="assignee.id" :value="String(assignee.id)">{{ assignee.name }} · {{ assignee.role }}</option></select></label>
                                        <label v-if="safetyActionType === 'assign'">Follow-up action<textarea v-model.trim="safetyActionForm.action_plan" rows="3" maxlength="4000" required placeholder="Record the practical action and verification step"></textarea></label>
                                        <label v-else>Resolution and verification summary<textarea v-model.trim="safetyActionForm.resolution_summary" rows="3" maxlength="4000" required placeholder="Document what was done and how the issue was checked"></textarea></label>
                                        <div v-if="safetyActionError" class="inline-error" role="alert">{{ safetyActionError }}</div>
                                        <div class="safety-action-footer"><span>Follow-up and closure are entered manually; no regulatory conclusion is inferred.</span><div><button type="button" class="button button-soft" @click="safetyActionId = null; safetyActionError = ''">Cancel</button><button type="submit" class="button button-primary" :disabled="busy">{{ busy ? 'Saving…' : safetyActionType === 'assign' ? 'Assign action' : 'Resolve record' }}</button></div></div>
                                    </form>
                                </article>
                            </div>
                            <div v-else class="expense-empty-state"><span class="empty-icon">✓</span><b>{{ safetySearch ? 'No safety records match your search.' : safetyData.meta.total ? 'No records in this view.' : 'No safety records yet.' }}</b><p>{{ safetySearch ? 'Try another category, employee, or location.' : 'Record a workplace observation to begin scoped follow-up.' }}</p><button v-if="!safetySearch && !safetyFormOpen" class="button button-primary" @click="safetyFormOpen = true">Record a safety item</button><button v-else-if="safetySearch" class="button button-soft" @click="safetySearch = ''">Clear search</button></div>
                        </section>
                        <p class="expense-boundary-note"><span>ⓘ</span> This is a restricted manual record tracker, not an emergency service, medical record system, risk assessment, or legal compliance certification. Use approved safety procedures and local guidance.</p>
                    </template>
                    <section v-else class="panel payroll-restricted"><span class="empty-icon">♙</span><b>Restricted HR workspace</b><p>Health and safety records are available only to HR and administrator accounts within their company or branch scope.</p></section>
                </template>

                <template v-else>
                    <section class="page-heading module-heading"><div><span class="eyebrow">{{ moduleBySlug?.area || 'WORKSPACE' }} <span class="dot-separator">•</span> MODULE OVERVIEW</span><h1>{{ moduleBySlug?.name || 'Workspace module' }}</h1><p>{{ descriptions[activeSlug] || 'A workflow module in the Northstar People roadmap.' }}</p></div><span class="status-pill" :class="moduleBySlug?.delivery === 'Planned' ? 'status-planned' : 'status-foundation'">{{ moduleBySlug?.delivery || 'Planned' }}</span></section>
                    <section class="module-feature-grid"><article class="panel module-status-card"><span class="module-status-icon">✳</span><span class="eyebrow">DELIVERY TRACKER</span><h2>{{ moduleBySlug?.delivery === 'Planned' ? 'Planned — not yet implemented' : 'Foundation slice delivered' }}</h2><p>{{ stagedScope[activeSlug] || 'This module is in the approved enterprise scope. It will be delivered in a later increment with persisted data, authorization, and workflow tests.' }}</p><div class="module-divider"></div><div class="module-meta"><span>DATA STATUS</span><b>{{ moduleBySlug?.delivery === 'Planned' ? 'No workflow data is simulated' : 'Company-scoped foundation' }}</b></div><div class="module-meta"><span>INTEGRATIONS</span><b>Not connected</b></div></article><article class="panel roadmap-list"><span class="eyebrow">NEXT DELIVERY INCREMENTS</span><h2>From foundation to full workflow</h2><div class="roadmap-item"><span class="roadmap-index">01</span><span><b>People & organization</b><small>Profiles, document expiry, recruiting, onboarding, and org structure</small></span><span class="status-pill status-planned">Next</span></div><div class="roadmap-item"><span class="roadmap-index">02</span><span><b>Time & absence</b><small>Roster rules, holidays, corrections, overtime, and leave policies</small></span><span class="status-pill status-planned">Planned</span></div><div class="roadmap-item"><span class="roadmap-index">03</span><span><b>Compensation & finance</b><small>Payroll controls, settlement, benefits, loans, expenses, and WPS exports</small></span><span class="status-pill status-planned">Planned</span></div><div class="roadmap-item"><span class="roadmap-index">04</span><span><b>Growth, compliance & integrations</b><small>Performance, training, employee relations, advanced planning, and ERP contracts</small></span><span class="status-pill status-planned">Planned</span></div><div class="integration-note"><span>ⓘ</span><p>External providers, banking, biometrics, and ERP connections remain disabled until authorized credentials, privacy review, and a test environment are available.</p></div></article></section>
                </template>
                <footer class="page-footer"><span>Northstar People <i>•</i> Local development foundation</span><span>Synthetic records only · {{ user.company?.name }}</span></footer>
            </div>
        </main>
        <dialog v-if="managerReviewOpen && selectedManagerRequest" ref="managerReviewDialog" class="recruitment-hire-dialog manager-leave-review-dialog" role="alertdialog" aria-modal="true" aria-labelledby="manager-leave-review-title" @close="closeManagerLeaveReview">
            <form class="recruitment-hire-form" @submit.prevent="submitManagerLeaveReview">
                <span class="recruitment-hire-symbol">{{ managerReviewDecision === 'approved' ? '✓' : '−' }}</span>
                <span class="eyebrow">DIRECT-REPORT LEAVE REVIEW</span>
                <h2 id="manager-leave-review-title">{{ managerReviewDecision === 'approved' ? 'Approve this leave request?' : 'Decline this leave request?' }}</h2>
                <p>{{ fullName(selectedManagerRequest.employee) }} · {{ selectedManagerRequest.leave_type?.name }} · {{ Number(selectedManagerRequest.requested_days).toLocaleString() }} working days, {{ formatDate(selectedManagerRequest.start_date) }} to {{ formatDate(selectedManagerRequest.end_date) }}.</p>
                <p v-if="managerReviewDecision === 'approved'">Approval records your decision and deducts the requested days from the configured balance. It does not notify a provider or arrange cover.</p>
                <label>{{ managerReviewDecision === 'approved' ? 'Review note (optional)' : 'Reason for declining' }}<textarea v-model.trim="managerReviewNote" rows="3" maxlength="2000" :required="managerReviewDecision === 'rejected'" placeholder="Document your decision for the employee"></textarea></label>
                <label v-if="managerReviewDecision === 'approved'" class="manager-review-check"><input v-model="managerReviewAcknowledged" type="checkbox" required><span>I reviewed the dates and balance, and confirm this is a leave decision for my direct report.</span></label>
                <div v-if="managerReviewError" class="inline-error" role="alert">{{ managerReviewError }}</div>
                <div class="recruitment-hire-actions"><button type="button" class="button button-soft" @click="managerReviewDialog?.close()">Cancel</button><button type="submit" class="button button-primary" :disabled="busy || (managerReviewDecision === 'approved' && !managerReviewAcknowledged) || (managerReviewDecision === 'rejected' && !managerReviewNote.trim())">{{ busy ? 'Saving…' : managerReviewDecision === 'approved' ? 'Confirm approval' : 'Confirm decline' }}</button></div>
            </form>
        </dialog>
        <dialog v-if="expenseReviewOpen && selectedExpenseClaim" ref="expenseReviewDialog" class="recruitment-hire-dialog expense-review-dialog" role="alertdialog" aria-modal="true" aria-labelledby="expense-review-title" @close="closeExpenseReview">
            <form class="recruitment-hire-form" @submit.prevent="submitExpenseReview">
                <span class="recruitment-hire-symbol">{{ expenseReviewDecision === 'approve' ? '✓' : '−' }}</span>
                <span class="eyebrow">EXPENSE CLAIM DECISION</span>
                <h2 id="expense-review-title">{{ expenseReviewDecision === 'approve' ? 'Approve this expense claim?' : 'Reject this expense claim?' }}</h2>
                <p>{{ selectedExpenseClaim.employee_name }} · {{ selectedExpenseClaim.merchant }} · {{ selectedExpenseClaim.currency }} {{ Number(selectedExpenseClaim.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}.</p>
                <p v-if="expenseReviewDecision === 'approve'">This records an internal approval only. It does not confirm policy compliance, reimbursement, or payment.</p>
                <label>{{ expenseReviewDecision === 'approve' ? 'Reviewer note (optional)' : 'Reason for rejection' }}<textarea v-model.trim="expenseReviewNote" rows="3" maxlength="2000" :required="expenseReviewDecision === 'reject'" placeholder="Document the decision for the employee"></textarea></label>
                <div v-if="expenseReviewError" class="inline-error" role="alert">{{ expenseReviewError }}</div>
                <div class="recruitment-hire-actions"><button type="button" class="button button-soft" @click="expenseReviewDialog?.close()">Cancel</button><button type="submit" class="button button-primary" :disabled="busy || (expenseReviewDecision === 'reject' && !expenseReviewNote.trim())">{{ busy ? 'Saving…' : expenseReviewDecision === 'approve' ? 'Confirm approval' : 'Confirm rejection' }}</button></div>
            </form>
        </dialog>
        <dialog v-if="loanReviewOpen && selectedLoanAdvance" ref="loanReviewDialog" class="recruitment-hire-dialog loan-review-dialog" role="alertdialog" aria-modal="true" aria-labelledby="loan-review-title" @close="closeLoanReview">
            <form class="recruitment-hire-form" @submit.prevent="submitLoanReview">
                <span class="recruitment-hire-symbol">{{ loanReviewDecision === 'approve' ? '✓' : '−' }}</span>
                <span class="eyebrow">LOAN REQUEST DECISION</span>
                <h2 id="loan-review-title">{{ loanReviewDecision === 'approve' ? 'Approve and set a schedule?' : 'Reject this loan request?' }}</h2>
                <p>{{ selectedLoanAdvance.employee_name }} requests {{ formatLoanMoney(selectedLoanAdvance.requested_amount, selectedLoanAdvance.currency) }} over {{ selectedLoanAdvance.requested_installments }} preferred installments.</p>
                <template v-if="loanReviewDecision === 'approve'">
                    <label>Approved principal<input v-model="loanReviewForm.approved_amount" type="number" min="0.01" :max="selectedLoanAdvance.requested_amount" step="0.01" inputmode="decimal" required></label>
                    <label>Number of installments<input v-model="loanReviewForm.repayment_installments" type="number" min="1" max="60" step="1" required></label>
                    <label>First installment due<input v-model="loanReviewForm.first_due_date" type="date" :min="localDateInput(new Date())" required></label>
                    <p class="loan-review-disclosure">Schedule is equal-principal arithmetic only. It does not create legal terms, interest, a disbursement, or payroll deductions.</p>
                </template>
                <label>{{ loanReviewDecision === 'approve' ? 'Reviewer note (optional)' : 'Reason for rejection' }}<textarea v-model.trim="loanReviewForm.review_note" rows="3" maxlength="2000" :required="loanReviewDecision === 'reject'" placeholder="Document the decision for the employee"></textarea></label>
                <div v-if="loanReviewError" class="inline-error" role="alert">{{ loanReviewError }}</div>
                <div class="recruitment-hire-actions"><button type="button" class="button button-soft" @click="loanReviewDialog?.close()">Cancel</button><button type="submit" class="button button-primary" :disabled="busy || (loanReviewDecision === 'reject' && !loanReviewForm.review_note.trim())">{{ busy ? 'Saving…' : loanReviewDecision === 'approve' ? 'Confirm approval & schedule' : 'Confirm rejection' }}</button></div>
            </form>
        </dialog>
        <dialog v-if="selectedCandidate" ref="hireDialog" class="recruitment-hire-dialog" aria-labelledby="hire-dialog-title" @close="closeHireDialog">
            <form class="recruitment-hire-form" @submit.prevent="hireCandidate">
                <span class="recruitment-hire-symbol">✓</span>
                <span class="eyebrow">OFFER ACCEPTED</span>
                <h2 id="hire-dialog-title">Create an employee record?</h2>
                <p>Hiring {{ fullName(selectedCandidate) }} will fill one opening and add this person to your employee directory. No user login or salary is created.</p>
                <label>Employee code<input v-model.trim="hireForm.employee_code" required maxlength="32" autocomplete="off"></label>
                <label>Joining date<input v-model="hireForm.join_date" type="date" required></label>
                <div v-if="hireActionError" class="inline-error" role="alert">{{ hireActionError }}</div>
                <div class="recruitment-hire-actions"><button type="button" class="button button-soft" @click="hireDialog?.close()">Cancel</button><button type="submit" class="button button-primary" :disabled="busy">Confirm hire &amp; create employee</button></div>
            </form>
        </dialog>
        <dialog v-if="payrollReviewOpen && selectedPayrollCycle" ref="payrollReviewDialog" class="recruitment-hire-dialog payroll-review-dialog" aria-labelledby="payroll-review-title" @close="closePayrollReview">
            <form class="recruitment-hire-form" @submit.prevent="reviewPayrollCycle(payrollReviewDecision)">
                <span class="recruitment-hire-symbol">{{ payrollReviewDecision === 'approve' ? '✓' : '↶' }}</span>
                <span class="eyebrow">{{ payrollReviewDecision === 'approve' ? 'PAYROLL REVIEW' : 'RETURN FOR CHANGES' }}</span>
                <h2 id="payroll-review-title">{{ payrollReviewDecision === 'approve' ? 'Approve this workpaper?' : 'Request changes to this cycle?' }}</h2>
                <p v-if="payrollReviewDecision === 'approve'">This records an approval for {{ selectedPayrollCycle.currency }} {{ formatDate(selectedPayrollCycle.period_start) }} – {{ formatDate(selectedPayrollCycle.period_end) }}. It does not initiate payment or confirm statutory compliance.</p>
                <p v-else>Returned cycles become editable again. Provide a clear note so the payroll preparer knows what needs review.</p>
                <label>{{ payrollReviewDecision === 'approve' ? 'Reviewer note (optional)' : 'Reason for requested changes' }}<textarea v-model.trim="payrollReviewNote" rows="3" maxlength="2000" :required="payrollReviewDecision === 'return'" placeholder="Document the review decision"></textarea></label>
                <label v-if="payrollReviewDecision === 'approve'" class="lifecycle-confirm-check"><input v-model="payrollReviewAcknowledged" type="checkbox" required><span>I reviewed the saved manual inputs and confirm this is an approval only, not a payment instruction.</span></label>
                <div v-if="payrollReviewError" class="inline-error" role="alert">{{ payrollReviewError }}</div>
                <div class="recruitment-hire-actions">
                    <button type="button" class="button button-soft" @click="payrollReviewDialog?.close()">Cancel</button>
                    <button type="submit" class="button button-primary" :disabled="busy || (payrollReviewDecision === 'approve' && !payrollReviewAcknowledged) || (payrollReviewDecision === 'return' && !payrollReviewNote.trim())">{{ busy ? 'Saving…' : payrollReviewDecision === 'approve' ? 'Confirm approval' : 'Return cycle' }}</button>
                </div>
            </form>
        </dialog>
        <dialog v-if="benefitReviewOpen && selectedBenefitEnrollment" ref="benefitReviewDialog" class="recruitment-hire-dialog payroll-review-dialog" aria-labelledby="benefit-review-title" @close="closeBenefitReview">
            <form class="recruitment-hire-form" @submit.prevent="reviewBenefitEnrollment">
                <span class="recruitment-hire-symbol">{{ benefitReviewDecision === 'approve' ? '✓' : benefitReviewDecision === 'return' ? '↶' : '−' }}</span>
                <span class="eyebrow">BENEFIT ENROLLMENT REVIEW</span>
                <h2 id="benefit-review-title">{{ benefitReviewDecision === 'approve' ? 'Approve this enrollment?' : benefitReviewDecision === 'return' ? 'Request enrollment changes?' : 'Decline this enrollment?' }}</h2>
                <p>{{ selectedBenefitEnrollment.employee_name }} · {{ selectedBenefitEnrollment.plan?.name }}. This records an HR decision only; no contribution, payroll deduction, provider update, or payment is initiated.</p>
                <label>{{ benefitReviewDecision === 'approve' ? 'Reviewer note (optional)' : benefitReviewDecision === 'return' ? 'Reason for requested changes' : 'Reason for declining' }}<textarea v-model.trim="benefitReviewNote" rows="3" maxlength="2000" :required="benefitReviewDecision !== 'approve'" placeholder="Document the review decision"></textarea></label>
                <label v-if="benefitReviewDecision === 'approve'" class="lifecycle-confirm-check"><input v-model="benefitReviewAcknowledged" type="checkbox" required><span>I verified the manually entered amounts and confirm this is an approval record only.</span></label>
                <div v-if="benefitReviewError" class="inline-error" role="alert">{{ benefitReviewError }}</div>
                <div class="recruitment-hire-actions"><button type="button" class="button button-soft" @click="benefitReviewDialog?.close()">Cancel</button><button type="submit" class="button button-primary" :disabled="busy || (benefitReviewDecision === 'approve' && !benefitReviewAcknowledged) || (benefitReviewDecision !== 'approve' && !benefitReviewNote.trim())">{{ busy ? 'Saving…' : benefitReviewDecision === 'approve' ? 'Confirm approval' : benefitReviewDecision === 'return' ? 'Request changes' : 'Decline enrollment' }}</button></div>
            </form>
        </dialog>
        <dialog v-if="benefitEndOpen && selectedBenefitEnrollment" ref="benefitEndDialog" class="recruitment-hire-dialog payroll-review-dialog" aria-labelledby="benefit-end-title" @close="benefitEndOpen = false; benefitEndError = ''">
            <form class="recruitment-hire-form" @submit.prevent="endBenefitEnrollment">
                <span class="recruitment-hire-symbol">↗</span>
                <span class="eyebrow">END BENEFIT ENROLLMENT</span>
                <h2 id="benefit-end-title">Record an end date?</h2>
                <p>This ends the internal enrollment record for {{ selectedBenefitEnrollment.employee_name }}. It does not notify the employee, update a provider, or stop payroll deductions.</p>
                <label>Effective end date<input v-model="benefitEndDate" type="date" :min="selectedBenefitEnrollment.effective_start" required></label>
                <div v-if="benefitEndError" class="inline-error" role="alert">{{ benefitEndError }}</div>
                <div class="recruitment-hire-actions"><button type="button" class="button button-soft" @click="benefitEndDialog?.close()">Cancel</button><button type="submit" class="button button-primary" :disabled="busy">{{ busy ? 'Saving…' : 'Confirm end date' }}</button></div>
            </form>
        </dialog>
        <dialog v-if="settlementReviewOpen && selectedSettlement" ref="settlementReviewDialog" class="recruitment-hire-dialog payroll-review-dialog" aria-labelledby="settlement-review-title" @close="settlementReviewOpen = false; settlementReviewAcknowledged = false">
            <form class="recruitment-hire-form" @submit.prevent="reviewSettlement">
                <span class="recruitment-hire-symbol">{{ settlementReviewDecision === 'approve' ? '✓' : '↶' }}</span>
                <span class="eyebrow">SETTLEMENT WORKPAPER REVIEW</span>
                <h2 id="settlement-review-title">{{ settlementReviewDecision === 'approve' ? 'Approve this workpaper?' : 'Request workpaper changes?' }}</h2>
                <p>{{ selectedSettlement.employee_name }} · {{ selectedSettlement.currency }}. The total is arithmetic only. Approval records a reviewer decision and does not create a payment or payroll instruction.</p>
                <label>{{ settlementReviewDecision === 'approve' ? 'Reviewer note (optional)' : 'Reason for requested changes' }}<textarea v-model.trim="settlementReviewNote" rows="3" maxlength="2000" :required="settlementReviewDecision === 'return'" placeholder="Document the review decision"></textarea></label>
                <label v-if="settlementReviewDecision === 'approve'" class="lifecycle-confirm-check"><input v-model="settlementReviewAcknowledged" type="checkbox" required><span>I reviewed all saved workpaper lines and confirm this is approval only, not payment.</span></label>
                <div v-if="settlementReviewError" class="inline-error" role="alert">{{ settlementReviewError }}</div>
                <div class="recruitment-hire-actions"><button type="button" class="button button-soft" @click="settlementReviewDialog?.close()">Cancel</button><button type="submit" class="button button-primary" :disabled="busy || (settlementReviewDecision === 'approve' && !settlementReviewAcknowledged) || (settlementReviewDecision === 'return' && !settlementReviewNote.trim())">{{ busy ? 'Saving…' : settlementReviewDecision === 'approve' ? 'Confirm approval' : 'Request changes' }}</button></div>
            </form>
        </dialog>
        <dialog v-if="lifecycleCloseOpen && selectedLifecycleCase" ref="lifecycleCloseDialog" class="recruitment-hire-dialog" aria-labelledby="lifecycle-close-title" @close="lifecycleCloseOpen = false; lifecycleCloseAcknowledged = false">
            <form class="recruitment-hire-form lifecycle-close-form" @submit.prevent="completeLifecycleCase">
                <span class="recruitment-hire-symbol">{{ selectedLifecycleCase.type === 'offboarding' ? '↗' : '✓' }}</span>
                <span class="eyebrow">{{ selectedLifecycleCase.type === 'offboarding' ? 'FINAL EXIT REVIEW' : 'ONBOARDING COMPLETE' }}</span>
                <h2 id="lifecycle-close-title">{{ selectedLifecycleCase.type === 'offboarding' ? 'Finalize this employee exit?' : 'Close this onboarding journey?' }}</h2>
                <p v-if="selectedLifecycleCase.type === 'offboarding'">This closes the workflow and changes {{ fullName(selectedLifecycleCase.employee) }}’s employee status to inactive. Payroll settlement and account revocation are not performed by this action.</p>
                <p v-else>All checklist items are complete. This will close the onboarding journey for {{ fullName(selectedLifecycleCase.employee) }}.</p>
                <label v-if="selectedLifecycleCase.type === 'offboarding'" class="lifecycle-confirm-check"><input v-model="lifecycleCloseAcknowledged" type="checkbox" required><span>I reviewed the completed clearances and confirm the employee should be marked inactive.</span></label>
                <div class="recruitment-hire-actions"><button type="button" class="button button-soft" @click="lifecycleCloseDialog?.close()">Go back</button><button type="submit" class="button button-primary" :disabled="busy || (selectedLifecycleCase.type === 'offboarding' && !lifecycleCloseAcknowledged)">{{ selectedLifecycleCase.type === 'offboarding' ? 'Confirm exit' : 'Complete journey' }}</button></div>
            </form>
        </dialog>
        <dialog v-if="trainingTransitionOpen && pendingTrainingTransition" ref="trainingTransitionDialog" class="recruitment-hire-dialog" role="alertdialog" aria-modal="true" aria-labelledby="training-transition-title" @close="trainingTransitionOpen = false; pendingTrainingTransition = null">
            <form class="recruitment-hire-form" @submit.prevent="confirmTrainingEnrollmentTransition">
                <span class="recruitment-hire-symbol">{{ trainingEnrollmentDrafts[pendingTrainingTransition.id]?.status === 'completed' ? '✓' : '−' }}</span>
                <span class="eyebrow">FINAL TRAINING STATUS</span>
                <h2 id="training-transition-title">{{ trainingEnrollmentDrafts[pendingTrainingTransition.id]?.status === 'completed' ? 'Record completion?' : 'Cancel this assignment?' }}</h2>
                <p>{{ pendingTrainingTransition.employee_name }} · {{ pendingTrainingTransition.program?.title }}. This status is final and cannot be changed later. Confirm only a manually verified completion or cancellation.</p>
                <div class="recruitment-hire-actions">
                    <button type="button" class="button button-soft" @click="trainingTransitionDialog?.close()">Go back</button>
                    <button type="submit" class="button button-primary" :class="{ 'growth-transition-confirm': trainingEnrollmentDrafts[pendingTrainingTransition.id]?.status === 'cancelled' }" :disabled="busy">{{ trainingEnrollmentDrafts[pendingTrainingTransition.id]?.status === 'completed' ? 'Confirm completion' : 'Confirm cancellation' }}</button>
                </div>
            </form>
        </dialog>
    </div>
</template>
