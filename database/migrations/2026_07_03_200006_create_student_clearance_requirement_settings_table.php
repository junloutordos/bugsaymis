<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_clearance_requirement_settings', function (Blueprint $table) {
            $table->id();
            $table->string('requirement_code')->unique();
            $table->string('requirement_label');
            $table->string('requirement_type')->default('office');
            $table->string('requirement_group')->default('administrative')->index();
            $table->unsignedBigInteger('assigned_user_id')->nullable()->index();
            $table->string('assigned_permission')->nullable()->index();
            $table->json('applies_grade_levels')->nullable();
            $table->boolean('intern_only')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        foreach ($this->defaults() as $row) {
            DB::table('student_clearance_requirement_settings')->updateOrInsert(
                ['requirement_code' => $row['requirement_code']],
                array_merge($row, [
                    'applies_grade_levels' => isset($row['applies_grade_levels']) ? json_encode($row['applies_grade_levels']) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_clearance_requirement_settings');
    }

    private function defaults(): array
    {
        return [
            ['requirement_code' => 'office:bio_lab', 'requirement_label' => 'Biology Laboratory', 'requirement_type' => 'office', 'requirement_group' => 'laboratory', 'assigned_permission' => 'students.clearance.sign', 'sort_order' => 500],
            ['requirement_code' => 'office:chem_lab', 'requirement_label' => 'Chemistry Laboratory', 'requirement_type' => 'office', 'requirement_group' => 'laboratory', 'assigned_permission' => 'students.clearance.sign', 'sort_order' => 501],
            ['requirement_code' => 'office:physics_lab', 'requirement_label' => 'Physics Laboratory', 'requirement_type' => 'office', 'requirement_group' => 'laboratory', 'assigned_permission' => 'students.clearance.sign', 'sort_order' => 502],
            ['requirement_code' => 'office:computer_lab', 'requirement_label' => 'Computer Laboratory', 'requirement_type' => 'office', 'requirement_group' => 'laboratory', 'assigned_permission' => 'students.clearance.sign', 'sort_order' => 503],
            ['requirement_code' => 'office:dental', 'requirement_label' => 'Dental', 'requirement_type' => 'office', 'requirement_group' => 'administrative', 'assigned_permission' => 'health.manage', 'sort_order' => 504],
            ['requirement_code' => 'office:medical', 'requirement_label' => 'Medical', 'requirement_type' => 'office', 'requirement_group' => 'administrative', 'assigned_permission' => 'health.manage', 'sort_order' => 505],
            ['requirement_code' => 'office:guidance', 'requirement_label' => 'Guidance', 'requirement_type' => 'office', 'requirement_group' => 'administrative', 'assigned_permission' => 'guidance.manage', 'sort_order' => 506],
            ['requirement_code' => 'office:library', 'requirement_label' => 'Library', 'requirement_type' => 'office', 'requirement_group' => 'administrative', 'assigned_permission' => 'library.manage', 'sort_order' => 507],
            ['requirement_code' => 'office:canteen', 'requirement_label' => 'Canteen', 'requirement_type' => 'office', 'requirement_group' => 'administrative', 'assigned_permission' => 'students.clearance.sign', 'sort_order' => 508],
            ['requirement_code' => 'office:supply_books', 'requirement_label' => 'Supply & Property - Books', 'requirement_type' => 'office', 'requirement_group' => 'administrative', 'assigned_permission' => 'supply.manage', 'sort_order' => 509],
            ['requirement_code' => 'office:supply_locker', 'requirement_label' => 'Supply & Property - Locker', 'requirement_type' => 'office', 'requirement_group' => 'administrative', 'assigned_permission' => 'property.manage', 'sort_order' => 510],
            ['requirement_code' => 'office:cashier', 'requirement_label' => 'Cashier', 'requirement_type' => 'office', 'requirement_group' => 'administrative', 'assigned_permission' => 'students.clearance.sign', 'sort_order' => 511],
            ['requirement_code' => 'office:discipline', 'requirement_label' => 'Discipline Officer', 'requirement_type' => 'office', 'requirement_group' => 'administrative', 'assigned_permission' => 'discipline.manage', 'sort_order' => 512],
            ['requirement_code' => 'office:ala_club', 'requirement_label' => 'ALA / Club Adviser', 'requirement_type' => 'office', 'requirement_group' => 'administrative', 'assigned_permission' => 'students.clearance.sign', 'sort_order' => 513],
            ['requirement_code' => 'office:sg_adviser', 'requirement_label' => 'SG Adviser', 'requirement_type' => 'office', 'requirement_group' => 'administrative', 'assigned_permission' => 'students.clearance.sign', 'sort_order' => 514],
            ['requirement_code' => 'office:residence_hall', 'requirement_label' => 'Residence Hall', 'requirement_type' => 'office', 'requirement_group' => 'administrative', 'assigned_permission' => 'rh.interns.manage', 'intern_only' => true, 'sort_order' => 515],
            ['requirement_code' => 'office:scale_adviser', 'requirement_label' => 'SCALE Adviser', 'requirement_type' => 'office', 'requirement_group' => 'administrative', 'assigned_permission' => 'students.clearance.sign', 'applies_grade_levels' => [11, 12], 'sort_order' => 516],
            ['requirement_code' => 'office:research_teacher', 'requirement_label' => 'Research Teacher', 'requirement_type' => 'office', 'requirement_group' => 'subject', 'assigned_permission' => 'students.clearance.subject-sign', 'applies_grade_levels' => [12], 'sort_order' => 517],
            ['requirement_code' => 'office:cid_chief', 'requirement_label' => 'Chief, Curriculum & Instruction Division', 'requirement_type' => 'office', 'requirement_group' => 'final', 'assigned_permission' => 'students.clearance.sign', 'applies_grade_levels' => [12], 'sort_order' => 518],
            ['requirement_code' => 'office:ssd_chief', 'requirement_label' => 'Chief, Student Services Division', 'requirement_type' => 'office', 'requirement_group' => 'final', 'assigned_permission' => 'students.clearance.sign', 'applies_grade_levels' => [12], 'sort_order' => 519],
            ['requirement_code' => 'office:campus_director', 'requirement_label' => 'Campus Director', 'requirement_type' => 'office', 'requirement_group' => 'final', 'assigned_permission' => 'students.clearance.admin', 'applies_grade_levels' => [12], 'sort_order' => 520],
            ['requirement_code' => 'office:section_adviser', 'requirement_label' => 'Section / Homeroom Adviser', 'requirement_type' => 'section_adviser', 'requirement_group' => 'final', 'sort_order' => 521],
            ['requirement_code' => 'office:registrar', 'requirement_label' => 'Registrar', 'requirement_type' => 'registrar', 'requirement_group' => 'final', 'assigned_permission' => 'students.clearance.registrar', 'sort_order' => 522],
        ];
    }
};
