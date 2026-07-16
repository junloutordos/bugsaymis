<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use App\Services\DigitalSignatureService;
use App\Services\FacultyLoading\ClassScheduleApprovalService;
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
            $table->longText('schedule_snapshot');
            $table->string('schedule_hash', 64);
            $table->unsignedBigInteger('submitted_by');
            $table->timestamp('submitted_at');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('returned_by')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->text('return_remarks')->nullable();
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
}
