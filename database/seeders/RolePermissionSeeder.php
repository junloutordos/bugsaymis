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
            'facilities.view', 'facilities.manage',
            'vehicles.view', 'vehicles.manage',
            'documents.view',
            'reports.view', 'reports.export',
        ]);

        // ── HR ────────────────────────────────────────────────────────────────
        $assign('HR', [
            'hr.view',
            'hr.employees.view', 'hr.employees.manage',
            'hr.attendance.view',
            'hr.pds.view', 'hr.pds.manage',
            'hr.schedule.view', 'hr.schedule.manage',
            'hr.gatepass.view', 'hr.gatepass.approve',
            'wfh.view', 'wfh.monitor',
            'ipcr.view', 'ipcr.approve', 'ipcr.monitor',
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
            'hr.leave.view', 'hr.leave.approve',
            'hr.employee.view', 'hr.employee.manage',
        ]);

        // ── Payroll Officer ───────────────────────────────────────────────────
        $assign('Payroll Officer', [
            'payroll.view', 'payroll.process', 'payroll.approve',
            'hr.dtr.view',
            'hr.leave.view',
            'hr.employee.view',
            'reports.view', 'reports.export',
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
        ]);

        // ── DivisionChief ─────────────────────────────────────────────────────
        $assign('DivisionChief', [
            'hr.view',
            'hr.attendance.view',
            'hr.pds.view',
            'hr.gatepass.view', 'hr.gatepass.approve',
            'hr.schedule.view',
            'wfh.view', 'wfh.monitor',
            'hr.dtr.view',
            'hr.leave.view', 'hr.leave.approve',
            'hr.employee.view',
            'payroll.view',
            'ipcr.view', 'ipcr.approve', 'ipcr.monitor',
            'accomplishments.view',
            'facilities.view', 'facilities.create',
            'vehicles.view', 'vehicles.create',
            'documents.view', 'documents.approve',
            'reports.view',
            // L&D — supervisors approve nominations, IDP, and submit behavior evals
            'lnd.view', 'lnd.approve', 'lnd.evaluate',
            // Rewards — supervisors can nominate and sit on evaluation/approval panels
            'rewards.view', 'rewards.nominate', 'rewards.evaluate', 'rewards.approve',
        ]);

        // ── OCD (Office/Unit Chief Director) ──────────────────────────────────
        $assign('OCD', [
            'hr.view',
            'hr.attendance.view',
            'hr.pds.view',
            'hr.gatepass.view', 'hr.gatepass.create',
            'wfh.view', 'wfh.monitor',
            'ipcr.view', 'ipcr.monitor',
            'accomplishments.view',
            'facilities.view', 'facilities.create',
            'vehicles.view', 'vehicles.create',
            'documents.view',
        ]);

        // ── PMT ───────────────────────────────────────────────────────────────
        $assign('PMT', [
            'ipcr.view', 'ipcr.approve', 'ipcr.monitor',
            'accomplishments.view',
            'reports.view', 'reports.export',
        ]);

        // ── Faculty ───────────────────────────────────────────────────────────
        $assign('Faculty', [
            'hr.view',
            'hr.pds.view',
            'hr.attendance.view',
            'hr.gatepass.view', 'hr.gatepass.create',
            'hr.schedule.view',
            'wfh.view', 'wfh.time-in', 'wfh.time-out',
            'wfh.accomplishments.create', 'wfh.accomplishments.delete',
            'ipcr.view', 'ipcr.create', 'ipcr.update', 'ipcr.submit',
            'accomplishments.view', 'accomplishments.create',
            'accomplishments.update', 'accomplishments.delete',
            'it.requests.view', 'it.requests.create',
            'facilities.view', 'facilities.create',
            'vehicles.view', 'vehicles.create',
            'documents.view', 'documents.create',
            'library.view',
            // L&D — employees view own trainings, submit TNA, manage own IDP
            'lnd.view', 'lnd.create', 'lnd.evaluate',
            // Rewards — employees can nominate peers and view own recognitions
            'rewards.view', 'rewards.nominate',
            // Payroll — view own payslip; file own leave
            'payroll.view',
            'hr.leave.file',
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
            'ipcr.view', 'ipcr.create', 'ipcr.update', 'ipcr.submit',
            'accomplishments.view', 'accomplishments.create',
            'accomplishments.update', 'accomplishments.delete',
            'it.requests.view', 'it.requests.create',
            'facilities.view', 'facilities.create',
            'vehicles.view', 'vehicles.create',
            'documents.view', 'documents.create',
            'library.view',
            // L&D — employees view own trainings, submit TNA, manage own IDP
            'lnd.view', 'lnd.create', 'lnd.evaluate',
            // Rewards — employees can nominate peers and view own recognitions
            'rewards.view', 'rewards.nominate',
            // Payroll — view own payslip; file own leave
            'payroll.view',
            'hr.leave.file',
        ]);

        // ── Registrar ─────────────────────────────────────────────────────────
        $assign('Registrar', [
            'hr.pds.view',
            'documents.view', 'documents.create', 'documents.update',
            'reports.view',
        ]);

        // ── Records ───────────────────────────────────────────────────────────
        $assign('Records', [
            'documents.view', 'documents.create', 'documents.update',
            'documents.approve',
            'reports.view',
        ]);

        // ── Librarian ─────────────────────────────────────────────────────────
        $assign('Librarian', [
            'library.view', 'library.manage',
            'reports.view',
        ]);

        // ── Nurse ─────────────────────────────────────────────────────────────
        $assign('Nurse', [
            'health.view', 'health.manage',
            'reports.view',
        ]);

        // ── Guidance ──────────────────────────────────────────────────────────
        $assign('Guidance', [
            'health.view',
            'reports.view',
        ]);

        // ── GSU Head ──────────────────────────────────────────────────────────
        $assign('GSU Head', [
            'facilities.view', 'facilities.manage',
            'vehicles.view', 'vehicles.manage',
            'procurement.view', 'procurement.create', 'procurement.approve',
            'reports.view',
        ]);

        // ── InformationOfficer ────────────────────────────────────────────────
        $assign('InformationOfficer', [
            'documents.view', 'documents.create',
            'reports.view',
        ]);

        // ── Dorm Manager ──────────────────────────────────────────────────────
        $assign('Dorm Manager', [
            'facilities.view', 'facilities.manage',
            'reports.view',
        ]);

        // ── Student / Parent — very limited read-only ─────────────────────────
        $assign('Student', ['library.view']);
        $assign('Parent',  ['library.view']);

        $this->command->info('Role permissions assigned successfully.');
    }
}
