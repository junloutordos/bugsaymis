<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Call your roles seeder
        $this->call(InitialRolesTableSeeder::class);

        // RBAC seeders
        $this->call(RolesSeeder::class);
        $this->call(PermissionsSeeder::class);
        $this->call(RolePermissionSeeder::class);

        // Ensure Administrator role exists
        $adminRole = Role::firstOrCreate(['name' => 'Administrator']);

        // Create Administrator user if not exists
        $adminUser = User::firstOrCreate(
            ['email' => 'jtordos@crc.pshs.edu.ph'],
            [
                'name' => 'Junlou R. Tordos',
                'password' => Hash::make('password123'), // 🔐 change later
                'role_id' => $adminRole->id,
            ]
        );

        // Ensure Administrator role is in the pivot (idempotent)
        $adminUser->roles()->syncWithoutDetaching([$adminRole->id]);

        // ✅ Call the IPCR Weight Seeder
        $this->call(IPCRWeightSeeder::class);
        // Seed sample vehicle requests (optional)
        $this->call(\Database\Seeders\VehicleRequestSeeder::class);
        // Seed document types (ARTA/CSC-based)
        $this->call(DocumentTypeSeeder::class);
        // Seed IPCR rating periods
        $this->call(IPCRRatingPeriodSeeder::class);

        // Recruitment & Selection Module
        $this->call(RecruitmentTypeSeeder::class);
        $this->call(RecruitmentCriteriaSeeder::class);
        $this->call(RecruitmentOnboardingSeeder::class);
        $this->call(ApplicationRequirementsSeeder::class);
        $this->call(SalaryGradeSeeder::class);

        // HR & Payroll Module
        $this->call(LeaveTypesSeeder::class);
        $this->call(HolidaysSeeder::class);
        $this->call(AllowanceTypesSeeder::class);
        $this->call(PayrollPermissionsSeeder::class);

        // Organizational Structure Module
        $this->call(OrgStructurePermissionsSeeder::class);
        $this->call(PshsOrgStructureSeeder::class);

        // ── Faculty Loading Module ─────────────────────────────────────────────
        // Order matters: SchoolYear before Sections (FK dependency)
        $this->call(SchoolYearSeeder::class);       // school_years + academic_terms
        $this->call(SubjectSeeder::class);           // subjects catalog (44+ PSHS subjects)
        $this->call(ClassroomSeeder::class);         // classrooms (labs, lecture halls, etc.)
        $this->call(CommitteeSeeder::class);         // institutional committees
        $this->call(SstPositionSeeder::class);       // SST I–V position reference data
        $this->call(SalaryScheduleSeeder::class);    // DBM SSL V 2023 salary schedule (SG 1–33)
        $this->call(SectionSeeder::class);           // student sections (requires school year)

        // ── ICT Agent Module ────────────────────────────────────────────────────
        $this->call(IctRemediationRuleSeeder::class); // self-healing rules, seeded inert (auto_execute=false)
    }
}
