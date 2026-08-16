<?php

namespace Tests\Unit\SPMS;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\SPMS\FiscalPeriod;
use App\Models\SPMS\Ipcr;
use App\Models\User;
use App\Services\SPMS\IPCRTargetGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IPCRTargetGenerationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeLoadAssignment(User $user, SchoolYear $schoolYear, string $assignmentType, float $units): LoadAssignment
    {
        $term = AcademicTerm::firstOrCreate(
            ['school_year_id' => $schoolYear->id, 'term_type' => '1st_semester'],
            ['name' => '1st Semester']
        );

        $facultyLoad = FacultyLoad::firstOrCreate(
            ['user_id' => $user->id, 'academic_term_id' => $term->id],
            ['school_year_id' => $schoolYear->id]
        );

        return LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id,
            'user_id' => $user->id,
            'school_year_id' => $schoolYear->id,
            'academic_term_id' => $term->id,
            'assignment_type' => $assignmentType,
            'load_units' => $units,
            'description' => ucfirst($assignmentType).' assignment',
        ]);
    }

    public function test_generates_one_core_target_per_distinct_assignment_type(): void
    {
        $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
        $user = User::factory()->create();
        $fiscalPeriod = FiscalPeriod::factory()->create(['school_year_id' => $schoolYear->id]);
        $ipcr = Ipcr::factory()->create(['user_id' => $user->id, 'fiscal_period_id' => $fiscalPeriod->id]);

        $this->makeLoadAssignment($user, $schoolYear, 'teaching', 6);
        $this->makeLoadAssignment($user, $schoolYear, 'research', 2);

        $result = (new IPCRTargetGenerationService())->generate($ipcr);

        $this->assertSame(2, $result['attached']);
        $this->assertCount(2, $ipcr->fresh()->targets);
    }

    public function test_never_clobbers_an_existing_target(): void
    {
        $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
        $user = User::factory()->create();
        $fiscalPeriod = FiscalPeriod::factory()->create(['school_year_id' => $schoolYear->id]);
        $ipcr = Ipcr::factory()->create(['user_id' => $user->id, 'fiscal_period_id' => $fiscalPeriod->id]);
        $this->makeLoadAssignment($user, $schoolYear, 'teaching', 6);

        $service = new IPCRTargetGenerationService();
        $service->generate($ipcr);
        $ipcr->fresh()->targets->first()->update(['target' => 'manually edited target']);

        $result = $service->generate($ipcr);

        $this->assertSame(0, $result['attached']);
        $this->assertSame('manually edited target', $ipcr->fresh()->targets->first()->target);
    }
}
