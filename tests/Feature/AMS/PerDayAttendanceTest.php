<?php

namespace Tests\Feature\AMS;

use App\Models\AMS\Activity;
use App\Models\AMS\ActivityAttendanceDay;
use App\Models\AMS\ActivityParticipant;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerDayAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_day_belongs_to_activity_and_enforces_unique_combo(): void
    {
        $activity = Activity::create([
            'user_id' => \App\Models\User::factory()->create()->id,
            'title' => 'Multi-day Activity',
            'activity_type' => Activity::TYPE_IN_HOUSE,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-12',
        ]);

        $day = ActivityAttendanceDay::create([
            'activity_id' => $activity->id,
            'participant_type' => 'employee',
            'participant_id' => 999,
            'date' => '2026-08-10',
            'attended' => 'yes',
            'hours_attended' => 8,
        ]);

        $this->assertSame($activity->id, $day->activity->id);
        $this->assertCount(1, $activity->attendanceDays);

        $this->expectException(\Illuminate\Database\QueryException::class);
        ActivityAttendanceDay::create([
            'activity_id' => $activity->id,
            'participant_type' => 'employee',
            'participant_id' => 999,
            'date' => '2026-08-10',
            'attended' => 'no',
            'hours_attended' => 0,
        ]);
    }

    public function test_single_day_activity_ignores_daily_payload(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeMultiDayActivity($owner, '2026-08-10', '2026-08-10');
        $attendee = User::factory()->create();
        $participant = ActivityParticipant::create([
            'activity_id' => $activity->id, 'participant_id' => $attendee->id,
            'participant_type' => 'employee', 'attended' => 'no', 'hours_attended' => 0,
        ]);

        $this->actingAs($owner)
            ->post(route('ams.activities.participants.save-attendance', [$activity, $participant]), [
                'attended' => 'yes',
                'hours_attended' => 8,
                'daily' => [
                    ['date' => '2026-08-10', 'attended' => 'yes', 'hours_attended' => 8],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('ams_activity_attendance_days', 0);
        $this->assertSame('yes', $participant->fresh()->attended);
        $this->assertSame('8.00', $participant->fresh()->hours_attended);
    }

    public function test_multiday_employee_attendance_upserts_daily_rows_and_computes_rollup(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeMultiDayActivity($owner, '2026-08-10', '2026-08-12');
        $attendee = User::factory()->create();
        $participant = ActivityParticipant::create([
            'activity_id' => $activity->id, 'participant_id' => $attendee->id,
            'participant_type' => 'employee', 'attended' => 'no', 'hours_attended' => 0,
        ]);

        $this->actingAs($owner)
            ->post(route('ams.activities.participants.save-attendance', [$activity, $participant]), [
                'attended' => 'no',
                'hours_attended' => 0,
                'daily' => [
                    ['date' => '2026-08-10', 'attended' => 'yes', 'hours_attended' => 8],
                    ['date' => '2026-08-11', 'attended' => 'no', 'hours_attended' => 0],
                    ['date' => '2026-08-12', 'attended' => 'yes', 'hours_attended' => 6],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('ams_activity_attendance_days', 3);
        $this->assertDatabaseHas('ams_activity_attendance_days', [
            'activity_id' => $activity->id, 'participant_type' => 'employee',
            'participant_id' => $attendee->id, 'date' => '2026-08-10', 'attended' => 'yes',
        ]);

        $participant->refresh();
        $this->assertSame('yes', $participant->attended); // present on any day
        $this->assertSame('14.00', $participant->hours_attended); // 8 + 6, day 2 absent contributes 0
    }

    public function test_resaving_daily_attendance_updates_existing_rows_not_duplicates(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeMultiDayActivity($owner, '2026-08-10', '2026-08-11');
        $attendee = User::factory()->create();
        $participant = ActivityParticipant::create([
            'activity_id' => $activity->id, 'participant_id' => $attendee->id,
            'participant_type' => 'employee', 'attended' => 'no', 'hours_attended' => 0,
        ]);
        $payload = [
            'attended' => 'no', 'hours_attended' => 0,
            'daily' => [
                ['date' => '2026-08-10', 'attended' => 'yes', 'hours_attended' => 8],
                ['date' => '2026-08-11', 'attended' => 'yes', 'hours_attended' => 8],
            ],
        ];

        $this->actingAs($owner)->post(route('ams.activities.participants.save-attendance', [$activity, $participant]), $payload);
        $this->assertDatabaseCount('ams_activity_attendance_days', 2);

        $payload['daily'][1]['hours_attended'] = 4;
        $this->actingAs($owner)->post(route('ams.activities.participants.save-attendance', [$activity, $participant]), $payload);

        $this->assertDatabaseCount('ams_activity_attendance_days', 2); // still 2, not 4
        $this->assertDatabaseHas('ams_activity_attendance_days', [
            'activity_id' => $activity->id, 'date' => '2026-08-11', 'hours_attended' => 4,
        ]);
        $this->assertSame('12.00', $participant->fresh()->hours_attended); // 8 + 4
    }

    public function test_multiday_section_student_attendance_upserts_daily_rows_and_rollup(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeMultiDayActivity($owner, '2026-08-10', '2026-08-11');
        $section = \App\Models\AMS\ActivityParticipant::create([
            'activity_id' => $activity->id, 'participant_id' => 1, 'participant_type' => 'section',
        ]);
        $studentAttendance = \App\Models\AMS\ActivityStudentAttendance::create([
            'activity_id' => $activity->id, 'participant_id' => 501, 'attended' => 'no', 'hours_attended' => 0,
        ]);

        $this->actingAs($owner)
            ->post(route('ams.activities.participants.save-section-attendance', [$activity, $section]), [
                'students' => [[
                    'attendance_id' => $studentAttendance->id,
                    'student_id' => 501,
                    'attended' => 'no',
                    'hours_attended' => 0,
                    'daily' => [
                        ['date' => '2026-08-10', 'attended' => 'yes', 'hours_attended' => 7],
                        ['date' => '2026-08-11', 'attended' => 'yes', 'hours_attended' => 7],
                    ],
                ]],
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('ams_activity_attendance_days', 2);
        $this->assertDatabaseHas('ams_activity_attendance_days', [
            'activity_id' => $activity->id, 'participant_type' => 'student',
            'participant_id' => 501, 'date' => '2026-08-10', 'attended' => 'yes',
        ]);

        $studentAttendance->refresh();
        $this->assertSame('yes', $studentAttendance->attended);
        $this->assertSame('14.00', $studentAttendance->hours_attended);
    }

    public function test_show_page_exposes_multi_day_flag_and_per_participant_daily_data(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeMultiDayActivity($owner, '2026-08-10', '2026-08-11');
        $attendee = User::factory()->create();
        ActivityParticipant::create([
            'activity_id' => $activity->id, 'participant_id' => $attendee->id,
            'participant_type' => 'employee', 'attended' => 'yes', 'hours_attended' => 8,
        ]);
        ActivityAttendanceDay::create([
            'activity_id' => $activity->id, 'participant_type' => 'employee',
            'participant_id' => $attendee->id, 'date' => '2026-08-10',
            'attended' => 'yes', 'hours_attended' => 8,
        ]);

        $this->actingAs($owner)
            ->get(route('ams.activities.show', $activity))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('activity.is_multi_day', true)
                ->where('activity.attendance_days', ['2026-08-10', '2026-08-11'])
                ->where("participants.0.daily.2026-08-10.attended", 'yes')
            );
    }

    public function test_section_students_endpoint_exposes_per_day_data(): void
    {
        $owner = $this->userWithPermission('activities.manage');
        $activity = $this->makeMultiDayActivity($owner, '2026-08-10', '2026-08-11');
        $section = ActivityParticipant::create([
            'activity_id' => $activity->id, 'participant_id' => 1, 'participant_type' => 'section',
        ]);
        \Illuminate\Support\Facades\DB::table('section_students')->insert([
            'sectionid' => 1, 'studentid' => 501,
        ]);
        \App\Models\Student::forceCreate(['id' => 501, 'firstname' => 'Juan', 'lastname' => 'Cruz']);
        \App\Models\AMS\ActivityStudentAttendance::create([
            'activity_id' => $activity->id, 'participant_id' => 501, 'attended' => 'yes', 'hours_attended' => 7,
        ]);
        ActivityAttendanceDay::create([
            'activity_id' => $activity->id, 'participant_type' => 'student',
            'participant_id' => 501, 'date' => '2026-08-11', 'attended' => 'yes', 'hours_attended' => 7,
        ]);

        $response = $this->actingAs($owner)
            ->getJson(route('ams.activities.participants.students', [$activity, $section]))
            ->assertOk();

        $json = $response->json();
        $this->assertSame('yes', $json[0]['daily']['2026-08-11']['attended']);
    }

    private function makeMultiDayActivity(User $owner, string $start, string $end): Activity
    {
        return Activity::create([
            'user_id' => $owner->id,
            'title' => 'Multi-day Test Activity',
            'activity_type' => Activity::TYPE_IN_HOUSE,
            'start_date' => $start,
            'end_date' => $end,
        ]);
    }

    private function userWithPermission(string $permissionName): User
    {
        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['module' => 'Activities', 'description' => $permissionName],
        );
        $role = Role::create(['name' => 'AMS Daily Test '.uniqid()]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role);

        return $user;
    }
}
