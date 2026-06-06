<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    /**
     * Upsert descriptions into existing roles and create any missing ones.
     * Never deletes existing roles — safe to re-run.
     */
    public function run(): void
    {
        $roles = [
            // ── Core ──────────────────────────────────────────────────────────
            ['name' => 'Administrator',      'description' => 'Full system access. Bypasses all permission checks.'],
            ['name' => 'MIS',                'description' => 'Manages IT infrastructure, equipment, and job requests.'],
            ['name' => 'HR',                 'description' => 'Human Resource personnel. Manages attendance, PDS, and employees.'],
            ['name' => 'OCD',                'description' => 'Office/Unit Chief Director. Monitors unit-level operations.'],
            ['name' => 'DivisionChief',      'description' => 'Division Chief. Approves requests and monitors division performance.'],

            // ── Academic ──────────────────────────────────────────────────────
            ['name' => 'CID Chief',          'description' => 'Curriculum and Instruction Division Chief. Full access to Faculty Loading module.'],
            ['name' => 'Faculty',            'description' => 'Teaching staff with access to academic modules.'],
            ['name' => 'Staff',              'description' => 'Non-teaching staff with standard operational access.'],
            ['name' => 'Registrar',          'description' => 'Manages student records and registration.'],
            ['name' => 'Guidance',           'description' => 'Guidance counselor with access to consultation records.'],
            ['name' => 'Nurse',              'description' => 'Health personnel with access to clinic and health records.'],
            ['name' => 'Librarian',          'description' => 'Manages library collections, borrowings, and attendance.'],
            ['name' => 'Records',            'description' => 'Records officer. Manages document tracking.'],
            ['name' => 'InformationOfficer', 'description' => 'Manages public information and communications.'],
            ['name' => 'PMT',                'description' => 'Performance Management Team member.'],
            ['name' => 'GSU Head',           'description' => 'General Services Unit Head.'],
            ['name' => 'Dorm Manager',       'description' => 'Manages dormitory operations.'],

            // ── General Services ──────────────────────────────────────────────
            ['name' => 'FAD Chief',          'description' => 'Finance and Administrative Division Chief. Approves facility, work, and service requests.'],

            // ── HR & Payroll ──────────────────────────────────────────────────
            ['name' => 'Payroll Officer',    'description' => 'Processes, approves, and releases payroll runs.'],
            ['name' => 'Cashier',            'description' => 'Uploads payroll Excel workbooks and sends per-employee payslip emails.'],

            // ── Recruitment ───────────────────────────────────────────────────
            ['name' => 'Recruitment Officer','description' => 'Manages the full recruitment and selection process.'],
            ['name' => 'HRMPSB',             'description' => 'Human Resource Merit Promotion and Selection Board member. Evaluates, interviews, and ranks applicants.'],

            // ── Finance / Procurement ─────────────────────────────────────────
            ['name' => 'Budget Officer',      'description' => 'Verifies account codes and numbers Obligation Request Status (ORS) documents.'],
            ['name' => 'Bookkeeper',          'description' => 'Verifies completeness of ORS and Disbursement Voucher documents.'],
            ['name' => 'Accountant',          'description' => 'Reviews data correctness in ORS and Disbursement Voucher documents.'],
            ['name' => 'Procurement Officer', 'description' => 'Assigns official PR numbers and processes Purchase Requests.'],

            // ── Non-staff ─────────────────────────────────────────────────────
            ['name' => 'Student',            'description' => 'Student with limited read-only access.'],
            ['name' => 'Parent',             'description' => 'Parent/Guardian with limited access to student information.'],
        ];

        foreach ($roles as $data) {
            Role::updateOrCreate(
                ['name' => $data['name']],
                ['description' => $data['description']]
            );
        }

        $this->command->info('Roles seeded: ' . count($roles) . ' roles upserted.');
    }
}
