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
    description: '', eligibility_notes: '',
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
    benefits: 'Benefits enrollment, allowances, insurance, and compensation policies.',
    performance: 'Goals, reviews, feedback, and documented performance plans.',
    training: 'Training needs, programs, attendance, credentials, and career development.',
    'employee-self-service': 'Employee-owned profile, requests, documents, letters, and payroll views.',
    'manager-self-service': 'Team directory, approvals, schedules, and manager requests.',
    expenses: 'Expense claims, receipt handling, approval, and reimbursement tracking.',
    'loans-advances': 'Applications, approvals, repayment schedules, balances, and settlement.',
    travel: 'Travel requests, itinerary, allowance rules, expenses, and settlements.',
    disciplinary: 'Restricted incident, investigation, hearing, and corrective-action records.',
    'health-safety': 'Safety incidents, risk assessments, inspections, PPE, and follow-up actions.',
    'assets-uniforms': 'Assignment, handover, return, condition, uniforms, and PPE records.',
    documents: 'Private employee records with controlled uploads, access, and expiry tracking.',
    'separation-settlement': 'Exit workflow, clearances, recoveries, settlement review, and closure.',
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
    'employee-self-service': 'Self-service navigation is available; profile changes, private documents, and letters are staged.',
    'manager-self-service': 'Team-scoped leave review is available. Additional team approvals, requisitions, and scheduling are staged.',
    expenses: 'Claim submission, receipt storage, approvals, and reimbursement are staged.',
    'loans-advances': 'Loan requests, repayment schedules, payroll deductions, and settlement are staged.',
    travel: 'Travel requests, policy rules, itineraries, and settlement are staged.',
    disciplinary: 'Restricted case-management workflows are staged.',
    'health-safety': 'Incident, risk, medical fitness, and action-management workflows are staged.',
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
    return attendanceToday.value;
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
        provider: '', description: '', eligibility_notes: '',
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
    busy.value = true;
    try {
        await api(`/benefits/enrollments/${enrollment.id}/submit`, { method: 'POST', body: '{}' });
        message('Enrollment submitted for HR review. Inputs are locked.');
        await loadActive();
    } catch (requestError) {
        message(requestError.message, true);
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
    busy.value = true;
    try {
        await api(`/separations/settlements/${selectedSettlement.value.id}/submit`, {
            method: 'POST',
            body: '{}',
        });
        message('Settlement workpaper submitted for review. No payment was made.');
        await loadActive();
    } catch (requestError) {
        message(requestError.message, true);
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

async function attendanceAction() {
    busy.value = true;
    try {
        if (employeeAttendance.value?.check_in && !employeeAttendance.value?.check_out) {
            await api(`/attendance/${employeeAttendance.value.id}/check-out`, { method: 'POST', body: '{}' });
            message('Check-out recorded.');
        } else {
            await api('/attendance/check-in', { method: 'POST', body: '{}' });
            message('Check-in recorded.');
        }
        await loadAttendanceReport();
    } catch (requestError) {
        message(requestError.message, true);
    } finally {
        busy.value = false;
    }
}

watch(selectedCandidate, async (candidate) => {
    if (!candidate) return;
    await nextTick();
    if (hireDialog.value && !hireDialog.value.open) hireDialog.value.showModal();
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
                                    <label v-if="!user.branch_id">Branch scope<select v-model="benefitPlanForm.branch_id"><option value="">Company-wide</option><option v-for="branch in benefitsData.branches" :key="branch.id" :value="String(branch.id)">{{ branch.name }}</option></select></label>
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
                                        <div class="benefit-plan-actions"><button class="button button-soft" @click="openBenefitPlanForm(plan)">⚙ Configure</button><button v-if="plan.status === 'active'" class="button button-primary" @click="openBenefitEnrollmentForm(); benefitEnrollmentForm.benefit_plan_id = String(plan.id)">＋ Enroll</button></div>
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
                                        <button v-if="['draft', 'changes_requested'].includes(selectedSettlement.status)" class="button button-primary" :disabled="busy || !selectedSettlement.items.length || !settlementClearancesComplete(selectedSettlement) || settlementItemDrafts[selectedSettlement.id]?.length !== selectedSettlement.items.length" @click="submitSettlement">Submit for review →</button>
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

                <template v-else>
                    <section class="page-heading module-heading"><div><span class="eyebrow">{{ moduleBySlug?.area || 'WORKSPACE' }} <span class="dot-separator">•</span> MODULE OVERVIEW</span><h1>{{ moduleBySlug?.name || 'Workspace module' }}</h1><p>{{ descriptions[activeSlug] || 'A workflow module in the Northstar People roadmap.' }}</p></div><span class="status-pill" :class="moduleBySlug?.delivery === 'Planned' ? 'status-planned' : 'status-foundation'">{{ moduleBySlug?.delivery || 'Planned' }}</span></section>
                    <section class="module-feature-grid"><article class="panel module-status-card"><span class="module-status-icon">✳</span><span class="eyebrow">DELIVERY TRACKER</span><h2>{{ moduleBySlug?.delivery === 'Planned' ? 'Planned — not yet implemented' : 'Foundation slice delivered' }}</h2><p>{{ stagedScope[activeSlug] || 'This module is in the approved enterprise scope. It will be delivered in a later increment with persisted data, authorization, and workflow tests.' }}</p><div class="module-divider"></div><div class="module-meta"><span>DATA STATUS</span><b>{{ moduleBySlug?.delivery === 'Planned' ? 'No workflow data is simulated' : 'Company-scoped foundation' }}</b></div><div class="module-meta"><span>INTEGRATIONS</span><b>Not connected</b></div></article><article class="panel roadmap-list"><span class="eyebrow">NEXT DELIVERY INCREMENTS</span><h2>From foundation to full workflow</h2><div class="roadmap-item"><span class="roadmap-index">01</span><span><b>People & organization</b><small>Profiles, document expiry, recruiting, onboarding, and org structure</small></span><span class="status-pill status-planned">Next</span></div><div class="roadmap-item"><span class="roadmap-index">02</span><span><b>Time & absence</b><small>Roster rules, holidays, corrections, overtime, and leave policies</small></span><span class="status-pill status-planned">Planned</span></div><div class="roadmap-item"><span class="roadmap-index">03</span><span><b>Compensation & finance</b><small>Payroll controls, settlement, benefits, loans, expenses, and WPS exports</small></span><span class="status-pill status-planned">Planned</span></div><div class="roadmap-item"><span class="roadmap-index">04</span><span><b>Growth, compliance & integrations</b><small>Performance, training, employee relations, advanced planning, and ERP contracts</small></span><span class="status-pill status-planned">Planned</span></div><div class="integration-note"><span>ⓘ</span><p>External providers, banking, biometrics, and ERP connections remain disabled until authorized credentials, privacy review, and a test environment are available.</p></div></article></section>
                </template>
                <footer class="page-footer"><span>Northstar People <i>•</i> Local development foundation</span><span>Synthetic records only · {{ user.company?.name }}</span></footer>
            </div>
        </main>
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
    </div>
</template>
