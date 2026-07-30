<?php

namespace Tests\Feature;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\Registrar\StudentEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentIndexTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $currentSY;
    private SchoolYear $pastSY;
    private Section $sectionA;
    private Section $sectionB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currentSY = SchoolYear::create([
            'name' => '2026-2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_current' => true,
            'status' => 'active',
        ]);

        $this->pastSY = SchoolYear::create([
            'name' => '2025-2026',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'is_current' => false,
            'status' => 'active',
        ]);

        $this->sectionA = Section::create([
            'levelid' => 7, 'sectionname' => 'Newton', 'syid' => $this->currentSY->id,
            'school_year_id' => $this->currentSY->id, 'capacity' => 30, 'is_active' => true,
        ]);

        $this->sectionB = Section::create([
            'levelid' => 8, 'sectionname' => 'Einstein', 'syid' => $this->currentSY->id,
            'school_year_id' => $this->currentSY->id, 'capacity' => 30, 'is_active' => true,
        ]);
    }

    private function makeStudent(array $overrides = []): int
    {
        return (int) DB::table('students')->insertGetId(array_merge([
            'lastname' => 'Cruz',
            'firstname' => 'Juan',
            'middlename' => 'Santos',
            'sex' => 'M',
            'student_email' => 'juan.cruz@crc.pshs.edu.ph',
        ], $overrides));
    }

    public function test_active_tab_only_shows_students_enrolled_in_current_school_year(): void
    {
        $enrolled = $this->makeStudent(['lastname' => 'Alpha']);
        StudentEnrollment::create([
            'student_id' => $enrolled, 'school_year_id' => $this->currentSY->id,
            'section_id' => $this->sectionA->id, 'grade_level' => 7,
            'enrollment_type' => 'returning', 'status' => 'enrolled', 'enrollment_date' => '2026-07-20',
        ]);

        $dropped = $this->makeStudent(['lastname' => 'Beta']);
        StudentEnrollment::create([
            'student_id' => $dropped, 'school_year_id' => $this->currentSY->id,
            'section_id' => $this->sectionA->id, 'grade_level' => 7,
            'enrollment_type' => 'returning', 'status' => 'dropped', 'enrollment_date' => '2026-07-20',
        ]);

        $neverEnrolled = $this->makeStudent(['lastname' => 'Gamma']);

        $pastOnly = $this->makeStudent(['lastname' => 'Delta']);
        StudentEnrollment::create([
            'student_id' => $pastOnly, 'school_year_id' => $this->pastSY->id,
            'section_id' => $this->sectionA->id, 'grade_level' => 7,
            'enrollment_type' => 'returning', 'status' => 'enrolled', 'enrollment_date' => '2025-07-20',
        ]);

        $response = $this->actingAs($this->adminUser())
            ->get(route('students.index', ['tab' => 'active']));

        $response->assertOk();
        $ids = collect($response->viewData('page')['props']['students']['data'])->pluck('id')->all();

        $this->assertContains($enrolled, $ids);
        $this->assertNotContains($dropped, $ids);
        $this->assertNotContains($neverEnrolled, $ids);
        $this->assertNotContains($pastOnly, $ids);

        $counts = $response->viewData('page')['props']['tab_counts'];
        $this->assertSame(1, $counts['active']);
        $this->assertSame(3, $counts['inactive']);
    }

    public function test_inactive_tab_includes_never_enrolled_and_non_enrolled_status(): void
    {
        $enrolled = $this->makeStudent(['lastname' => 'Alpha']);
        StudentEnrollment::create([
            'student_id' => $enrolled, 'school_year_id' => $this->currentSY->id,
            'section_id' => $this->sectionA->id, 'grade_level' => 7,
            'enrollment_type' => 'returning', 'status' => 'enrolled', 'enrollment_date' => '2026-07-20',
        ]);

        $neverEnrolled = $this->makeStudent(['lastname' => 'Gamma']);

        $onLeave = $this->makeStudent(['lastname' => 'Epsilon']);
        StudentEnrollment::create([
            'student_id' => $onLeave, 'school_year_id' => $this->currentSY->id,
            'section_id' => $this->sectionB->id, 'grade_level' => 8,
            'enrollment_type' => 'returning', 'status' => 'on_leave', 'enrollment_date' => '2026-07-20',
        ]);

        $response = $this->actingAs($this->adminUser())
            ->get(route('students.index', ['tab' => 'inactive']));

        $response->assertOk();
        $ids = collect($response->viewData('page')['props']['students']['data'])->pluck('id')->all();

        $this->assertNotContains($enrolled, $ids);
        $this->assertContains($neverEnrolled, $ids);
        $this->assertContains($onLeave, $ids);
    }

    public function test_search_matches_across_name_email_and_lrn(): void
    {
        $target = $this->makeStudent([
            'lastname' => 'Dela Cruz', 'firstname' => 'Maria', 'lrn' => '123456789012',
            'student_email' => 'maria.delacruz@crc.pshs.edu.ph',
        ]);
        $other = $this->makeStudent(['lastname' => 'Reyes', 'firstname' => 'Pedro']);

        $response = $this->actingAs($this->adminUser())
            ->get(route('students.index', ['tab' => 'inactive', 'q' => 'delacruz']));

        $ids = collect($response->viewData('page')['props']['students']['data'])->pluck('id')->all();
        $this->assertContains($target, $ids);
        $this->assertNotContains($other, $ids);

        $responseLrn = $this->actingAs($this->adminUser())
            ->get(route('students.index', ['tab' => 'inactive', 'q' => '123456789012']));
        $idsLrn = collect($responseLrn->viewData('page')['props']['students']['data'])->pluck('id')->all();
        $this->assertContains($target, $idsLrn);
    }

    public function test_filters_by_section_grade_and_sex(): void
    {
        $matching = $this->makeStudent(['lastname' => 'Match', 'sex' => 'F']);
        StudentEnrollment::create([
            'student_id' => $matching, 'school_year_id' => $this->currentSY->id,
            'section_id' => $this->sectionA->id, 'grade_level' => 7,
            'enrollment_type' => 'returning', 'status' => 'enrolled', 'enrollment_date' => '2026-07-20',
        ]);

        $otherSection = $this->makeStudent(['lastname' => 'OtherSection', 'sex' => 'F']);
        StudentEnrollment::create([
            'student_id' => $otherSection, 'school_year_id' => $this->currentSY->id,
            'section_id' => $this->sectionB->id, 'grade_level' => 8,
            'enrollment_type' => 'returning', 'status' => 'enrolled', 'enrollment_date' => '2026-07-20',
        ]);

        $otherSex = $this->makeStudent(['lastname' => 'OtherSex', 'sex' => 'M']);
        StudentEnrollment::create([
            'student_id' => $otherSex, 'school_year_id' => $this->currentSY->id,
            'section_id' => $this->sectionA->id, 'grade_level' => 7,
            'enrollment_type' => 'returning', 'status' => 'enrolled', 'enrollment_date' => '2026-07-20',
        ]);

        $response = $this->actingAs($this->adminUser())->get(route('students.index', [
            'tab' => 'active', 'section_id' => $this->sectionA->id, 'grade_level' => 7, 'sex' => 'F',
        ]));

        $ids = collect($response->viewData('page')['props']['students']['data'])->pluck('id')->all();
        $this->assertSame([$matching], $ids);
    }

    public function test_page_size_is_thirty(): void
    {
        $response = $this->actingAs($this->adminUser())->get(route('students.index'));
        $this->assertSame(30, $response->viewData('page')['props']['students']['per_page']);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->update(['role_id' => null]);
        // isSuperAdmin() checks role name; attach an Administrator role
        $role = \App\Models\Role::firstOrCreate(['name' => 'Administrator']);
        $user->roles()->attach($role);
        return $user;
    }
}
