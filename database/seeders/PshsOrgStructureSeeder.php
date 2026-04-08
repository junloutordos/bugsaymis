<?php

namespace Database\Seeders;

use App\Models\OrganizationalUnit;
use Illuminate\Database\Seeder;

/**
 * Seeds the official PSHS-CRC organizational hierarchy.
 * Uses firstOrCreate so it is safe to run multiple times.
 *
 * Hierarchy (abridged):
 *   PSHS-CRC (institution)
 *     ├── Office of the Campus Director (office)
 *     │     └── Records Section (section)
 *     ├── Academic Division (division)
 *     │     ├── Science Department (department)
 *     │     ├── Mathematics Department (department)
 *     │     ├── Social Sciences Department (department)
 *     │     ├── Filipino & Humanities Department (department)
 *     │     ├── English & Communication Arts Department (department)
 *     │     └── Physical Education & Health Department (department)
 *     ├── Student Affairs & Services Division (division)
 *     │     ├── Guidance Services (office)
 *     │     ├── Dormitory Management Office (office)
 *     │     └── Library Services (office)
 *     ├── Research & Development Division (division)
 *     ├── Administrative Division (division)
 *     │     ├── Finance Section (section)
 *     │     ├── Human Resource Section (section)
 *     │     ├── General Services Section (section)
 *     │     └── MIS Section (section)
 *     └── Health & Wellness Center (office)
 */
