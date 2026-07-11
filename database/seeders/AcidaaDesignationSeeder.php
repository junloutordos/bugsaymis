<?php

namespace Database\Seeders;

use App\Models\FacultyLoading\Designation;
use App\Models\FacultyLoading\DesignationCategory;
use Illuminate\Database\Seeder;

/**
 * Seeds the "Assistant CID Chief for Academic Affairs" (ACIDAA) designation
 * under the Supervisory category. The IPCR supervisor chain resolves the
 * ACIDAA holder through load_assignments carrying this designation
 * (IPCRWorkflowService::ACIDAA_DESIGNATION_CODE).
 *
 * Prod already has this row (designations.id=86, code ACIDAA) — this seeder
 * is a no-op there thanks to the code-based updateOrCreate; it exists so dev
 * and future environments get the same designation.
 */
class AcidaaDesignationSeeder extends Seeder
{
    public function run(): void
    {
        $sup = DesignationCategory::updateOrCreate(
            ['code' => 'SUPV'],
            [
                'name'        => 'Supervisory',
                'description' => 'Supervisory roles with high load units',
                'sort_order'  => 11,
                'is_active'   => true,
            ]
        );

        Designation::updateOrCreate(
            ['code' => 'ACIDAA'],
            [
                'designation_category_id' => $sup->id,
                'name'            => 'Assistant CID Chief for Academic Affairs',
                'load_units'      => 9,
                'assignment_type' => 'admin',
                'requires_unit'   => false,
                'max_holders'     => 1,
                'sort_order'      => 5,
                'is_active'       => true,
            ]
        );
    }
}
