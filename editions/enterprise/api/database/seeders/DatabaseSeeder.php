<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Lasabro\Procurement\Database\Seeders\ProcurementFilesSeeder;
use Lasabro\Procurement\Database\Seeders\ProcurementInvoicesSeeder;
use Lasabro\Procurement\Database\Seeders\ProcurementOrdersSeeder;
use Lasabro\Procurement\Database\Seeders\ProcurementRfqSeeder;
use Lasabro\Procurement\Database\Seeders\ProcurementSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            LanguageSeeder::class,
            LookupSeeder::class,
            PermissionSeeder::class,
            MenuAndWidgetSeeder::class,
            ServiceDeskSeeder::class,        // Case/chat lookups, bot knowledge, menu, widgets
            DemoOrganizationSeeder::class,   // generic demo tenant; remove for production installs
            ServiceDeskRolesSeeder::class,   // Widgets per role + admin phone (runs after roles exist)
            FilesSeeder::class,              // File lookups, classification levels, menu, widgets, role grants
            ITOperationsSeeder::class,       // Assets, movements, access register, incidents, IT manager role
            ArchiveSeeder::class,            // Records, retention, disposal, legal references, records manager + compliance officer roles
            ComplianceSeeder::class,         // Backups, training, compliance calendar, audits
            ReportsSeeder::class,            // Reports menu and report permissions per role
            CopsFoundationSeeder::class,
            ProjectsSeeder::class,
            TimeSeeder::class,
            BillingSeeder::class,
            PortalSeeder::class,             // // COPS Phase 2b               // // COPS Phase 1     // Approvals, rules, scope, employees, clients
            HrSeeder::class,                 // HR lookups, permissions, hr_manager role, employment-change flow
            HrRecruitmentSeeder::class,      // Recruitment lookups, permissions, approval flows, onboarding template
            HrAttendanceSeeder::class,       // Attendance lookups, permissions, correction approval flow
            HrLeaveSeeder::class,
            HrTripSeeder::class,
            HrPayrollSeeder::class,          // Payroll lookups, permissions, menu (no rate or scheme is seeded)
            HrPayrollRunSeeder::class,       // Run / payslip lookups, stage permissions, menu
            HrPayrollExportSeeder::class,    // Export lookups and permissions (no layout or account is seeded)
            MeetingsSeeder::class,          // Meeting lookups, permissions, menu
            MeetingsAttendanceSeeder::class, // Attendance statuses and check-in methods
            MeetingsMinutesSeeder::class, // Minutes statuses, visibilities and the chair-approval flow
            MeetingsFollowUpSeeder::class, // Resolution and action statuses, follow-up menu
            EventsSeeder::class, // Event lookups, permissions, menu
            EventsExpensesSeeder::class, // Expense categories, statuses, approval flow
            ProcurementSeeder::class, // Procurement lookups, permissions, menu, approval flows
            ProcurementOrdersSeeder::class, // Purchase order statuses, receiving permission, flow
            ProcurementInvoicesSeeder::class, // Invoice statuses, permissions, menu
            ProcurementRfqSeeder::class, // RFQ statuses and menu
            ProcurementFilesSeeder::class, // File link types for quote and invoice documents
            MeetingsOnlineSeeder::class, // Online meeting lookups
            MeetingsCollabSeeder::class,
            MeetingBaselineAccessSeeder::class, // COPS b37: every role may open its own meetings and decide its approvals
            PolishSeeder::class,             // currency list and menu icons
            CodesSeeder::class,              // system rule sets with fixed keys
        ]);
    }
}
