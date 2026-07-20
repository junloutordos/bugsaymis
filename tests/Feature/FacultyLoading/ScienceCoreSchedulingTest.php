<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use App\Services\FacultyLoading\DeterministicSchedulingService;
use App\Services\FacultyLoading\ScienceCoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Science Core subjects (e.g. Biology/Chemistry/Physics Level 2) must all be
 * placed at the exact same shared day+period, cutting across every homeroom
 * in the grade — the whole reason they exist instead of being modeled as
 * regular per-homeroom subjects. These tests exercise the real generate()
 * pipeline end-to-end rather than reflecting into private internals, since
 * the behavior being verified is the interaction between
 * placeScienceCoreGroups() (grouped placement + grade-wide reservation) and
 * the normal per-subject placement loop that runs after it.
 */
class ScienceCoreSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $sy;

    private AcademicTerm $term;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sy = SchoolYear::create([
            'name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-05-31', 'is_current' => true,
        ]);
        $this->term = AcademicTerm::create([
            'school_year_id' => $this->sy->id, 'name' => 'Term 1', 'term_type' => 'quarter',
            'start_date' => '2026-08-01', 'end_date' => '2026-10-31', 'is_current' => true,
        ]);
    }

    public function test_science_core_group_lands_every_class_on_the_same_shared_slot(): void
    {
        [$mars, $mercury] = [
            Section::create(['levelid' => 11, 'sectionname' => 'Mars', 'capacity' => 30, 'school_year_id' => $this->sy->id, 'syid' => $this->sy->id, 'is_active' => true]),
            Section::create(['levelid' => 11, 'sectionname' => 'Mercury', 'capacity' => 30, 'school_year_id' => $this->sy->id, 'syid' => $this->sy->id, 'is_active' => true]),
        ];

        $bioTeacher  = User::factory()->create();
        $chemTeacher = User::factory()->create();

        $bio  = $this->makeScienceCoreSubject('BIO-TEST-G11', 'Biology Test Level 2');
        $chem = $this->makeScienceCoreSubject('CHEM-TEST-G11', 'Chemistry Test Level 2');

        $bioSection  = Section::create(['levelid' => 11, 'sectionname' => ScienceCoreService::SECTION_PREFIX . 'BIO-TEST-G11-1', 'capacity' => 30, 'school_year_id' => $this->sy->id, 'syid' => $this->sy->id, 'is_active' => true]);
        $chemSection = Section::create(['levelid' => 11, 'sectionname' => ScienceCoreService::SECTION_PREFIX . 'CHEM-TEST-G11-1', 'capacity' => 30, 'school_year_id' => $this->sy->id, 'syid' => $this->sy->id, 'is_active' => true]);

        LoadAssignment::create($this->teachingLoad($bio->id, $bioSection->id, $bioTeacher->id));
        LoadAssignment::create($this->teachingLoad($chem->id, $chemSection->id, $chemTeacher->id));

        $service = new DeterministicSchedulingService;
        $result  = $service->generate($this->sy->id, $this->term->id);

        $this->assertSame(0, count($result['unplaceable']), 'Both Science Core classes should be fully placed: ' . json_encode($result['unplaceable']));

        $bioRows  = collect($result['schedules'])->where('subject_id', $bio->id)->values();
        $chemRows = collect($result['schedules'])->where('subject_id', $chem->id)->values();

        $this->assertSame(2, $bioRows->count());
        $this->assertSame(2, $chemRows->count());

        // Every Biology session must have a matching Chemistry session at the
        // identical day+time — the whole point of the shared group.
        foreach ($bioRows as $bioRow) {
            $match = $chemRows->first(fn ($c) =>
                $c['day_of_week'] === $bioRow['day_of_week']
                && $c['start_time'] === $bioRow['start_time']
                && $c['end_time'] === $bioRow['end_time']
            );
            $this->assertNotNull($match, "No Chemistry session found matching Biology's {$bioRow['day_of_week']} {$bioRow['start_time']}");
        }
    }

    public function test_science_core_slot_is_reserved_grade_wide_so_homerooms_cannot_use_it(): void
    {
        $mars = Section::create(['levelid' => 11, 'sectionname' => 'Mars', 'capacity' => 30, 'school_year_id' => $this->sy->id, 'syid' => $this->sy->id, 'is_active' => true]);

        $bioTeacher    = User::factory()->create();
        $chemTeacher   = User::factory()->create();
        $marsHomeroomTeacher = User::factory()->create();

        $bio  = $this->makeScienceCoreSubject('BIO-TEST2-G11', 'Biology Test2 Level 2');
        $chem = $this->makeScienceCoreSubject('CHEM-TEST2-G11', 'Chemistry Test2 Level 2');
        $bioSection  = Section::create(['levelid' => 11, 'sectionname' => ScienceCoreService::SECTION_PREFIX . 'BIO-TEST2-G11-1', 'capacity' => 30, 'school_year_id' => $this->sy->id, 'syid' => $this->sy->id, 'is_active' => true]);
        $chemSection = Section::create(['levelid' => 11, 'sectionname' => ScienceCoreService::SECTION_PREFIX . 'CHEM-TEST2-G11-1', 'capacity' => 30, 'school_year_id' => $this->sy->id, 'syid' => $this->sy->id, 'is_active' => true]);
        LoadAssignment::create($this->teachingLoad($bio->id, $bioSection->id, $bioTeacher->id));
        LoadAssignment::create($this->teachingLoad($chem->id, $chemSection->id, $chemTeacher->id));

        // A regular homeroom subject for Mars — placed AFTER the Science Core
        // group in the pipeline, so it must never land on the group's slot.
        $marsSubject = Subject::create([
            'school_year_id' => $this->sy->id, 'code' => 'ENG-TEST-G11', 'name' => 'English Test',
            'credit_units' => 3, 'lecture_hours' => 3, 'lab_hours' => 0, 'load_units' => 2,
            'subject_type' => 'lecture', 'grade_level' => 11, 'semester' => 'both',
            'sessions_per_week' => 1, 'minutes_per_session' => 50, 'is_active' => true,
        ]);
        LoadAssignment::create($this->teachingLoad($marsSubject->id, $mars->id, $marsHomeroomTeacher->id));

        $service = new DeterministicSchedulingService;
        $result  = $service->generate($this->sy->id, $this->term->id);

        $this->assertSame(0, count($result['unplaceable']));

        $scienceCoreSlots = collect($result['schedules'])
            ->whereIn('subject_id', [$bio->id, $chem->id])
            ->map(fn ($r) => $r['day_of_week'] . ' ' . $r['start_time'])
            ->unique();

        $marsSlots = collect($result['schedules'])
            ->where('subject_id', $marsSubject->id)
            ->map(fn ($r) => $r['day_of_week'] . ' ' . $r['start_time']);

        foreach ($marsSlots as $slot) {
            $this->assertFalse($scienceCoreSlots->contains($slot), "Mars's regular subject landed on the Science Core group's reserved slot: {$slot}");
        }
    }

    private function makeScienceCoreSubject(string $code, string $name): Subject
    {
        return Subject::create([
            'school_year_id' => $this->sy->id, 'code' => $code, 'name' => $name,
            'credit_units' => 5, 'lecture_hours' => 5, 'lab_hours' => 0, 'load_units' => 2,
            'subject_type' => 'science_core', 'grade_level' => 11, 'semester' => 'both',
            'sessions_per_week' => 2, 'minutes_per_session' => 50, 'is_active' => true,
        ]);
    }

    private function teachingLoad(int $subjectId, int $sectionId, int $userId): array
    {
        $facultyLoad = FacultyLoad::create([
            'user_id' => $userId, 'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
            'teaching_units' => 2, 'research_units' => 0, 'admin_units' => 0,
            'cocurricular_units' => 0, 'committee_units' => 0, 'total_units' => 2,
            'full_load_threshold' => 18.0, 'load_status' => 'underload', 'overload_approved' => false,
        ]);

        return [
            'faculty_load_id' => $facultyLoad->id,
            'user_id' => $userId, 'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subjectId, 'section_id' => $sectionId,
            'load_units' => 2,
        ];
    }
}
