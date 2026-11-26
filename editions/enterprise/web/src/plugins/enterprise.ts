import type { ComponentType } from 'react';
import { definePlugin } from '@lasabro/kit';
import { page } from '../lib/lazyPage';

/**
 * The screens of this edition. Each route is code-split (fetched on first visit). As modules become LASABRO plugins their
 * routes move out of this list into the plugin's own web package; enterprise.test.ts keeps the union identical.
 */
const pg = page;
const DashboardPage = pg(() => import('../pages/DashboardPage'), 'DashboardPage');
const AssetsPage = pg(() => import('../pages/AssetsPage'), 'AssetsPage');
const MovementsPage = pg(() => import('../pages/MovementsPage'), 'MovementsPage');
const AccessRegisterPage = pg(() => import('../pages/AccessRegisterPage'), 'AccessRegisterPage');
const IncidentsPage = pg(() => import('../pages/IncidentsPage'), 'IncidentsPage');
const RecordsPage = pg(() => import('../pages/RecordsPage'), 'RecordsPage');
const ArchiveAccessPage = pg(() => import('../pages/ArchiveAccessPage'), 'ArchiveAccessPage');
const InventoryPage = pg(() => import('../pages/InventoryPage'), 'InventoryPage');
const DisposalPage = pg(() => import('../pages/DisposalPage'), 'DisposalPage');
const RetentionPage = pg(() => import('../pages/RetentionPage'), 'RetentionPage');
const ComplianceCalendarPage = pg(() => import('../pages/ComplianceCalendarPage'), 'ComplianceCalendarPage');
const BackupsPage = pg(() => import('../pages/BackupsPage'), 'BackupsPage');
const TrainingPage = pg(() => import('../pages/TrainingPage'), 'TrainingPage');
const AuditsPage = pg(() => import('../pages/AuditsPage'), 'AuditsPage');
const ManagementReportsPage = pg(() => import('../pages/ManagementReportsPage'), 'ManagementReportsPage');
const LegalReferencesPage = pg(() => import('../pages/LegalReferencesPage'), 'LegalReferencesPage');
const ReportsPage = pg(() => import('../pages/ReportsPage'), 'ReportsPage');
const ReportViewPage = pg(() => import('../pages/ReportViewPage'), 'ReportViewPage');
const FilesPage = pg(() => import('../pages/FilesPage'), 'FilesPage');
const CasesPage = pg(() => import('../pages/CasesPage'), 'CasesPage');
const CaseDetailPage = pg(() => import('../pages/CaseDetailPage'), 'CaseDetailPage');
const SupportChatsPage = pg(() => import('../pages/SupportChatsPage'), 'SupportChatsPage');
const UsersPage = pg(() => import('../pages/UsersPage'), 'UsersPage');
const RolesPage = pg(() => import('../pages/RolesPage'), 'RolesPage');
const OrganizationPage = pg(() => import('../pages/OrganizationPage'), 'OrganizationPage');
const DepartmentsPage = pg(() => import('../pages/DepartmentsPage'), 'DepartmentsPage');
const LocationsPage = pg(() => import('../pages/LocationsPage'), 'LocationsPage');
const LookupsPage = pg(() => import('../pages/LookupsPage'), 'LookupsPage');
const AuditLogPage = pg(() => import('../pages/AuditLogPage'), 'AuditLogPage');
const ApprovalsPage = pg(() => import('../pages/ApprovalsPage'), 'ApprovalsPage');
const EmployeesPage = pg(() => import('../pages/EmployeesPage'), 'EmployeesPage');
const PositionsPage = pg(() => import('../pages/PositionsPage'), 'PositionsPage');
const EmploymentChangesPage = pg(() => import('../pages/EmploymentChangesPage'), 'EmploymentChangesPage');
const ReviewsPage = pg(() => import('../pages/ReviewsPage'), 'ReviewsPage');
const MyEmploymentPage = pg(() => import('../pages/MyEmploymentPage'), 'MyEmploymentPage');
const RequisitionsPage = pg(() => import('../pages/RequisitionsPage'), 'RequisitionsPage');
const RecruitmentPage = pg(() => import('../pages/RecruitmentPage'), 'RecruitmentPage');
const InterviewsPage = pg(() => import('../pages/InterviewsPage'), 'InterviewsPage');
const ClockPage = pg(() => import('../pages/ClockPage'), 'ClockPage');
const TeamAttendancePage = pg(() => import('../pages/TeamAttendancePage'), 'TeamAttendancePage');
const AttendanceSetupPage = pg(() => import('../pages/AttendanceSetupPage'), 'AttendanceSetupPage');
const AttendanceImportPage = pg(() => import('../pages/AttendanceImportPage'), 'AttendanceImportPage');
const MyLeavePage = pg(() => import('../pages/MyLeavePage'), 'MyLeavePage');
const TeamLeavePage = pg(() => import('../pages/TeamLeavePage'), 'TeamLeavePage');
const LeaveSetupPage = pg(() => import('../pages/LeaveSetupPage'), 'LeaveSetupPage');
const TripsPage = pg(() => import('../pages/TripsPage'), 'TripsPage');
const TripsFinancePage = pg(() => import('../pages/TripsFinancePage'), 'TripsFinancePage');
const TripRatesPage = pg(() => import('../pages/TripRatesPage'), 'TripRatesPage');
const TripAuthorizationPage = pg(() => import('../pages/TripAuthorizationPage'), 'TripAuthorizationPage');
const TripDetailPage = pg(() => import('../pages/TripDetailPage'), 'TripDetailPage');
const PayrollSetupPage = pg(() => import('../pages/PayrollSetupPage'), 'PayrollSetupPage');
const PayrollRunsPage = pg(() => import('../pages/PayrollRunsPage'), 'PayrollRunsPage');
const PayrollRunDetailPage = pg(() => import('../pages/PayrollRunDetailPage'), 'PayrollRunDetailPage');
const PayslipPage = pg(() => import('../pages/PayslipPage'), 'PayslipPage');
const PayrollExportsPage = pg(() => import('../pages/PayrollExportsPage'), 'PayrollExportsPage');
const MyPayslipsPage = pg(() => import('../pages/MyPayslipsPage'), 'MyPayslipsPage');
const MyPayslipPage = pg(() => import('../pages/MyPayslipsPage'), 'MyPayslipPage');
const MeetingsPage = pg(() => import('../pages/MeetingsPage'), 'MeetingsPage');
const EventsPage = pg(() => import('../pages/EventsPage'), 'EventsPage');
const EventDetailPage = pg(() => import('../pages/EventDetailPage'), 'EventDetailPage');
const SuppliersPage = pg(() => import('../pages/SuppliersPage'), 'SuppliersPage');
const SupplierDetailPage = pg(() => import('../pages/SupplierDetailPage'), 'SupplierDetailPage');
const PurchaseRequisitionsPage = pg(() => import('../pages/PurchaseRequisitionsPage'), 'PurchaseRequisitionsPage');
const PurchaseRequisitionDetailPage = pg(() => import('../pages/PurchaseRequisitionDetailPage'), 'PurchaseRequisitionDetailPage');
const PurchaseOrdersPage = pg(() => import('../pages/PurchaseOrdersPage'), 'PurchaseOrdersPage');
const PurchaseOrderDetailPage = pg(() => import('../pages/PurchaseOrderDetailPage'), 'PurchaseOrderDetailPage');
const RfqsPage = pg(() => import('../pages/RfqsPage'), 'RfqsPage');
const RfqDetailPage = pg(() => import('../pages/RfqDetailPage'), 'RfqDetailPage');
const SupplierInvoicesPage = pg(() => import('../pages/SupplierInvoicesPage'), 'SupplierInvoicesPage');
const SupplierInvoiceDetailPage = pg(() => import('../pages/SupplierInvoiceDetailPage'), 'SupplierInvoiceDetailPage');
const MeetingRoomsPage = pg(() => import('../pages/MeetingRoomsPage'), 'MeetingRoomsPage');
const MeetingDetailPage = pg(() => import('../pages/MeetingDetailPage'), 'MeetingDetailPage');
const MeetingCheckinPage = pg(() => import('../pages/MeetingCheckinPage'), 'MeetingCheckinPage');
const MinutesPage = pg(() => import('../pages/MinutesPage'), 'MinutesPage');
const FollowUpsPage = pg(() => import('../pages/FollowUpsPage'), 'FollowUpsPage');
const MinutesDetailPage = pg(() => import('../pages/MinutesDetailPage'), 'MinutesDetailPage');
const ClientsPage = pg(() => import('../pages/ClientsPage'), 'ClientsPage');
const RulesPage = pg(() => import('../pages/RulesPage'), 'RulesPage');
const ApprovalFlowsPage = pg(() => import('../pages/ApprovalFlowsPage'), 'ApprovalFlowsPage');
const DepartmentAccessPage = pg(() => import('../pages/DepartmentAccessPage'), 'DepartmentAccessPage');
const ProjectsPage = pg(() => import('../pages/ProjectsPage'), 'ProjectsPage');
const ProjectDetailPage = pg(() => import('../pages/ProjectDetailPage'), 'ProjectDetailPage');
const TimePage = pg(() => import('../pages/TimePage'), 'TimePage');
const RateCardsPage = pg(() => import('../pages/RateCardsPage'), 'RateCardsPage');
const InvoicesPage = pg(() => import('../pages/InvoicesPage'), 'InvoicesPage');
const InvoiceDetailPage = pg(() => import('../pages/InvoiceDetailPage'), 'InvoiceDetailPage');
const TaxRatesPage = pg(() => import('../pages/TaxRatesPage'), 'TaxRatesPage');
const ClientPortalAdminPage = pg(() => import('../pages/ClientPortalAdminPage'), 'ClientPortalAdminPage');
const ProfilePage = pg(() => import('../pages/ProfilePage'), 'ProfilePage');

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export const enterprise = definePlugin<ComponentType<any>>({
  title: 'LASABRO Enterprise (host edition)',
  routes: [
    { path: '/dashboard', component: DashboardPage, permission: 'dashboard.view' },
    { path: '/assets', component: AssetsPage, permission: 'asset.view' },
    { path: '/assets/movements', component: MovementsPage, permission: 'asset.request_movement' },
    { path: '/access-register', component: AccessRegisterPage, permission: 'access.view' },
    { path: '/incidents', component: IncidentsPage, permission: 'case.view_own' },
    { path: '/archive/records', component: RecordsPage, permission: 'archive.view' },
    { path: '/archive/access', component: ArchiveAccessPage, permission: 'archive.view' },
    { path: '/archive/inventory', component: InventoryPage, permission: 'archive.manage' },
    { path: '/archive/disposal', component: DisposalPage, permission: ['archive.manage', 'archive.approve_disposal'] },
    { path: '/archive/retention', component: RetentionPage, permission: 'archive.view' },
    { path: '/compliance/calendar', component: ComplianceCalendarPage, permission: 'compliance.view' },
    { path: '/compliance/backups', component: BackupsPage, permission: ['backup.manage', 'compliance.view'] },
    { path: '/compliance/training', component: TrainingPage, permission: ['training.view', 'training.manage'] },
    { path: '/compliance/audits', component: AuditsPage, permission: 'compliance.view' },
    { path: '/compliance/reports', component: ManagementReportsPage, permission: ['report.receive', 'compliance.view'] },
    { path: '/compliance/legal', component: LegalReferencesPage, permission: 'archive.view' },
    { path: '/reports', component: ReportsPage, permission: ['report.view', 'report.receive'] },
    { path: '/reports/:code', component: ReportViewPage, permission: ['report.view', 'report.receive'] },
    { path: '/files', component: FilesPage, permission: 'file.view' },
    { path: '/cases', component: CasesPage, permission: 'case.view_own' },
    { path: '/cases/:id', component: CaseDetailPage, permission: 'case.view_own' },
    { path: '/support/chats', component: SupportChatsPage, permission: 'chat.moderate' },
    { path: '/admin/users', component: UsersPage, permission: 'user.view' },
    { path: '/admin/roles', component: RolesPage, permission: 'role.view' },
    { path: '/admin/organization', component: OrganizationPage, permission: 'organization.view' },
    { path: '/admin/departments', component: DepartmentsPage, permission: 'department.view' },
    { path: '/admin/locations', component: LocationsPage, permission: 'location.view' },
    { path: '/admin/lookups', component: LookupsPage, permission: 'lookup.view' },
    { path: '/admin/audit-log', component: AuditLogPage, permission: 'audit_log.view' },
    { path: '/approvals', component: ApprovalsPage, permission: ['approval.view', 'approval.act'] },
    { path: '/hr/employees', component: EmployeesPage, permission: 'employee.view' },
    { path: '/hr/positions', component: PositionsPage, permission: ['employee.view', 'hr.structure.manage'] },
    { path: '/hr/employment-changes', component: EmploymentChangesPage, permission: 'hr.history.manage' },
    { path: '/hr/reviews', component: ReviewsPage, permission: ['hr.reviews.manage', 'hr.reviews.write', 'hr.self.view'] },
    { path: '/me/employment', component: MyEmploymentPage, permission: 'hr.self.view' },
    { path: '/hr/requisitions', component: RequisitionsPage, permission: ['hr.requisition.create', 'hr.requisition.approve', 'hr.recruitment.manage'] },
    { path: '/hr/recruitment', component: RecruitmentPage, permission: 'hr.recruitment.manage' },
    { path: '/hr/interviews', component: InterviewsPage, permission: ['hr.interview.score', 'hr.recruitment.manage'] },
    { path: '/attendance/clock', component: ClockPage, permission: 'hr.attendance.clock' },
    { path: '/attendance/team', component: TeamAttendancePage, permission: ['hr.attendance.view', 'hr.attendance.manage'] },
    { path: '/attendance/setup', component: AttendanceSetupPage, permission: 'hr.attendance.manage' },
    { path: '/attendance/import', component: AttendanceImportPage, permission: 'hr.attendance.manage' },
    { path: '/leave/me', component: MyLeavePage, permission: 'hr.leave.request' },
    { path: '/leave/team', component: TeamLeavePage, permission: ['hr.leave.view', 'hr.leave.manage'] },
    { path: '/leave/setup', component: LeaveSetupPage, permission: ['hr.leave.manage', 'hr.leave.legal.manage', 'hr.leave.profile', 'rules.view'] },
    { path: '/trips', component: TripsPage, permission: ['hr.trip.request', 'hr.trip.view', 'hr.trip.finance'] },
    { path: '/trips/finance', component: TripsFinancePage, permission: 'hr.trip.finance' },
    { path: '/trips/rates', component: TripRatesPage, permission: ['hr.trip.configure', 'hr.trip.finance'] },
    { path: '/trips/:id/authorization', component: TripAuthorizationPage, permission: ['hr.trip.request', 'hr.trip.view', 'hr.trip.finance'] },
    { path: '/trips/:id', component: TripDetailPage, permission: ['hr.trip.request', 'hr.trip.view', 'hr.trip.finance'] },
    { path: '/payroll/setup', component: PayrollSetupPage, permission: ['hr.payroll.view', 'hr.payroll.configure', 'hr.payroll.verify', 'hr.payroll.prepare'] },
    { path: '/payroll/runs', component: PayrollRunsPage, permission: ['hr.payroll.view', 'hr.payroll.configure', 'hr.payroll.verify', 'hr.payroll.prepare', 'hr.payroll.review', 'hr.payroll.approve', 'hr.payroll.approve_md', 'hr.payroll.lock', 'hr.payroll.pay', 'hr.payroll.declare'] },
    { path: '/payroll/runs/:id', component: PayrollRunDetailPage, permission: ['hr.payroll.view', 'hr.payroll.configure', 'hr.payroll.verify', 'hr.payroll.prepare', 'hr.payroll.review', 'hr.payroll.approve', 'hr.payroll.approve_md', 'hr.payroll.lock', 'hr.payroll.pay', 'hr.payroll.declare'] },
    { path: '/payroll/payslips/:id', component: PayslipPage, permission: ['hr.payroll.view', 'hr.payroll.configure', 'hr.payroll.verify', 'hr.payroll.prepare', 'hr.payroll.review', 'hr.payroll.approve', 'hr.payroll.approve_md', 'hr.payroll.lock', 'hr.payroll.pay', 'hr.payroll.declare'] },
    { path: '/payroll/exports', component: PayrollExportsPage, permission: ['hr.payroll.export', 'hr.payroll.export.configure'] },
    { path: '/payroll/me', component: MyPayslipsPage, permission: 'hr.payslip.own' },
    { path: '/payroll/me/:id', component: MyPayslipPage, permission: 'hr.payslip.own' },
    { path: '/meetings', component: MeetingsPage, permission: 'meeting.view' },
    { path: '/events', component: EventsPage, permission: 'event.view' },
    { path: '/events/:id', component: EventDetailPage, permission: 'event.view' },
    { path: '/suppliers', component: SuppliersPage, permission: 'procurement.view' },
    { path: '/suppliers/:id', component: SupplierDetailPage, permission: 'procurement.view' },
    { path: '/requisitions', component: PurchaseRequisitionsPage, permission: 'procurement.requisition.create' },
    { path: '/requisitions/:id', component: PurchaseRequisitionDetailPage, permission: 'procurement.requisition.create' },
    { path: '/purchase-orders', component: PurchaseOrdersPage, permission: 'procurement.receive' },
    { path: '/purchase-orders/:id', component: PurchaseOrderDetailPage, permission: 'procurement.receive' },
    { path: '/rfqs', component: RfqsPage, permission: 'procurement.manage' },
    { path: '/rfqs/:id', component: RfqDetailPage, permission: 'procurement.manage' },
    { path: '/supplier-invoices', component: SupplierInvoicesPage, permission: ['procurement.invoice', 'procurement.invoice.approve', 'procurement.invoice.pay'] },
    { path: '/supplier-invoices/:id', component: SupplierInvoiceDetailPage, permission: ['procurement.invoice', 'procurement.invoice.approve', 'procurement.invoice.pay'] },
    { path: '/meetings/rooms', component: MeetingRoomsPage, permission: 'meeting.room.manage' },
    { path: '/meetings/:id', component: MeetingDetailPage, permission: 'meeting.view' },
    { path: '/meeting-checkin/:token', component: MeetingCheckinPage, permission: 'meeting.view' },
    { path: '/minutes', component: MinutesPage, permission: 'meeting.view' },
    { path: '/follow-ups', component: FollowUpsPage, permission: 'meeting.view' },
    { path: '/minutes/:id', component: MinutesDetailPage, permission: 'meeting.view' },
    { path: '/clients', component: ClientsPage, permission: 'client.view' },
    { path: '/admin/rules', component: RulesPage, permission: 'rules.view' },
    { path: '/admin/approval-flows', component: ApprovalFlowsPage, permission: 'approval.configure' },
    { path: '/admin/department-access', component: DepartmentAccessPage, permission: 'scope.manage' },
    { path: '/projects', component: ProjectsPage, permission: 'project.view' },
    { path: '/projects/:id', component: ProjectDetailPage, permission: 'project.view' },
    { path: '/time', component: TimePage, permission: 'time.track' },
    { path: '/admin/rate-cards', component: RateCardsPage, permission: 'rates.view' },
    { path: '/invoices', component: InvoicesPage, permission: 'billing.view' },
    { path: '/invoices/:id', component: InvoiceDetailPage, permission: 'billing.view' },
    { path: '/admin/tax-rates', component: TaxRatesPage, permission: 'billing.configure' },
    { path: '/admin/client-portal', component: ClientPortalAdminPage, permission: 'client.manage' },
    { path: '/profile', component: ProfilePage },
  ],
});
