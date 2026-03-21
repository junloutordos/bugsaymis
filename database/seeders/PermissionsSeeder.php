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
            ['module' => 'Facilities','name' => 'facilities.view',   'description' => 'View facility requests'],
            ['module' => 'Facilities','name' => 'facilities.create', 'description' => 'Submit facility requests'],
            ['module' => 'Facilities','name' => 'facilities.manage', 'description' => 'Manage facility requests'],
            ['module' => 'Vehicles', 'name' => 'vehicles.view',      'description' => 'View vehicle requests'],
            ['module' => 'Vehicles', 'name' => 'vehicles.create',    'description' => 'Submit vehicle requests'],
            ['module' => 'Vehicles', 'name' => 'vehicles.manage',    'description' => 'Manage vehicle requests'],

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
