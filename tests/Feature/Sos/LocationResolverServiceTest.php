<?php

namespace Tests\Feature\Sos;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Student;
use App\Models\User;
use App\Services\Sos\LocationResolverService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LocationResolverServiceTest extends TestCase
{
    use RefreshDatabase;

    private function currentTerm(): AcademicTerm
    {
        $schoolYear = SchoolYear::create([
            'name' => '2026-2027', 'is_current' => true, 'status' => 'active',
            'start_date' => '2026-06-01', 'end_date' => '2027-03-31',
        ]);
        return AcademicTerm::create([
            'school_year_id' => $schoolYear->id, 'name' => '1st Semester',
            'term_type' => '1st_semester', 'is_current' => true,
        ]);
    }

    private function seedStudent(): int
    {
        return DB::table('students')->insertGetId([
            'pisaysystemID' => 'TEST-LOC-1', 'firstname' => 'Loc', 'lastname' => 'Test',
        ]);
    }

    public function test_student_mid_class_resolves_to_classroom(): void
    {
        $term = $this->currentTerm();
        $classroom = Classroom::create(['school_year_id' => $term->school_year_id, 'name' => 'Room 204', 'code' => 'R204', 'building' => 'Main Building']);
        $section = Section::create(['levelid' => 7, 'sectionname' => 'Newton', 'syid' => $term->school_year_id, 'school_year_id' => $term->school_year_id, 'classroom_id' => $classroom->id, 'is_active' => true]);
        $subject = Subject::create(['school_year_id' => $term->school_year_id, 'code' => 'SCI7', 'name' => 'Science 7', 'subject_type' => 'science_core', 'load_units' => 1, 'grade_level' => 7]);
        $teacher = User::factory()->create(['name' => 'Ms. Curie']);

        $monday = Carbon::now()->next(Carbon::MONDAY)->setTime(10, 15);

        ClassSchedule::create([
            'user_id' => $teacher->id, 'subject_id' => $subject->id, 'section_id' => $section->id,
            'classroom_id' => $classroom->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'entry_type' => 'class', 'session_type' => 'regular', 'day_of_week' => 'Monday',
            'start_time' => '09:30:00', 'end_time' => '10:30:00', 'status' => 'active',
        ]);

        $studentId = $this->seedStudent();
        StudentEnrollment::create([
            'student_id' => $studentId, 'school_year_id' => $term->school_year_id, 'section_id' => $section->id,
            'grade_level' => 7, 'enrollment_type' => 'new', 'status' => 'enrolled', 'enrollment_date' => now(),
        ]);

        $result = app(LocationResolverService::class)->resolve(Student::find($studentId), $monday);

        $this->assertSame('classroom', $result['type']);
        $this->assertSame('Room 204 — Science 7 with Ms. Curie', $result['label']);
        $this->assertSame('Main Building', $result['building']);
        $this->assertSame('Room 204', $result['room']);
        $this->assertSame('schedule', $result['source']);
    }

    public function test_student_in_a_gap_falls_back_to_homeroom(): void
    {
        $term = $this->currentTerm();
        $classroom = Classroom::create(['school_year_id' => $term->school_year_id, 'name' => 'Room 101', 'code' => 'R101']);
        $section = Section::create(['levelid' => 7, 'sectionname' => 'Newton', 'syid' => $term->school_year_id, 'school_year_id' => $term->school_year_id, 'classroom_id' => $classroom->id, 'is_active' => true]);

        $studentId = $this->seedStudent();
        StudentEnrollment::create([
            'student_id' => $studentId, 'school_year_id' => $term->school_year_id, 'section_id' => $section->id,
            'grade_level' => 7, 'enrollment_type' => 'new', 'status' => 'enrolled', 'enrollment_date' => now(),
        ]);

        $sunday = Carbon::now()->next(Carbon::MONDAY)->subDay()->setTime(10, 0);

        $result = app(LocationResolverService::class)->resolve(Student::find($studentId), $sunday);

        $this->assertSame('homeroom', $result['type']);
        $this->assertSame('Homeroom: Room 101', $result['label']);
        $this->assertSame('homeroom', $result['source']);
    }

    public function test_student_with_no_enrollment_is_unknown(): void
    {
        $this->currentTerm();
        $studentId = $this->seedStudent();

        $result = app(LocationResolverService::class)->resolve(Student::find($studentId), Carbon::now());

        $this->assertSame('unknown', $result['type']);
        $this->assertSame('fallback', $result['source']);
    }

    public function test_faculty_mid_class_resolves_to_classroom(): void
    {
        $term = $this->currentTerm();
        $classroom = Classroom::create(['school_year_id' => $term->school_year_id, 'name' => 'Room 305', 'code' => 'R305', 'building' => 'Science Wing']);
        $section = Section::create(['levelid' => 8, 'sectionname' => 'Darwin', 'syid' => $term->school_year_id, 'school_year_id' => $term->school_year_id, 'is_active' => true]);
        $subject = Subject::create(['school_year_id' => $term->school_year_id, 'code' => 'MATH8', 'name' => 'Math 8', 'subject_type' => 'science_core', 'load_units' => 1, 'grade_level' => 8]);
        $teacher = User::factory()->create(['name' => 'Mr. Newton']);

        $monday = Carbon::now()->next(Carbon::MONDAY)->setTime(13, 0);

        ClassSchedule::create([
            'user_id' => $teacher->id, 'subject_id' => $subject->id, 'section_id' => $section->id,
            'classroom_id' => $classroom->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'entry_type' => 'class', 'session_type' => 'regular', 'day_of_week' => 'Monday',
            'start_time' => '12:30:00', 'end_time' => '13:30:00', 'status' => 'active',
        ]);

        $result = app(LocationResolverService::class)->resolve($teacher, $monday);

        $this->assertSame('classroom', $result['type']);
        $this->assertSame('Teaching Math 8 — Room 305', $result['label']);
        $this->assertSame('schedule', $result['source']);
    }

    public function test_faculty_with_no_current_class_falls_back_to_office(): void
    {
        $this->currentTerm();
        $division = \App\Models\Division::create(['division_name' => 'Curriculum & Instruction Division', 'status' => 'active']);
        $office = \App\Models\Office::create(['name' => 'CID Office', 'division_id' => $division->id]);
        $teacher = User::factory()->create(['office_id' => $office->id]);

        $result = app(LocationResolverService::class)->resolve($teacher, Carbon::now()->next(Carbon::SUNDAY));

        $this->assertSame('office', $result['type']);
        $this->assertSame('CID Office (Curriculum & Instruction Division)', $result['label']);
        $this->assertSame('office', $result['source']);
    }

    public function test_staff_with_no_teaching_load_resolves_straight_to_office(): void
    {
        $this->currentTerm();
        $office = \App\Models\Office::create(['name' => 'General Services Office']);
        $staff = User::factory()->create(['office_id' => $office->id]);

        $result = app(LocationResolverService::class)->resolve($staff, Carbon::now());

        $this->assertSame('office', $result['type']);
        $this->assertSame('General Services Office', $result['label']);
    }

    public function test_staff_with_no_office_is_unknown(): void
    {
        $this->currentTerm();
        $staff = User::factory()->create(['office_id' => null]);

        $result = app(LocationResolverService::class)->resolve($staff, Carbon::now());

        $this->assertSame('unknown', $result['type']);
    }
}
