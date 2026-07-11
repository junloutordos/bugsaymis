<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $allPermissions = Permission::pluck('id')->toArray();

        // Helper: resolve role and sync a subset of permissions
        $assign = function (string $roleName, array $permissionNames) {
            $role = Role::where('name', $roleName)->first();
            if (! $role) return;
            $ids = Permission::whereIn('name', $permissionNames)->pluck('id')->toArray();
            $role->permissions()->syncWithoutDetaching($ids);
        };

        // ── Administrator — all permissions (SuperAdmin bypasses checks anyway) ─
        $admin = Role::where('name', 'Administrator')->first();
        if ($admin) {
            $admin->permissions()->sync($allPermissions);
        }

        // ── MIS ───────────────────────────────────────────────────────────────
        $assign('MIS', [
            'users.view',
            'it.requests.view', 'it.requests.manage',
            'it.equipment.view', 'it.equipment.manage',
            'atlas.modules.view',
            'atlas.watchtower.view', 'atlas.watchtower.manage',
            'facilities.view', 'facilities.manage',
            'vehicles.view', 'vehicles.manage',
            'documents.view',
            'reports.view', 'reports.export',
            'chat.access',
        ]);

        // ── SALN Committee (HRMPSB doubles as committee; also set on HR head) ──
        // All employee-type roles get saln.create + saln.submit.
        // HR gets view_all + file. Designated committee members get review + approve.
        $employeeRoles = [
            'HR', 'MIS', 'Payroll Officer', 'HRMPSB', 'Recruitment Officer',
            'DivisionChief', 'OCD', 'PMT', 'Faculty', 'Staff', 'Registrar',
            'Records', 'Librarian', 'Nurse', 'Guidance', 'GSU Head',
            'InformationOfficer', 'Dorm Manager',
        ];
        foreach ($employeeRoles as $roleName) {
            $assign($roleName, ['saln.create', 'saln.submit']);
        }

        // HR gets full filing & reporting access
        $assign('HR', ['saln.view_all', 'saln.file', 'saln.review', 'saln.approve']);

        // HRMPSB is the designated SALN review committee
        $assign('HRMPSB', ['saln.review', 'saln.approve']);

        // ── HR ────────────────────────────────────────────────────────────────
        $assign('HR', [
            'hr.view',
            'hr.employees.view', 'hr.employees.manage',
            'hr.attendance.view',
            'hr.pds.view', 'hr.pds.manage',
            'hr.schedule.view', 'hr.schedule.manage',
            'hr.gatepass.view', 'hr.gatepass.approve',
            'wfh.view', 'wfh.monitor',
            'hr.online-punch.record', 'hr.online-punch.monitor',
            'hr.face-enrollment.self', 'hr.face-enrollment.manage',
            'ipcr.view', 'ipcr.approve', 'ipcr.monitor', 'ipcr.admin',
            'accomplishments.view',
            'users.view',
            'reports.view', 'reports.export',
            // Recruitment — HR can view and manage the full pipeline
            'recruitment.view', 'recruitment.manage', 'recruitment.publish',
            'recruitment.evaluate', 'recruitment.rank', 'recruitment.approve',
            'recruitment.onboarding',
            // L&D — HR manages the full L&D cycle
            'lnd.view', 'lnd.create', 'lnd.edit', 'lnd.delete',
            'lnd.approve', 'lnd.evaluate',
            // Rewards — HR manages full PRAISE cycle
            'rewards.view', 'rewards.nominate', 'rewards.evaluate',
            'rewards.approve', 'rewards.manage',
            // Payroll & DTR module
            'payroll.view', 'payroll.process',
            'hr.dtr.view', 'hr.dtr.manage',
            'hr.biometric.manage',
            'dtr.view_own',
            'hr.leave.view', 'hr.leave.approve',
            'hr.leave.credits.view', 'hr.leave.credits.manage',
            'hr.leave.credits.service', 'hr.leave.credits.reports',
            'hr.employee.view', 'hr.employee.manage',
            'chat.access',
        ]);

        // ── HR Dashboard — comprehensive HR/Recruitment/PMS/L&D/SALN/Rewards overview ─
        // Each section is independently gated by its own existing module permission,
        // so granting page access here is safe even for roles missing some sections.
        foreach (['HR', 'OCD', 'DivisionChief', 'PMT', 'Recruitment Officer', 'HRMPSB'] as $roleName) {
            $assign($roleName, ['hr.dashboard.view']);
        }

        // ── Payroll Officer ───────────────────────────────────────────────────
        $assign('Payroll Officer', [
            'payroll.view', 'payroll.process', 'payroll.approve',
            'hr.dtr.view',
            'hr.leave.view',
            'hr.employee.view',
            'reports.view', 'reports.export',
            'chat.access',
        ]);

        // ── Cashier ───────────────────────────────────────────────────────────
        $assign('Cashier', [
            'payroll.upload', 'payroll.send', 'payroll.view_all', 'payroll.view_own',
            'chat.access',
        ]);

        // ── HRMPSB ───────────────────────────────────────────────────────────
        // Evaluate applicants, conduct interviews, and participate in ranking.
        // No manage/approve/publish — those stay with Recruitment Officer / HR.
        $assign('HRMPSB', [
            'recruitment.view',
            'recruitment.evaluate',
            'recruitment.rank',
            'hr.view',
            'hr.employees.view',
            'chat.access',
        ]);

        // ── Recruitment Officer ───────────────────────────────────────────────
        $assign('Recruitment Officer', [
            'recruitment.view',
            'recruitment.manage',
            'recruitment.publish',
            'recruitment.apply',
            'recruitment.evaluate',
            'recruitment.rank',
            'recruitment.approve',
            'recruitment.onboarding',
            'hr.view',
            'hr.employees.view',
            'reports.view', 'reports.export',
            'chat.access',
        ]);

        // ── DivisionChief ─────────────────────────────────────────────────────
        $assign('DivisionChief', [
            'hr.view',
            'hr.attendance.view',
            'hr.pds.view',
            'hr.gatepass.view', 'hr.gatepass.approve',
            'hr.schedule.view',
            'wfh.view', 'wfh.monitor',
            'hr.online-punch.record', 'hr.online-punch.monitor', 'hr.face-enrollment.self',
            'hr.dtr.view',
            'dtr.view_own',
            'hr.leave.view', 'hr.leave.approve',
            'hr.leave.credits.view', 'hr.leave.credits.reports',
            'hr.employee.view',
            'payroll.view',
            'ipcr.view', 'ipcr.approve', 'ipcr.monitor',
            'accomplishments.view',
            'facilities.view', 'facilities.create', 'facilities.dc-approve',
            'vehicles.view', 'vehicles.create', 'vehicles.dc-approve',
            'documents.view', 'documents.approve',
            'messengerial.view', 'messengerial.create', 'messengerial.dc-approve',
            'reports.view',
            // L&D — supervisors approve nominations, IDP, and submit behavior evals
            'lnd.view', 'lnd.approve', 'lnd.evaluate',
            // Rewards — supervisors can nominate and sit on evaluation/approval panels
            'rewards.view', 'rewards.nominate', 'rewards.evaluate', 'rewards.approve',
            'chat.access',
        ]);

        // ── OCD (Office/Unit Chief Director) ──────────────────────────────────
        $assign('OCD', [
            'hr.view',
            'hr.attendance.view',
            'hr.pds.view',
            'hr.gatepass.view', 'hr.gatepass.create',
            'wfh.view', 'wfh.monitor',
            'hr.online-punch.record', 'hr.online-punch.monitor', 'hr.face-enrollment.self',
            'dtr.view_own',
            'ipcr.view', 'ipcr.monitor', 'ipcr.admin',
            'accomplishments.view',
            'facilities.view', 'facilities.create',
            'vehicles.view', 'vehicles.create',
            'documents.view',
            'messengerial.view', 'messengerial.ocd-approve',
            'chat.access',
        ]);

        // ── PMT ───────────────────────────────────────────────────────────────
        $assign('PMT', [
            'ipcr.view', 'ipcr.approve', 'ipcr.monitor',
            'accomplishments.view',
            'reports.view', 'reports.export',
            'chat.access',
        ]);

        // ── Faculty Loading ───────────────────────────────────────────────────
        // CID Chief: manage loads and schedules, manage catalog, setup
        $assign('CID Chief', [
            'faculty_loading.view',
            'faculty_loading.manage',
            'faculty_loading.load_assignments',
            'faculty_loading.reports',
            'faculty_loading.subjects',
            'faculty_loading.classrooms',
            'faculty_loading.school_year',
            'faculty_loading.setup',
            'faculty_loading.vacancies',
            'faculty_loading.training',
            'faculty_loading.training.verify',
        ]);

        // Campus Director: approve overloads + view everything
        $assign('OCD', [
            'faculty_loading.view',
            'faculty_loading.approve',
            'faculty_loading.reports',
            'faculty_loading.vacancies',
            'faculty_loading.training',
        ]);

        // Faculty: view own load only
        $assign('Faculty', ['faculty_loading.view_own']);

        // ── Faculty ───────────────────────────────────────────────────────────
        $assign('Faculty', [
            'hr.view',
            'hr.pds.view',
            'hr.attendance.view',
            'hr.gatepass.view', 'hr.gatepass.create',
            'hr.schedule.view',
            'wfh.view', 'wfh.time-in', 'wfh.time-out',
            'wfh.accomplishments.create', 'wfh.accomplishments.delete',
            'hr.online-punch.record', 'hr.face-enrollment.self',
            'ipcr.view', 'ipcr.create', 'ipcr.update', 'ipcr.submit',
            'accomplishments.view', 'accomplishments.create',
            'accomplishments.update', 'accomplishments.delete',
            'it.requests.view', 'it.requests.create',
            'facilities.view', 'facilities.create',
            'vehicles.view', 'vehicles.create',
            'documents.view', 'documents.create',
            'messengerial.view', 'messengerial.create',
            'guidance.refer',
            'guidance.cumulative.view',
            'library.view',
            // L&D — employees view own trainings, submit TNA, manage own IDP
            'lnd.view', 'lnd.create', 'lnd.evaluate',
            // Rewards — employees can nominate peers and view own recognitions
            'rewards.view', 'rewards.nominate',
            // Payroll — view own payslip; file own leave; view own DTR
            'payroll.view',
            'hr.leave.view', 'hr.leave.file', 'hr.leave.credits.view',
            'dtr.view_own',
            'chat.access',
        ]);

        // ── Staff ─────────────────────────────────────────────────────────────
        $assign('Staff', [
            'hr.view',
            'hr.pds.view',
            'hr.attendance.view',
            'hr.gatepass.view', 'hr.gatepass.create',
            'hr.schedule.view',
            'wfh.view', 'wfh.time-in', 'wfh.time-out',
            'wfh.accomplishments.create', 'wfh.accomplishments.delete',
            'hr.online-punch.record', 'hr.face-enrollment.self',
            'ipcr.view', 'ipcr.create', 'ipcr.update', 'ipcr.submit',
            'accomplishments.view', 'accomplishments.create',
            'accomplishments.update', 'accomplishments.delete',
            'it.requests.view', 'it.requests.create',
            'facilities.view', 'facilities.create',
            'vehicles.view', 'vehicles.create',
            'documents.view', 'documents.create',
            'messengerial.view', 'messengerial.create',
            'guidance.refer',
            'library.view',
            // L&D — employees view own trainings, submit TNA, manage own IDP
            'lnd.view', 'lnd.create', 'lnd.evaluate',
            // Rewards — employees can nominate peers and view own recognitions
            'rewards.view', 'rewards.nominate',
            // Payroll — view own payslip; file own leave; view own DTR
            'payroll.view',
            'hr.leave.view', 'hr.leave.file', 'hr.leave.credits.view',
            'dtr.view_own',
            'chat.access',
        ]);

        // ── Registrar ─────────────────────────────────────────────────────────
        $assign('Registrar', [
            'hr.pds.view',
            'documents.view', 'documents.create', 'documents.update',
            'students.clearance.view', 'students.clearance.manage',
            'students.clearance.registrar', 'students.clearance.report',
            'reports.view',
            'chat.access',
        ]);

        // ── Records ───────────────────────────────────────────────────────────
        $assign('Records', [
            'documents.view', 'documents.create', 'documents.update',
            'documents.approve',
            'messengerial.view', 'messengerial.manage',
            'reports.view',
            'chat.access',
        ]);

        // ── Librarian ─────────────────────────────────────────────────────────
        $assign('Librarian', [
            'library.view', 'library.manage',
            'students.clearance.sign',
            'reports.view',
            'chat.access',
        ]);

        // ── Nurse ─────────────────────────────────────────────────────────────
        $assign('Nurse', [
            'health.view', 'health.manage',
            'students.health.view', 'students.health.manage',
            'students.clearance.sign',
            'reports.view',
            'chat.access',
        ]);

        // ── Guidance ──────────────────────────────────────────────────────────
        $assign('Guidance', [
            'guidance.view', 'guidance.refer', 'guidance.manage',
            'guidance.cumulative.view', 'guidance.cumulative.manage',
            'students.health.view', 'students.health.manage',
            'students.clearance.sign',
            'reports.view',
            'chat.access',
        ]);

        // ── Student Discipline (SDO) ──────────────────────────────────────────
        // Discipline Officers receive, review, and resolve cases.
        $assign('Student Discipline Officer', [
            'discipline.file', 'discipline.view', 'discipline.manage', 'discipline.report',
            'students.enrollment.view',
            'students.clearance.sign',
            'reports.view',
            'chat.access',
        ]);
        // CID Chief & Guidance can view discipline cases (oversight).
        $assign('CID Chief', ['discipline.view', 'discipline.report']);
        $assign('Guidance',  ['discipline.view']);
        // Any faculty/staff (and advisers/division chiefs) may file an Anecdotal Report.
        foreach (['Faculty', 'Staff', 'DivisionChief', 'CID Chief', 'OCD'] as $r) {
            $assign($r, ['discipline.file', 'discipline.view']);
        }

        // ── GSU Head ──────────────────────────────────────────────────────────
        $assign('GSU Head', [
            'facilities.view', 'facilities.manage',
            'lostfound.manage',
            'vehicles.view', 'vehicles.manage',
            'messengerial.view', 'messengerial.create',
            'procurement.view', 'procurement.create', 'procurement.approve',
            'reports.view',
            'chat.access',
        ]);

        // ── FAD Chief ─────────────────────────────────────────────────────────
        $assign('FAD Chief', [
            'facilities.view', 'facilities.fad-approve',
            'reports.view',
            'chat.access',
        ]);

        // ── InformationOfficer ────────────────────────────────────────────────
        $assign('InformationOfficer', [
            'documents.view', 'documents.create',
            'reports.view',
            'chat.access',
        ]);

        // ── Dorm Manager ──────────────────────────────────────────────────────
        $assign('Dorm Manager', [
            'facilities.view', 'facilities.manage',
            'reports.view',
            'chat.access',
        ]);

        // ── Activity Management System ─────────────────────────────────────────
        // All staff can create and manage their own activities
        $amsStaffRoles = [
            'MIS', 'Payroll Officer', 'HRMPSB', 'Recruitment Officer',
            'DivisionChief', 'OCD', 'PMT', 'CID Chief', 'Faculty', 'Staff', 'Registrar',
            'Records', 'Librarian', 'Nurse', 'Guidance', 'GSU Head', 'FAD Chief',
            'InformationOfficer', 'Dorm Manager',
        ];
        foreach ($amsStaffRoles as $roleName) {
            $assign($roleName, ['activities.manage']);
        }
        // HR sees all activities (read-only) and can also create their own
        $assign('HR', ['activities.manage', 'activities.view_all']);
        // HR also monitors the evaluation analytics dashboard (Administrator bypasses via isSuperAdmin())
        $assign('HR', ['activities.monitor']);

        // ── Student / Parent — very limited read-only ─────────────────────────
        $assign('Student', ['library.view', 'messengerial.view', 'messengerial.create']);
        $assign('Parent',  ['library.view', 'messengerial.view', 'messengerial.create']);

        // ── Class Records ─────────────────────────────────────────────────────
        $assign('CID Chief', ['class-records.view', 'class-records.manage', 'class-records.admin']);
        $assign('Faculty',   ['class-records.view', 'class-records.manage', 'students.clearance.subject-sign', 'students.clearance.adviser-review']);
        $assign('Staff',     ['class-records.view', 'students.clearance.sign']);
        $assign('CID Chief', ['students.clearance.view', 'students.clearance.sign', 'students.clearance.subject-sign', 'students.clearance.adviser-review']);

        // ── Teacher Class Attendance (NFC Tap-In) ─────────────────────────────
        // AUH scoping is handled at runtime by checking academic_units.head_user_id
        $assign('CID Chief', ['class-attendance.view', 'class-attendance.manage']);

        // ── CID Dashboard ─────────────────────────────────────────────────────
        $assign('CID Chief', ['cid.dashboard']);
        $assign('AUH',       ['cid.dashboard']);

        // ── CID Competitions & Winnings ───────────────────────────────────────
        $assign('CID Chief', ['cid.competitions.view', 'cid.competitions.manage']);
        $assign('AUH',       ['cid.competitions.view']);
        $assign('Faculty',   ['cid.competitions.view']);
        $assign('Staff',     ['cid.competitions.view']);

        // ── PPMP ──────────────────────────────────────────────────────────────
        $assign('Faculty', ['ppmp.create', 'ppmp.submit', 'ppmp.export']);
        $assign('Staff',   ['ppmp.create', 'ppmp.submit', 'ppmp.export']);
        $assign('DivisionChief', ['ppmp.review', 'ppmp.approve', 'ppmp.view_all', 'ppmp.export']);
        $assign('OCD',     ['ppmp.review', 'ppmp.approve', 'ppmp.consolidate', 'ppmp.view_all', 'ppmp.export']);
        $assign('HR',      ['ppmp.view_all', 'ppmp.export']);
        $assign('Procurement Officer', ['ppmp.bac_review', 'ppmp.view_all', 'ppmp.export']);
        $assign('FAD Chief',           ['ppmp.bac_review', 'ppmp.view_all', 'ppmp.export']);

        // ── Org Structure ─────────────────────────────────────────────────────
        $assign('HR', [
            'org.view', 'org.view_all',
            'org.units.create', 'org.units.update', 'org.units.delete', 'org.units.manage',
            'org.assign', 'org.assign.manage',
            'org.heads.manage',
            'org.versions.view', 'org.versions.manage',
            'org.export', 'org.reports',
        ]);
        foreach (['OCD', 'DivisionChief', 'PMT'] as $r) {
            $assign($r, ['org.view', 'org.view_all', 'org.export', 'org.reports', 'org.versions.view']);
        }
        $orgViewOnly = [
            'MIS', 'Payroll Officer', 'HRMPSB', 'Recruitment Officer',
            'Faculty', 'Staff', 'Registrar', 'Records', 'Librarian',
            'Nurse', 'Guidance', 'GSU Head', 'InformationOfficer',
            'Dorm Manager', 'Student', 'Parent',
        ];
        foreach ($orgViewOnly as $r) {
            $assign($r, ['org.view']);
        }

        // ── Issuances (Special Orders, Memorandums, Travel Orders, etc.) ──────
        // OCD + Administrator can create, sign, and release
        $assign('OCD', ['issuances.view', 'issuances.manage']);

        // All staff roles can VIEW issuances addressed to them or their office
        $issuanceViewRoles = [
            'Records', 'DivisionChief', 'Faculty', 'Staff', 'CID Chief', 'FAD Chief',
            'GSU Head', 'HR', 'HRMPSB', 'MIS', 'Registrar', 'Librarian', 'Nurse',
            'Guidance', 'InformationOfficer', 'Recruitment Officer', 'Payroll Officer',
            'Cashier', 'PMT', 'Dorm Manager',
        ];
        foreach ($issuanceViewRoles as $roleName) {
            $assign($roleName, ['issuances.view']);
        }

        // ── Knowledge Management (OED Issuances) ──────────────────────────────
        // OCD + Records can upload/manage; Administrator bypasses via isSuperAdmin()
        $assign('OCD',     ['km.view', 'km.manage']);
        $assign('Records', ['km.view', 'km.manage']);

        // Campus-wide view access for all other employee roles
        $kmViewRoles = [
            'DivisionChief', 'Faculty', 'Staff', 'CID Chief', 'FAD Chief',
            'GSU Head', 'HR', 'HRMPSB', 'MIS', 'Registrar', 'Librarian', 'Nurse',
            'Guidance', 'InformationOfficer', 'Recruitment Officer', 'Payroll Officer',
            'Cashier', 'PMT', 'Dorm Manager',
        ];
        foreach ($kmViewRoles as $roleName) {
            $assign($roleName, ['km.view']);
        }

        // ── Travel ────────────────────────────────────────────────────────────
        $travelUserRoles = [
            'DivisionChief', 'Faculty', 'Staff', 'CID Chief', 'FAD Chief',
            'GSU Head', 'HR', 'HRMPSB', 'MIS', 'Registrar', 'Librarian', 'Nurse',
            'Guidance', 'InformationOfficer', 'Recruitment Officer', 'Payroll Officer',
            'Cashier', 'PMT', 'Dorm Manager', 'Records',
        ];
        foreach ($travelUserRoles as $roleName) {
            $assign($roleName, ['travel.view', 'travel.create']);
        }
        $assign('DivisionChief', ['travel.approve.division']);
        $assign('FAD Chief', ['travel.review.fad', 'travel.finance']);
        $assign('OCD', ['travel.view', 'travel.create', 'travel.approve.ocd', 'travel.manage']);
        foreach (['Budget Officer', 'Bookkeeper', 'Accountant', 'Cashier'] as $roleName) {
            $assign($roleName, ['travel.view', 'travel.finance']);
        }

        // ── Purchase Requests (PR) ────────────────────────────────────────────
        $prBasic = ['procurement.view', 'procurement.create', 'procurement.pr.view', 'procurement.pr.create'];
        foreach (['Faculty', 'Staff', 'DivisionChief', 'OCD', 'HR', 'MIS',
                  'Registrar', 'Librarian', 'Nurse', 'Guidance', 'GSU Head',
                  'Records', 'InformationOfficer', 'PMT', 'Dorm Manager',
                  'Payroll Officer', 'Cashier', 'Recruitment Officer', 'HRMPSB',
                  'Budget Officer', 'Bookkeeper', 'Accountant', 'Procurement Officer'] as $r) {
            $assign($r, $prBasic);
        }
        $assign('DivisionChief',      ['procurement.pr.dc_sign']);
        $assign('Procurement Officer', ['procurement.pr.number']);
        $assign('OCD',                ['procurement.pr.ocd_sign']);
        $assign('Budget Officer',     ['procurement.pr.bo_initial']);
        $assign('FAD Chief', array_merge($prBasic, [
            'procurement.pr.dc_sign', 'procurement.pr.number',
            'procurement.pr.ocd_sign', 'procurement.pr.bo_initial',
        ]));

        // ── Obligation Request Status (ORS) ───────────────────────────────────
        $orsBasic = ['procurement.ors.view', 'procurement.ors.create'];
        foreach (['Faculty', 'Staff', 'DivisionChief', 'OCD',
                  'Budget Officer', 'Bookkeeper', 'Accountant', 'Procurement Officer', 'FAD Chief'] as $r) {
            $assign($r, $orsBasic);
        }
        $assign('DivisionChief',  ['procurement.ors.dc_sign']);
        $assign('Budget Officer', ['procurement.ors.budget_sign']);
        $assign('Bookkeeper',     ['procurement.ors.bookkeep']);
        $assign('Accountant',     ['procurement.ors.account']);
        $assign('OCD',            ['procurement.ors.ocd_sign']);
        $assign('FAD Chief', [
            'procurement.ors.dc_sign', 'procurement.ors.budget_sign',
            'procurement.ors.bookkeep', 'procurement.ors.account', 'procurement.ors.ocd_sign',
        ]);

        // ── Disbursement Vouchers (DV) ────────────────────────────────────────
        $dvBasic = ['procurement.dv.view', 'procurement.dv.create'];
        foreach (['Faculty', 'Staff', 'DivisionChief', 'OCD',
                  'Budget Officer', 'Bookkeeper', 'Accountant', 'Cashier',
                  'Procurement Officer', 'FAD Chief'] as $r) {
            $assign($r, $dvBasic);
        }
        $assign('GSU Head',   ['procurement.dv.view', 'procurement.dv.delivery']);
        $assign('Bookkeeper', ['procurement.dv.bookkeep']);
        $assign('Accountant', ['procurement.dv.account']);
        $assign('OCD',        ['procurement.dv.ocd_sign']);
        $assign('Cashier',    ['procurement.dv.cashier']);
        $assign('FAD Chief', [
            'procurement.dv.delivery', 'procurement.dv.bookkeep',
            'procurement.dv.account', 'procurement.dv.ocd_sign', 'procurement.dv.cashier',
        ]);

        // ── Purchase Orders (PO) ─────────────────────────────────────────────
        $poBasic = ['procurement.po.view', 'procurement.po.create'];
        foreach (['Faculty', 'Staff', 'DivisionChief', 'OCD',
                  'Budget Officer', 'Bookkeeper', 'Accountant', 'Procurement Officer', 'FAD Chief'] as $r) {
            $assign($r, $poBasic);
        }
        $assign('Procurement Officer', ['procurement.po.review']);
        $assign('OCD',                 ['procurement.po.sign']);
        $assign('FAD Chief',           ['procurement.po.review', 'procurement.po.sign']);

        // ── Request for Quotation (RFQ) ───────────────────────────────────────
        $rfqView = ['procurement.rfq.view'];
        foreach (['Faculty', 'Staff', 'DivisionChief', 'OCD',
                  'Budget Officer', 'Bookkeeper', 'Accountant',
                  'Procurement Officer', 'FAD Chief'] as $r) {
            $assign($r, $rfqView);
        }
        $assign('Procurement Officer', [
            'procurement.rfq.create', 'procurement.rfq.upload',
            'procurement.rfq.evaluate', 'procurement.rfq.award',
        ]);
        $assign('FAD Chief', [
            'procurement.rfq.create', 'procurement.rfq.upload',
            'procurement.rfq.evaluate', 'procurement.rfq.award',
        ]);

        // ── Supply & Property ─────────────────────────────────────────────────
        $supplyView = ['supply.view'];
        foreach (['Faculty', 'Staff', 'DivisionChief', 'OCD',
                  'Budget Officer', 'Bookkeeper', 'Accountant',
                  'Procurement Officer', 'FAD Chief'] as $r) {
            $assign($r, $supplyView);
        }
        $assign('Procurement Officer', ['supply.receive', 'supply.issue']);
        $assign('FAD Chief', ['supply.receive', 'supply.issue', 'supply.manage']);

        // ── Property ─────────────────────────────────────────────────────────
        $propertyView = ['property.view', 'work-orders.view'];
        foreach (['Faculty', 'Staff', 'DivisionChief', 'OCD',
                  'Budget Officer', 'Bookkeeper', 'Accountant',
                  'Procurement Officer', 'FAD Chief'] as $r) {
            $assign($r, $propertyView);
        }
        $assign('Procurement Officer', ['property.transfer', 'property.reports', 'work-orders.manage']);
        $assign('FAD Chief', ['property.manage', 'property.transfer', 'property.reports', 'property.dispose', 'work-orders.manage']);

        $this->command->info('Role permissions assigned successfully.');
    }
}
