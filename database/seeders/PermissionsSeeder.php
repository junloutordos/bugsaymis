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
            ['module' => 'Guidance', 'name' => 'guidance.view',      'description' => 'View guidance consultations'],
            ['module' => 'Guidance', 'name' => 'guidance.manage',    'description' => 'Manage guidance consultations & interventions'],

            // ── Procurement ───────────────────────────────────────────────────
            ['module' => 'Procurement','name' => 'procurement.view',   'description' => 'View procurement records'],
            ['module' => 'Procurement','name' => 'procurement.create', 'description' => 'Create procurement requests'],
            ['module' => 'Procurement','name' => 'procurement.approve','description' => 'Approve procurement requests'],

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

            // ── Chat ──────────────────────────────────────────────────────────
            ['module' => 'Chat',   'name' => 'chat.access',            'description' => 'Access the real-time messaging module'],

            // ── Faculty Loading ───────────────────────────────────────────────
            ['module' => 'Faculty Loading', 'name' => 'faculty_loading.view',        'description' => 'View faculty loads and schedules'],
            ['module' => 'Faculty Loading', 'name' => 'faculty_loading.view_own',    'description' => 'Faculty: view own load and schedule'],
            ['module' => 'Faculty Loading', 'name' => 'faculty_loading.manage',      'description' => 'CID/AUH: assign subjects, schedules, classrooms'],
            ['module' => 'Faculty Loading', 'name' => 'faculty_loading.approve',     'description' => 'Campus Director: approve overloads'],
            ['module' => 'Faculty Loading', 'name' => 'faculty_loading.reports',     'description' => 'View faculty load and schedule reports'],
            ['module' => 'Faculty Loading', 'name' => 'faculty_loading.subjects',    'description' => 'Manage subject catalog'],
            ['module' => 'Faculty Loading', 'name' => 'faculty_loading.classrooms',  'description' => 'Manage classroom catalog'],
            ['module' => 'Faculty Loading', 'name' => 'faculty_loading.school_year', 'description' => 'Manage school years and academic terms'],

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
