<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [

            // ── Users & Roles ─────────────────────────────────────────────────
            ['module' => 'Users',    'name' => 'users.view',       'description' => 'View user list'],
            ['module' => 'Users',    'name' => 'users.create',     'description' => 'Create new users'],
            ['module' => 'Users',    'name' => 'users.update',     'description' => 'Edit user accounts'],
            ['module' => 'Users',    'name' => 'users.delete',     'description' => 'Delete user accounts'],
            ['module' => 'Users',    'name' => 'users.manage',     'description' => 'Full user management'],
            ['module' => 'Roles',    'name' => 'roles.view',       'description' => 'View roles list'],
            ['module' => 'Roles',    'name' => 'roles.create',     'description' => 'Create roles'],
            ['module' => 'Roles',    'name' => 'roles.update',     'description' => 'Edit roles'],
            ['module' => 'Roles',    'name' => 'roles.delete',     'description' => 'Delete roles'],
            ['module' => 'Roles',    'name' => 'roles.assign',     'description' => 'Assign roles to users'],

            // ── HR ────────────────────────────────────────────────────────────
            ['module' => 'HR',       'name' => 'hr.view',          'description' => 'View HR module'],
            ['module' => 'HR',       'name' => 'hr.employees.view','description' => 'View employee list'],
            ['module' => 'HR',       'name' => 'hr.employees.manage','description' => 'Manage employees'],
            ['module' => 'HR',       'name' => 'hr.attendance.view','description' => 'View attendance logs'],
            ['module' => 'HR',       'name' => 'hr.pds.view',      'description' => 'View own PDS'],
            ['module' => 'HR',       'name' => 'hr.pds.manage',    'description' => 'Manage all PDS records'],
            ['module' => 'HR',       'name' => 'hr.schedule.view', 'description' => 'View schedules'],
            ['module' => 'HR',       'name' => 'hr.schedule.manage','description' => 'Manage schedules'],
            ['module' => 'HR',       'name' => 'hr.gatepass.view', 'description' => 'View gate passes'],
            ['module' => 'HR',       'name' => 'hr.gatepass.create','description' => 'Create gate passes'],
            ['module' => 'HR',       'name' => 'hr.gatepass.approve','description' => 'Approve gate passes'],

            // ── WFH ───────────────────────────────────────────────────────────
            ['module' => 'WFH',      'name' => 'wfh.view',         'description' => 'Access WFH dashboard'],
            ['module' => 'WFH',      'name' => 'wfh.time-in',      'description' => 'Record WFH time-in'],
            ['module' => 'WFH',      'name' => 'wfh.time-out',     'description' => 'Record WFH time-out'],
            ['module' => 'WFH',      'name' => 'wfh.accomplishments.create', 'description' => 'Add WFH accomplishments'],
            ['module' => 'WFH',      'name' => 'wfh.accomplishments.delete', 'description' => 'Delete own WFH accomplishments'],
            ['module' => 'WFH',      'name' => 'wfh.monitor',      'description' => 'Monitor WFH attendance of subordinates'],

            // ── Performance / IPCR ────────────────────────────────────────────
            ['module' => 'IPCR',     'name' => 'ipcr.view',        'description' => 'View own IPCR'],
            ['module' => 'IPCR',     'name' => 'ipcr.create',      'description' => 'Create IPCR entries'],
            ['module' => 'IPCR',     'name' => 'ipcr.update',      'description' => 'Update IPCR entries'],
            ['module' => 'IPCR',     'name' => 'ipcr.submit',      'description' => 'Submit IPCR for approval'],
            ['module' => 'IPCR',     'name' => 'ipcr.approve',     'description' => 'Approve IPCR submissions'],
            ['module' => 'IPCR',     'name' => 'ipcr.monitor',     'description' => 'Monitor unit/division IPCR'],

            // ── Accomplishments ───────────────────────────────────────────────
            ['module' => 'Accomplishments', 'name' => 'accomplishments.view',   'description' => 'View own accomplishments'],
            ['module' => 'Accomplishments', 'name' => 'accomplishments.create', 'description' => 'Add accomplishments'],
            ['module' => 'Accomplishments', 'name' => 'accomplishments.update', 'description' => 'Edit accomplishments'],
            ['module' => 'Accomplishments', 'name' => 'accomplishments.delete', 'description' => 'Delete accomplishments'],

            // ── Class Records ─────────────────────────────────────────────────
            ['module' => 'ClassRecord', 'name' => 'class-records.view',   'description' => 'View own class records (teacher)'],
            ['module' => 'ClassRecord', 'name' => 'class-records.manage', 'description' => 'Create, edit, enter scores for own class records'],
            ['module' => 'ClassRecord', 'name' => 'class-records.admin',  'description' => 'View all class records, check, unlock quarters (AUH/Admin)'],

            // ── IT / MIS ──────────────────────────────────────────────────────
            ['module' => 'IT',       'name' => 'it.requests.view',   'description' => 'View IT job requests'],
            ['module' => 'IT',       'name' => 'it.requests.create', 'description' => 'Submit IT job requests'],
            ['module' => 'IT',       'name' => 'it.requests.manage', 'description' => 'Manage all IT job requests'],
            ['module' => 'IT',       'name' => 'it.equipment.view',  'description' => 'View ICT equipment'],
            ['module' => 'IT',       'name' => 'it.equipment.manage','description' => 'Manage ICT equipment & PMS'],

            // ── Facilities & Services ─────────────────────────────────────────
            ['module' => 'Facilities','name' => 'facilities.view',       'description' => 'View facility requests'],
            ['module' => 'Facilities','name' => 'facilities.create',     'description' => 'Submit facility requests'],
            ['module' => 'Facilities','name' => 'facilities.manage',     'description' => 'Manage facility requests (GSU Head / Admin)'],
            ['module' => 'Facilities','name' => 'facilities.dc-approve', 'description' => 'Division Chief in-app approval of facility, work, and service requests'],
            ['module' => 'Facilities','name' => 'facilities.fad-approve','description' => 'FAD Chief in-app approval of facility, work, and service requests'],
            ['module' => 'Vehicles', 'name' => 'vehicles.view',          'description' => 'View vehicle requests'],
            ['module' => 'Vehicles', 'name' => 'vehicles.create',        'description' => 'Submit vehicle requests'],
            ['module' => 'Vehicles', 'name' => 'vehicles.manage',        'description' => 'Manage vehicle requests (GSU Head / Admin)'],
            ['module' => 'Vehicles', 'name' => 'vehicles.dc-approve',    'description' => 'Division Chief in-app approval of vehicle requests'],

            // ── Messengerial ──────────────────────────────────────────────────
            ['module' => 'Messengerial', 'name' => 'messengerial.view',       'description' => 'View messengerial requests'],
            ['module' => 'Messengerial', 'name' => 'messengerial.create',     'description' => 'Submit messengerial requests'],
            ['module' => 'Messengerial', 'name' => 'messengerial.manage',     'description' => 'Manage all messengerial requests (Records / Admin)'],
            ['module' => 'Messengerial', 'name' => 'messengerial.dc-approve', 'description' => 'Division Chief in-app approval of messengerial requests'],
            ['module' => 'Messengerial', 'name' => 'messengerial.ocd-approve','description' => 'OCD in-app approval of messengerial requests'],

            // ── Documents ─────────────────────────────────────────────────────
            ['module' => 'Documents','name' => 'documents.view',     'description' => 'View documents'],
            ['module' => 'Documents','name' => 'documents.create',   'description' => 'Upload documents'],
            ['module' => 'Documents','name' => 'documents.update',   'description' => 'Edit documents'],
            ['module' => 'Documents','name' => 'documents.delete',   'description' => 'Delete documents'],
            ['module' => 'Documents','name' => 'documents.approve',  'description' => 'Approve document routing'],

            // ── Library ───────────────────────────────────────────────────────
            ['module' => 'Library',  'name' => 'library.view',       'description' => 'View library collections'],
            ['module' => 'Library',  'name' => 'library.manage',     'description' => 'Manage collections & borrowings'],

            // ── Clinic / Health ───────────────────────────────────────────────
            ['module' => 'Health',   'name' => 'health.view',        'description' => 'View health records'],
            ['module' => 'Health',   'name' => 'health.manage',      'description' => 'Manage clinic records'],

            // ── Guidance ──────────────────────────────────────────────────────
            ['module' => 'Guidance', 'name' => 'guidance.view',              'description' => 'View guidance consultations'],
            ['module' => 'Guidance', 'name' => 'guidance.refer',             'description' => 'Submit student referrals to guidance office'],
            ['module' => 'Guidance', 'name' => 'guidance.manage',            'description' => 'Manage guidance consultations & interventions'],
            ['module' => 'Guidance', 'name' => 'guidance.cumulative.view',   'description' => 'View student cumulative guidance records (EGCU profiles)'],
            ['module' => 'Guidance', 'name' => 'guidance.cumulative.manage', 'description' => 'Add and edit student cumulative guidance records'],

            // ── Registrar ─────────────────────────────────────────────────────
            ['module' => 'Registrar', 'name' => 'students.enrollment.view',   'description' => 'View enrollment records, section rosters'],
            ['module' => 'Registrar', 'name' => 'students.enrollment.manage', 'description' => 'Enroll, drop, transfer students; manage enrollment periods'],
            ['module' => 'Registrar', 'name' => 'students.policies.manage',   'description' => 'Configure retention, exclusion, and honors policies'],
            ['module' => 'Registrar', 'name' => 'students.transcript.view',   'description' => 'View student transcripts and download report cards'],
            ['module' => 'Registrar', 'name' => 'students.transcript.manage', 'description' => 'Compute, recompute, and lock student annual grades'],
            ['module' => 'Registrar', 'name' => 'students.records.export',    'description' => 'Generate and download COE, Good Moral, and Clearance PDFs'],
            ['module' => 'Registrar', 'name' => 'students.promotion.process', 'description' => 'Run year-end promotion and advance students to next school year'],
            ['module' => 'Registrar', 'name' => 'students.analytics.view',    'description' => 'View enrollment analytics and performance dashboard'],

            // ── Student Health Records ────────────────────────────────────────
            ['module' => 'Student Health', 'name' => 'students.health.view',   'description' => 'View student medical records (allergies, immunizations, history, vitamins)'],
            ['module' => 'Student Health', 'name' => 'students.health.manage', 'description' => 'Add and edit student medical records'],

            // ── Procurement — Purchase Request (PR) ───────────────────────────
            ['module' => 'Procurement','name' => 'procurement.view',          'description' => 'View procurement records (legacy)'],
            ['module' => 'Procurement','name' => 'procurement.create',        'description' => 'Create procurement requests (legacy)'],
            ['module' => 'Procurement','name' => 'procurement.approve',       'description' => 'Approve procurement requests (legacy)'],
            ['module' => 'Procurement','name' => 'procurement.pr.view',       'description' => 'View Purchase Requests'],
            ['module' => 'Procurement','name' => 'procurement.pr.create',     'description' => 'Create and edit own Purchase Requests'],
            ['module' => 'Procurement','name' => 'procurement.pr.dc_sign',    'description' => 'Division Chief: sign Purchase Requests'],
            ['module' => 'Procurement','name' => 'procurement.pr.number',     'description' => 'Procurement Officer: assign official PR numbers'],
            ['module' => 'Procurement','name' => 'procurement.pr.ocd_sign',   'description' => 'OCD: sign and approve Purchase Requests'],
            ['module' => 'Procurement','name' => 'procurement.pr.bo_initial', 'description' => 'Budget Officer: initial supplemental PR documents'],

            // ── Procurement — Obligation Request Status (ORS) ─────────────────
            ['module' => 'ORS',        'name' => 'procurement.ors.view',         'description' => 'View ORS records'],
            ['module' => 'ORS',        'name' => 'procurement.ors.create',       'description' => 'Create and log ORS records'],
            ['module' => 'ORS',        'name' => 'procurement.ors.dc_sign',      'description' => 'Division Chief: sign ORS'],
            ['module' => 'ORS',        'name' => 'procurement.ors.budget_sign',  'description' => 'Budget Officer: verify and number ORS'],
            ['module' => 'ORS',        'name' => 'procurement.ors.bookkeep',     'description' => 'Bookkeeper: verify ORS completeness'],
            ['module' => 'ORS',        'name' => 'procurement.ors.account',      'description' => 'Accountant: review ORS data'],
            ['module' => 'ORS',        'name' => 'procurement.ors.ocd_sign',     'description' => 'OCD: sign ORS and PO'],

            // ── Procurement — Purchase Order (PO) ────────────────────────────
            ['module' => 'PO',         'name' => 'procurement.po.view',    'description' => 'View Purchase Orders'],
            ['module' => 'PO',         'name' => 'procurement.po.create',  'description' => 'Create and manage own Purchase Orders'],
            ['module' => 'PO',         'name' => 'procurement.po.review',  'description' => 'Procurement Officer: review and approve POs'],
            ['module' => 'PO',         'name' => 'procurement.po.sign',    'description' => 'Campus Director: sign and issue POs'],

            // ── Procurement — Disbursement Voucher (DV) ───────────────────────
            ['module' => 'DV',         'name' => 'procurement.dv.view',          'description' => 'View Disbursement Vouchers'],
            ['module' => 'DV',         'name' => 'procurement.dv.create',        'description' => 'Create and prepare Disbursement Vouchers'],
            ['module' => 'DV',         'name' => 'procurement.dv.delivery',      'description' => 'Record delivery and acceptance (IAR, RIS, DR)'],
            ['module' => 'DV',         'name' => 'procurement.dv.bookkeep',      'description' => 'Bookkeeper: verify DV completeness'],
            ['module' => 'DV',         'name' => 'procurement.dv.account',       'description' => 'Accountant: review DV data'],
            ['module' => 'DV',         'name' => 'procurement.dv.ocd_sign',      'description' => 'OCD: sign DV and authorize payment'],
            ['module' => 'DV',         'name' => 'procurement.dv.cashier',       'description' => 'Cashier: process DV payment'],

            // ── Procurement — Request for Quotation (RFQ) ─────────────────────
            ['module' => 'RFQ',        'name' => 'procurement.rfq.view',     'description' => 'View RFQ list and details'],
            ['module' => 'RFQ',        'name' => 'procurement.rfq.create',   'description' => 'Create RFQs and manage suppliers'],
            ['module' => 'RFQ',        'name' => 'procurement.rfq.upload',   'description' => 'Upload scanned quotations from suppliers'],
            ['module' => 'RFQ',        'name' => 'procurement.rfq.evaluate', 'description' => 'Enter/edit quotation prices and build Abstract of Quotations'],
            ['module' => 'RFQ',        'name' => 'procurement.rfq.award',    'description' => 'Award items to suppliers and generate Purchase Orders'],

            // ── Reports ───────────────────────────────────────────────────────
            ['module' => 'Reports',  'name' => 'reports.view',       'description' => 'View system reports'],
            ['module' => 'Reports',  'name' => 'reports.export',     'description' => 'Export reports'],

            // ── Recruitment & Selection ───────────────────────────────────────
            ['module' => 'Recruitment', 'name' => 'recruitment.view',       'description' => 'View job items, vacancies and applications'],
            ['module' => 'Recruitment', 'name' => 'recruitment.manage',     'description' => 'Manage job items, types, and applicant records'],
            ['module' => 'Recruitment', 'name' => 'recruitment.publish',    'description' => 'Publish job vacancies'],
            ['module' => 'Recruitment', 'name' => 'recruitment.apply',      'description' => 'Submit applications for vacancies'],
            ['module' => 'Recruitment', 'name' => 'recruitment.evaluate',   'description' => 'Score applicants and manage interviews'],
            ['module' => 'Recruitment', 'name' => 'recruitment.rank',       'description' => 'Compute and manage applicant rankings'],
            ['module' => 'Recruitment', 'name' => 'recruitment.approve',    'description' => 'Approve selections and create placements'],
            ['module' => 'Recruitment', 'name' => 'recruitment.onboarding', 'description' => 'Manage onboarding tasks for new hires'],

            // ── Learning & Development (L&D) ──────────────────────────────────
            ['module' => 'LnD', 'name' => 'lnd.view',     'description' => 'View learning programs, sessions, TNA, and IDP'],
            ['module' => 'LnD', 'name' => 'lnd.create',   'description' => 'Create learning programs, sessions, TNA, and IDP entries'],
            ['module' => 'LnD', 'name' => 'lnd.edit',     'description' => 'Edit learning programs, sessions, and IDP records'],
            ['module' => 'LnD', 'name' => 'lnd.delete',   'description' => 'Delete L&D records'],
            ['module' => 'LnD', 'name' => 'lnd.approve',  'description' => 'Approve nominations, TNA, and IDP submissions'],
            ['module' => 'LnD', 'name' => 'lnd.evaluate', 'description' => 'Submit and manage Kirkpatrick evaluations'],

            // ── Rewards & Recognition (PRAISE) ────────────────────────────────
            ['module' => 'Rewards', 'name' => 'rewards.view',     'description' => 'View nominations, evaluations, approvals, and awards'],
            ['module' => 'Rewards', 'name' => 'rewards.nominate', 'description' => 'Submit nominations for rewards and recognition'],
            ['module' => 'Rewards', 'name' => 'rewards.evaluate', 'description' => 'Evaluate nominations as a committee member'],
            ['module' => 'Rewards', 'name' => 'rewards.approve',  'description' => 'Approve or reject nominations (committee / head of office)'],
            ['module' => 'Rewards', 'name' => 'rewards.manage',   'description' => 'Manage reward types and record awards (HR/Admin)'],

            // ── Payroll ───────────────────────────────────────────────────────
            ['module' => 'Payroll', 'name' => 'payroll.view',           'description' => 'View payroll runs and employee payslips'],
            ['module' => 'Payroll', 'name' => 'payroll.process',        'description' => 'Compute/process payroll runs'],
            ['module' => 'Payroll', 'name' => 'payroll.approve',        'description' => 'Approve payroll runs'],
            ['module' => 'Payroll', 'name' => 'payroll.manage',         'description' => 'Full payroll management (allowance types, configs)'],

            // ── HR — DTR & Biometric (new module) ─────────────────────────────
            ['module' => 'HR',     'name' => 'hr.dtr.view',            'description' => 'View DTR records for all employees'],
            ['module' => 'HR',     'name' => 'hr.dtr.manage',          'description' => 'Generate, edit, and lock DTR records'],
            ['module' => 'HR',     'name' => 'hr.biometric.manage',    'description' => 'Upload and resolve biometric logs'],
            ['module' => 'HR',     'name' => 'dtr.view_own',           'description' => 'View and submit penned entries on own DTR'],

            // ── HR — Leave (new module) ────────────────────────────────────────
            ['module' => 'HR',     'name' => 'hr.leave.view',          'description' => 'View all leave applications'],
            ['module' => 'HR',     'name' => 'hr.leave.file',          'description' => 'File own leave applications'],
            ['module' => 'HR',     'name' => 'hr.leave.approve',       'description' => 'Approve or deny leave applications'],

            // ── HR — Leave Credits ─────────────────────────────────────────────
            ['module' => 'HR',     'name' => 'hr.leave.credits.view',    'description' => 'View own leave credit balances and transaction history'],
            ['module' => 'HR',     'name' => 'hr.leave.credits.manage',  'description' => 'Initialize and manually adjust employee leave credits'],
            ['module' => 'HR',     'name' => 'hr.leave.credits.service', 'description' => 'Approve or reject teaching service credit records'],
            ['module' => 'HR',     'name' => 'hr.leave.credits.reports', 'description' => 'Access leave credit audit ledger and accrual/utilization reports'],

            // ── HR — Employee Profile (payroll context) ────────────────────────
            ['module' => 'HR',     'name' => 'hr.employee.view',       'description' => 'View employee profiles and salary info'],
            ['module' => 'HR',     'name' => 'hr.employee.manage',     'description' => 'Edit employee profiles and salary grades'],

            // ── SALN ──────────────────────────────────────────────────────────
            ['module' => 'SALN', 'name' => 'saln.create',   'description' => 'Create and manage own SALN (all employees)'],
            ['module' => 'SALN', 'name' => 'saln.submit',   'description' => 'Submit SALN for committee review'],
            ['module' => 'SALN', 'name' => 'saln.review',   'description' => 'Review submitted SALNs as committee member'],
            ['module' => 'SALN', 'name' => 'saln.approve',  'description' => 'Approve or return SALN (committee head)'],
            ['module' => 'SALN', 'name' => 'saln.view_all', 'description' => 'View all employee SALNs (HR Office)'],
            ['module' => 'SALN', 'name' => 'saln.file',     'description' => 'Mark approved SALN as officially filed (HR Office)'],

            // ── Student Gate Attendance ───────────────────────────────────────
            ['module' => 'Student Attendance', 'name' => 'students.attendance.view',   'description' => 'View gate attendance logs and parent contacts'],
            ['module' => 'Student Attendance', 'name' => 'students.attendance.scan',   'description' => 'Operate the gate kiosk scanner'],
            ['module' => 'Student Attendance', 'name' => 'students.attendance.manage', 'description' => 'Full gate attendance management'],

            // ── Activity Management System (AMS) ─────────────────────────────
            ['module' => 'AMS', 'name' => 'activities.manage',  'description' => 'Create and manage own activities, participants, and certificates'],
            ['module' => 'AMS', 'name' => 'activities.view_all','description' => 'View all activities read-only (HR Office)'],

            // ── Chat ──────────────────────────────────────────────────────────
            ['module' => 'Chat',   'name' => 'chat.access',            'description' => 'Access the real-time messaging module'],

            // ── Faculty Loading ───────────────────────────────────────────────
            ['module' => 'Faculty Loading', 'name' => 'faculty_loading.view',              'description' => 'View faculty loads and schedules'],
            ['module' => 'Faculty Loading', 'name' => 'faculty_loading.view_own',        'description' => 'Faculty: view own load and schedule'],
            ['module' => 'Faculty Loading', 'name' => 'faculty_loading.manage',          'description' => 'CID/AUH: assign subjects, schedules, classrooms'],
            ['module' => 'Faculty Loading', 'name' => 'faculty_loading.load_assignments','description' => 'Assign/revoke designation load assignments'],
            ['module' => 'Faculty Loading', 'name' => 'faculty_loading.approve',         'description' => 'Campus Director: approve overloads'],
            ['module' => 'Faculty Loading', 'name' => 'faculty_loading.reports',         'description' => 'View faculty load and schedule reports'],
            ['module' => 'Faculty Loading', 'name' => 'faculty_loading.subjects',        'description' => 'Manage subject catalog'],
            ['module' => 'Faculty Loading', 'name' => 'faculty_loading.classrooms',      'description' => 'Manage classroom catalog'],
            ['module' => 'Faculty Loading', 'name' => 'faculty_loading.school_year',     'description' => 'Manage school years and academic terms'],
            ['module' => 'Faculty Loading', 'name' => 'faculty_loading.setup',           'description' => 'Manage academic units and designation reference data'],
            ['module' => 'Faculty Loading', 'name' => 'faculty_loading.vacancies',       'description' => 'View and manage faculty vacancy slots'],
            ['module' => 'Faculty Loading', 'name' => 'faculty_loading.training',        'description' => 'Manage training and L&D records'],
            ['module' => 'Faculty Loading', 'name' => 'faculty_loading.training.verify', 'description' => 'Verify or reject training records'],

            // ── Payroll Upload (Cashier) ──────────────────────────────────────
            ['module' => 'Payroll', 'name' => 'payroll.upload',    'description' => 'Upload payroll Excel and parse into payslips'],
            ['module' => 'Payroll', 'name' => 'payroll.send',      'description' => 'Queue and send payslip emails to employees'],
            ['module' => 'Payroll', 'name' => 'payroll.view_all',  'description' => 'View all payroll batches and employee payslips'],
            ['module' => 'Payroll', 'name' => 'payroll.view_own',  'description' => 'View own payslip history'],

            // ── Issuances (Special Orders, Memos, Travel Orders, etc.) ───────
            ['module' => 'Issuances', 'name' => 'issuances.view',   'description' => 'View issuances addressed to the user or their office'],
            ['module' => 'Issuances', 'name' => 'issuances.manage', 'description' => 'Create, sign, release, and manage official issuances (OCD)'],

            // ── PPMP ──────────────────────────────────────────────────────────
            ['module' => 'PPMP', 'name' => 'ppmp.create',      'description' => 'Create and edit own unit PPMP'],
            ['module' => 'PPMP', 'name' => 'ppmp.submit',      'description' => 'Submit PPMP for review'],
            ['module' => 'PPMP', 'name' => 'ppmp.bac_review',  'description' => 'BAC/Procurement Officer: endorse or return PPMPs pending BAC review'],
            ['module' => 'PPMP', 'name' => 'ppmp.review',      'description' => 'View submitted PPMPs from all units'],
            ['module' => 'PPMP', 'name' => 'ppmp.approve',     'description' => 'Approve or return PPMPs'],
            ['module' => 'PPMP', 'name' => 'ppmp.consolidate', 'description' => 'Consolidate PPMPs into APP'],
            ['module' => 'PPMP', 'name' => 'ppmp.export',      'description' => 'Export PPMP/APP to Excel/PDF'],
            ['module' => 'PPMP', 'name' => 'ppmp.view_all',    'description' => 'View all PPMPs across units'],

            // ── Teacher Class Attendance (NFC Tap-In) ────────────────────────
            ['module' => 'Teacher Attendance', 'name' => 'class-attendance.view',   'description' => 'View teacher class attendance dashboard and history (CID Chief, AUH)'],
            ['module' => 'Teacher Attendance', 'name' => 'class-attendance.manage', 'description' => 'Override or correct teacher tap records (Admin only)'],

            // ── Org Structure ─────────────────────────────────────────────────
            ['module' => 'OrgStructure', 'name' => 'org.view',              'description' => 'View the organizational chart and unit details'],
            ['module' => 'OrgStructure', 'name' => 'org.view_all',          'description' => 'View the full org chart including inactive units'],
            ['module' => 'OrgStructure', 'name' => 'org.units.create',      'description' => 'Create new organizational units'],
            ['module' => 'OrgStructure', 'name' => 'org.units.update',      'description' => 'Edit existing organizational units'],
            ['module' => 'OrgStructure', 'name' => 'org.units.delete',      'description' => 'Delete / archive organizational units'],
            ['module' => 'OrgStructure', 'name' => 'org.units.manage',      'description' => 'Full CRUD management of organizational units'],
            ['module' => 'OrgStructure', 'name' => 'org.assign',            'description' => 'Assign or re-assign employees to organizational units'],
            ['module' => 'OrgStructure', 'name' => 'org.assign.manage',     'description' => 'Full management of employee unit assignments'],
            ['module' => 'OrgStructure', 'name' => 'org.heads.manage',      'description' => 'Designate and manage unit heads'],
            ['module' => 'OrgStructure', 'name' => 'org.versions.view',     'description' => 'View org structure version history'],
            ['module' => 'OrgStructure', 'name' => 'org.versions.manage',   'description' => 'Create, approve, and activate org structure versions'],
            ['module' => 'OrgStructure', 'name' => 'org.export',            'description' => 'Export the organizational chart (PDF, PNG, Excel)'],
            ['module' => 'OrgStructure', 'name' => 'org.reports',           'description' => 'Generate organizational structure reports'],

        ];

        foreach ($permissions as $data) {
            Permission::updateOrCreate(
                ['name' => $data['name']],
                ['module' => $data['module'], 'description' => $data['description']]
            );
        }

        $this->command->info('Permissions seeded: ' . count($permissions) . ' permissions upserted.');
    }
}
