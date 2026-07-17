<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\ClassScheduleSwapRequest;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use App\Services\DigitalSignatureService;
use App\Services\FacultyLoading\ClassScheduleApprovalService;
use App\Services\FacultyLoading\ClassScheduleSwapService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClassScheduleApprovalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('status')->default('active');
            $table->string('electronic_signature')->nullable();
            $table->string('signature_pin')->nullable();
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('role_user', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('user_id');
        });
        Schema::create('school_years', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current');
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('academic_terms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_year_id');
            $table->string('name');
            $table->string('term_type');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_current');
            $table->timestamps();
        });
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->integer('credit_units')->nullable();
            $table->decimal('lecture_hours')->nullable();
            $table->decimal('load_units')->nullable();
            $table->string('subject_type')->nullable();
            $table->integer('grade_level')->nullable();
            $table->integer('sessions_per_week')->nullable();
            $table->integer('minutes_per_session')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code');
            $table->string('classroom_type')->nullable();
            $table->integer('capacity')->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });
        Schema::create('load_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('academic_term_id');
            $table->string('assignment_type');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('section_id')->nullable();
            $table->timestamps();
        });
        Schema::create('class_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('load_assignment_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('section_id')->nullable();
            $table->unsignedBigInteger('classroom_id')->nullable();
            $table->unsignedBigInteger('school_year_id');
            $table->unsignedBigInteger('academic_term_id');
            $table->string('entry_type')->default('class');
            $table->string('session_type')->default('regular');
            $table->string('title')->nullable();
            $table->string('category')->nullable();
            $table->string('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('status');
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('class_schedule_approval_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('academic_term_id');
            $table->string('status');
            $table->string('approval_type')->default('full');
            $table->longText('schedule_snapshot');
            $table->string('schedule_hash', 64);
            $table->string('baseline_hash', 64)->nullable();
            $table->json('change_set')->nullable();
            $table->unsignedBigInteger('supersedes_batch_id')->nullable();
            $table->unsignedBigInteger('submitted_by');
            $table->timestamp('submitted_at');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('returned_by')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->text('return_remarks')->nullable();
            $table->timestamps();
        });
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->string('sectionname');
            $table->integer('levelid');
            $table->integer('capacity')->nullable();
            $table->time('recess_start')->nullable();
            $table->time('recess_end')->nullable();
            $table->time('lunch_start')->nullable();
            $table->time('lunch_end')->nullable();
            $table->timestamps();
        });
        Schema::create('faculty_loads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('academic_term_id');
            $table->unsignedBigInteger('user_id');
            $table->boolean('is_locked')->default(false);
            $table->decimal('admin_units')->default(0);
            $table->decimal('research_units')->default(0);
            $table->decimal('total_units')->default(0);
            $table->decimal('full_load_threshold')->default(18);
            $table->boolean('overload_approved')->default(false);
            $table->timestamps();
        });
        Schema::create('teacher_official_times', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
        });
        Schema::create('class_schedule_swap_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('academic_term_id');
            $table->unsignedBigInteger('source_schedule_id')->nullable();
            $table->unsignedBigInteger('requested_by');
            $table->text('reason');
            $table->json('preferences')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('target_schedule_id')->nullable();
            $table->json('selected_proposal')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('approval_batch_id')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });
        Schema::create('digital_signatures', function (Blueprint $table) {
            $table->id();
            $table->string('signable_type');
            $table->unsignedBigInteger('signable_id');
            $table->unsignedBigInteger('signer_id');
            $table->string('document_hash', 64);
            $table->text('signature');
            $table->string('signature_type')->default('hmac');
            $table->string('verification_token');
            $table->string('document_title');
            $table->json('metadata')->nullable();
            $table->timestamp('signed_at');
            $table->timestamps();
        });
    }

    public function test_cid_submission_is_locked_and_ocd_approval_activates_tentative_rows(): void
    {
        $cid = User::factory()->create([
            'electronic_signature' => 'signatures/cid.png',
            'signature_pin' => '1234',
        ]);
        $ocd = User::factory()->create([
            'electronic_signature' => 'signatures/ocd.png',
            'signature_pin' => '5678',
        ]);
        $schoolYear = SchoolYear::create([
            'name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $term = AcademicTerm::create([
            'school_year_id' => $schoolYear->id, 'name' => '1st Semester',
            'term_type' => '1st_semester', 'is_current' => true,
        ]);
        $subject = Subject::create([
            'code' => 'MATH8', 'name' => 'Mathematics 8', 'credit_units' => 1,
            'lecture_hours' => 1, 'load_units' => 1, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 1, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $room = Classroom::create([
            'name' => 'Room 1', 'code' => 'R1', 'classroom_type' => 'lecture',
            'capacity' => 30, 'is_available' => true,
        ]);
        $schedule = ClassSchedule::create([
            'user_id' => $cid->id, 'subject_id' => $subject->id, 'section_id' => 1,
            'classroom_id' => $room->id, 'school_year_id' => $schoolYear->id,
            'academic_term_id' => $term->id, 'day_of_week' => 'Monday',
            'start_time' => '08:00:00', 'end_time' => '09:00:00', 'status' => 'tentative',
        ]);

        $service = new ClassScheduleApprovalService(app(DigitalSignatureService::class));
        $batch = $service->submit($term, $cid, '1234');

        $this->assertSame('pending_ocd', $batch->status);
        $this->assertTrue(ClassScheduleApprovalService::termIsLocked($term->id));
        $this->assertDatabaseHas('digital_signatures', ['signable_id' => $batch->id, 'signer_id' => $cid->id]);

        $service->approve($batch, $ocd, '5678');

        $this->assertSame('approved', $batch->fresh()->status);
        $this->assertSame('active', $schedule->fresh()->status);
        $this->assertDatabaseHas('digital_signatures', ['signable_id' => $batch->id, 'signer_id' => $ocd->id]);
    }

    public function test_incorrect_signature_pin_does_not_create_submission(): void
    {
        $user = User::factory()->create([
            'electronic_signature' => 'signatures/cid.png',
            'signature_pin' => '1234',
        ]);
        $schoolYear = SchoolYear::create([
            'name' => '2027-2028', 'start_date' => '2027-08-01', 'end_date' => '2028-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $term = AcademicTerm::create([
            'school_year_id' => $schoolYear->id, 'name' => '1st Semester',
            'term_type' => '1st_semester', 'is_current' => true,
        ]);

        $this->expectException(ValidationException::class);
        (new ClassScheduleApprovalService(app(DigitalSignatureService::class)))->submit($term, $user, '9999');
    }

    public function test_swap_finder_excludes_both_rows_and_returns_an_exact_swap(): void
    {
        $facultyA = User::factory()->create();
        $facultyB = User::factory()->create();
        $schoolYear = SchoolYear::create([
            'name' => '2028-2029', 'start_date' => '2028-08-01', 'end_date' => '2029-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $term = AcademicTerm::create([
            'school_year_id' => $schoolYear->id, 'name' => '1st Semester',
            'term_type' => '1st_semester', 'is_current' => true,
        ]);
        $subjectA = Subject::create([
            'code' => 'MATH8', 'name' => 'Mathematics 8', 'load_units' => 1,
            'subject_type' => 'lecture', 'grade_level' => 8, 'is_active' => true,
        ]);
        $subjectB = Subject::create([
            'code' => 'ENG8', 'name' => 'English 8', 'load_units' => 1,
            'subject_type' => 'lecture', 'grade_level' => 8, 'is_active' => true,
        ]);
        $roomA = Classroom::create(['name' => 'Room A', 'code' => 'RA', 'classroom_type' => 'lecture', 'capacity' => 30, 'is_available' => true]);
        $roomB = Classroom::create(['name' => 'Room B', 'code' => 'RB', 'classroom_type' => 'lecture', 'capacity' => 30, 'is_available' => true]);
        $sectionA = DB::table('sections')->insertGetId(['sectionname' => 'Anthurium', 'levelid' => 8, 'created_at' => now(), 'updated_at' => now()]);
        $sectionB = DB::table('sections')->insertGetId(['sectionname' => 'Carnation', 'levelid' => 8, 'created_at' => now(), 'updated_at' => now()]);
        $source = ClassSchedule::create([
            'user_id' => $facultyA->id, 'subject_id' => $subjectA->id, 'section_id' => $sectionA,
            'classroom_id' => $roomA->id, 'school_year_id' => $schoolYear->id, 'academic_term_id' => $term->id,
            'day_of_week' => 'Monday', 'start_time' => '10:00', 'end_time' => '10:50', 'status' => 'active',
        ]);
        $partner = ClassSchedule::create([
            'user_id' => $facultyB->id, 'subject_id' => $subjectB->id, 'section_id' => $sectionB,
            'classroom_id' => $roomB->id, 'school_year_id' => $schoolYear->id, 'academic_term_id' => $term->id,
            'day_of_week' => 'Tuesday', 'start_time' => '07:30', 'end_time' => '08:20', 'status' => 'active',
        ]);
        $request = ClassScheduleSwapRequest::create([
            'academic_term_id' => $term->id, 'source_schedule_id' => $source->id,
            'requested_by' => $facultyA->id, 'reason' => 'Faculty availability changed.',
        ]);

        $service = app(ClassScheduleSwapService::class);
        $candidates = $service->candidates($request);

        $exact = collect($candidates)->first(fn ($candidate) => $candidate['type'] === 'swap'
            && $candidate['target_schedule_id'] === $partner->id);
        $this->assertNotNull($exact);
        $this->assertSame('Tuesday', $exact['source_new']['day_of_week']);
        $this->assertSame('Monday', $exact['target_new']['day_of_week']);

        $service->apply($request, $exact['id'], $facultyA, null);

        $this->assertSame('Tuesday', $source->fresh()->day_of_week);
        $this->assertSame('Monday', $partner->fresh()->day_of_week);
        $this->assertSame('applied', $request->fresh()->status);
    }

    public function test_approved_swap_is_applied_only_after_ocd_approves_the_amendment(): void
    {
        $cid = User::factory()->create(['electronic_signature' => 'cid.png', 'signature_pin' => '1234']);
        $ocd = User::factory()->create(['electronic_signature' => 'ocd.png', 'signature_pin' => '5678']);
        $schoolYear = SchoolYear::create([
            'name' => '2029-2030', 'start_date' => '2029-08-01', 'end_date' => '2030-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $term = AcademicTerm::create([
            'school_year_id' => $schoolYear->id, 'name' => '1st Semester',
            'term_type' => '1st_semester', 'is_current' => true,
        ]);
        $subject = Subject::create([
            'code' => 'BIO8', 'name' => 'Biology 8', 'load_units' => 1,
            'subject_type' => 'lecture', 'grade_level' => 8, 'is_active' => true,
        ]);
        $room = Classroom::create([
            'name' => 'Room 1', 'code' => 'R1', 'classroom_type' => 'lecture',
            'capacity' => 30, 'is_available' => true,
        ]);
        $schedule = ClassSchedule::create([
            'user_id' => $cid->id, 'subject_id' => $subject->id, 'section_id' => 1,
            'classroom_id' => $room->id, 'school_year_id' => $schoolYear->id,
            'academic_term_id' => $term->id, 'day_of_week' => 'Monday',
            'start_time' => '10:00', 'end_time' => '10:50', 'status' => 'tentative',
        ]);
        $service = app(ClassScheduleApprovalService::class);
        $original = $service->submit($term, $cid, '1234');
        $service->approve($original, $ocd, '5678');
        $swapRequest = ClassScheduleSwapRequest::create([
            'academic_term_id' => $term->id, 'source_schedule_id' => $schedule->id,
            'requested_by' => $cid->id, 'reason' => 'Move to Tuesday.',
        ]);

        $amendment = $service->submitSwapAmendment($term, $cid, '1234', [[
            'schedule_id' => $schedule->id,
            'attributes' => ['day_of_week' => 'Tuesday', 'start_time' => '07:30', 'end_time' => '08:20', 'classroom_id' => $room->id],
        ]], $swapRequest);

        $this->assertSame('Monday', $schedule->fresh()->day_of_week);
        $this->assertSame('pending_ocd', $amendment->status);

        $service->approve($amendment, $ocd, '5678');

        $this->assertSame('Tuesday', $schedule->fresh()->day_of_week);
        $this->assertSame('superseded', $original->fresh()->status);
        $this->assertSame('applied', $swapRequest->fresh()->status);
    }
}
