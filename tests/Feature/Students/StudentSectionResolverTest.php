<?php

namespace Tests\Feature\Students;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\Registrar\StudentEnrollment;
use App\Services\StudentSectionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionProperty;
use Tests\TestCase;

class StudentSectionResolverTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $currentSy;
    private SchoolYear $oldSy;
    private Section $currentSection;
    private Section $oldSection;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset the resolver's memoized current-school-year id between tests.
        $prop = new ReflectionProperty(StudentSectionResolver::class, 'currentSchoolYearId');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        $this->oldSy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-07-01', 'end_date' => '2026-06-30',
            'is_current' => false, 'status' => 'active',
        ]);
        $this->currentSy = SchoolYear::create([
            'name' => '2026-2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30',
            'is_current' => true, 'status' => 'active',
        ]);

        $this->oldSection = Section::create([
            'levelid' => 7, 'sectionname' => 'OldSection', 'syid' => 2025,
            'school_year_id' => $this->oldSy->id, 'capacity' => 30, 'is_active' => true,
        ]);
        $this->currentSection = Section::create([
            'levelid' => 8, 'sectionname' => 'NewSection', 'syid' => 2026,
            'school_year_id' => $this->currentSy->id, 'capacity' => 30, 'is_active' => true,
        ]);
    }

    public function test_prefers_current_school_year_enrollment_over_stale_legacy_mirror(): void
    {
        $studentId = 5001;

        // Stale legacy mirror row pointing at last year's section (higher id — the
        // old orderByDesc('id') logic would have picked this).
        DB::table('section_students')->insert([
            'studentid' => $studentId, 'levelid' => 7, 'sectionid' => $this->oldSection->id, 'syid' => 2025,
        ]);

        // Authoritative current-year enrollment in the new section/grade.
        StudentEnrollment::create([
            'student_id' => $studentId, 'school_year_id' => $this->currentSy->id,
            'section_id' => $this->currentSection->id, 'grade_level' => 8,
            'enrollment_type' => 'returning', 'status' => 'enrolled', 'enrollment_date' => '2026-07-20',
        ]);

        $row = (new StudentSectionResolver())->latestForStudent($studentId);

        $this->assertNotNull($row);
        $this->assertSame(8, (int) $row->levelid);
        $this->assertSame($this->currentSection->id, (int) $row->sectionid);

        $this->assertSame('Grade 8 NewSection', (new StudentSectionResolver())->gradeSectionString($studentId));
    }

    public function test_falls_back_to_legacy_mirror_when_no_current_enrollment(): void
    {
        $studentId = 5002; // no student_enrollments row at all

        DB::table('section_students')->insert([
            'studentid' => $studentId, 'levelid' => 7, 'sectionid' => $this->oldSection->id, 'syid' => 2025,
        ]);

        $row = (new StudentSectionResolver())->latestForStudent($studentId);

        $this->assertNotNull($row);
        $this->assertSame(7, (int) $row->levelid);
        $this->assertSame($this->oldSection->id, (int) $row->sectionid);
    }

    public function test_returns_grade_without_section_for_enrolled_unassigned_student(): void
    {
        $studentId = 5003; // e.g. a Grade 7 enrolled but not yet section-assigned

        StudentEnrollment::create([
            'student_id' => $studentId, 'school_year_id' => $this->currentSy->id,
            'section_id' => null, 'grade_level' => 7,
            'enrollment_type' => 'new', 'status' => 'enrolled', 'enrollment_date' => '2026-07-20',
        ]);

        $resolver = new StudentSectionResolver();
        $row = $resolver->latestForStudent($studentId);

        $this->assertNotNull($row);
        $this->assertSame(7, (int) $row->levelid);
        $this->assertNull($row->sectionid);
        $this->assertSame('Grade 7', $resolver->gradeSectionString($studentId));
    }

    public function test_returns_null_when_student_is_completely_unknown(): void
    {
        $this->assertNull((new StudentSectionResolver())->latestForStudent(999999));
    }
}