class PshsOrgStructureSeeder extends Seeder
{
    public function run(): void
    {
        // ── Root institution ───────────────────────────────────────────────────
        $campus = $this->create([
            'code'        => 'PSHS-CRC',
            'name'        => 'Philippine Science High School – Caraga Regional Campus',
            'short_name'  => 'PSHS-CRC',
            'type'        => 'institution',
            'parent_id'   => null,
            'order_index' => 0,
            'description' => 'State-run science high school under DOST, Caraga Region.',
            'legal_basis' => 'R.A. 3661 as amended by R.A. 8496',
            'is_active'   => true,
        ]);

        // ── Level 1 — major divisions & offices ───────────────────────────────
        $ocd = $this->create([
            'code'        => 'OCD',
            'name'        => 'Office of the Campus Director',
            'short_name'  => 'OCD',
            'type'        => 'office',
            'parent_id'   => $campus->id,
            'order_index' => 0,
            'is_active'   => true,
        ]);

        $acad = $this->create([
            'code'        => 'ACAD-DIV',
            'name'        => 'Academic Division',
            'short_name'  => 'ACAD',
            'type'        => 'division',
            'parent_id'   => $campus->id,
            'order_index' => 1,
            'mandate'     => 'Oversees all academic programs, curriculum, and faculty.',
            'is_active'   => true,
        ]);

        $sas = $this->create([
            'code'        => 'SAS-DIV',
            'name'        => 'Student Affairs and Services Division',
            'short_name'  => 'SAS',
            'type'        => 'division',
            'parent_id'   => $campus->id,
            'order_index' => 2,
            'mandate'     => 'Provides holistic student support services.',
            'is_active'   => true,
        ]);

        $rd = $this->create([
            'code'        => 'RD-DIV',
            'name'        => 'Research and Development Division',
            'short_name'  => 'R&D',
            'type'        => 'division',
            'parent_id'   => $campus->id,
            'order_index' => 3,
            'mandate'     => 'Manages student research programs and science innovation.',
            'is_active'   => true,
        ]);

        $admin = $this->create([
            'code'        => 'ADMIN-DIV',
            'name'        => 'Administrative Division',
            'short_name'  => 'ADMIN',
            'type'        => 'division',
            'parent_id'   => $campus->id,
            'order_index' => 4,
            'mandate'     => 'Manages finance, HR, general services, and MIS functions.',
            'is_active'   => true,
        ]);

        $health = $this->create([
            'code'        => 'HWC',
            'name'        => 'Health and Wellness Center',
            'short_name'  => 'HWC',
            'type'        => 'office',
            'parent_id'   => $campus->id,
            'order_index' => 5,
            'mandate'     => 'Provides medical and health services to students and staff.',
            'is_active'   => true,
        ]);

        // ── OCD children ──────────────────────────────────────────────────────
        $this->create([
            'code'        => 'RECORDS',
            'name'        => 'Records Section',
            'short_name'  => 'Records',
            'type'        => 'section',
            'parent_id'   => $ocd->id,
            'order_index' => 0,
            'is_active'   => true,
        ]);

        // ── Academic departments ───────────────────────────────────────────────
        $departments = [
            ['SCIENCE-DEPT',  'Science Department',                         'Science',    0],
            ['MATH-DEPT',     'Mathematics Department',                     'Math',       1],
            ['SS-DEPT',       'Social Sciences Department',                  'SocSci',     2],
            ['FIL-DEPT',      'Filipino and Humanities Department',          'Filipino',   3],
            ['ENG-DEPT',      'English and Communication Arts Department',   'EngComm',    4],
            ['PE-DEPT',       'Physical Education and Health Department',    'PE&Health',  5],
        ];
        foreach ($departments as [$code, $name, $short, $order]) {
            $this->create([
                'code'        => $code,
                'name'        => $name,
                'short_name'  => $short,
                'type'        => 'department',
                'parent_id'   => $acad->id,
                'order_index' => $order,
                'is_active'   => true,
            ]);
        }

        // ── SAS offices ───────────────────────────────────────────────────────
        $this->create([
            'code'        => 'GUIDANCE',
            'name'        => 'Guidance and Counseling Services',
            'short_name'  => 'Guidance',
            'type'        => 'office',
            'parent_id'   => $sas->id,
            'order_index' => 0,
            'is_active'   => true,
        ]);
        $this->create([
            'code'        => 'DORMITORY',
            'name'        => 'Dormitory Management Office',
            'short_name'  => 'Dorm',
            'type'        => 'office',
            'parent_id'   => $sas->id,
            'order_index' => 1,
            'is_active'   => true,
        ]);
        $this->create([
            'code'        => 'LIBRARY',
            'name'        => 'Library Services',
            'short_name'  => 'Library',
            'type'        => 'office',
            'parent_id'   => $sas->id,
            'order_index' => 2,
            'is_active'   => true,
        ]);

        // ── Admin sections ────────────────────────────────────────────────────
        $sections = [
            ['FINANCE',   'Finance Section',          'Finance',   0],
            ['HR-SECT',   'Human Resource Section',   'HR',        1],
            ['GSS',       'General Services Section', 'GSS',       2],
            ['MIS-SECT',  'MIS Section',              'MIS',       3],
        ];
        foreach ($sections as [$code, $name, $short, $order]) {
            $this->create([
                'code'        => $code,
                'name'        => $name,
                'short_name'  => $short,
                'type'        => 'section',
                'parent_id'   => $admin->id,
                'order_index' => $order,
                'is_active'   => true,
            ]);
        }

        // ── Standing committees ───────────────────────────────────────────────
        $committees = [
            ['HRMPSB',      'Human Resource Merit Promotion and Selection Board',  'HRMPSB',      0],
            ['BAC',         'Bids and Awards Committee',                            'BAC',          1],
            ['PMT-COMM',    'Planning and Management Team',                         'PMT',          2],
            ['INNO-COMM',   'Innovation and Technology Committee',                  'InnoTech',     3],
            ['SAFE-COMM',   'Safety and Disaster Risk Reduction Committee',         'DRRMC',        4],
        ];
        foreach ($committees as [$code, $name, $short, $order]) {
            $this->create([
                'code'        => $code,
                'name'        => $name,
                'short_name'  => $short,
                'type'        => 'committee',
                'parent_id'   => $campus->id,
                'order_index' => 10 + $order,   // after main divisions
                'is_active'   => true,
            ]);
        }
    }

    private function create(array $data): OrganizationalUnit
    {
        return OrganizationalUnit::firstOrCreate(
            ['code' => $data['code']],
            $data
        );
    }
}
