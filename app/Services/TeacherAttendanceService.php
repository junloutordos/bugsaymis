<?php

namespace App\Services;

use App\Models\FacultyLoading\AcademicUnit;
use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\TeacherTapLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TeacherAttendanceService
{
    // Minutes before class start that a tap is accepted
    const EARLY_WINDOW_MINUTES = 15;

    // Minutes after class start that a tap is still accepted (after = late, not absent)
    const LATE_CUTOFF_MINUTES = 30;

    // Minutes after class start before "on_time" becomes "late"
    const GRACE_PERIOD_MINUTES = 5;

    /**
     * Record a tap for the given teacher in the given room.
     *
     * Returns an array:
     *   status        => 'on_time' | 'late' | 'no_match' | 'already_tapped' | 'room_not_found'
     *   tap           => TeacherTapLog|null
     *   schedule      => ClassSchedule|null (with subject, section relations loaded)
     *   classroom     => Classroom|null
     */
    public function tap(string $nfcUuid, User $teacher): array
    {
        $currentSyId = SchoolYear::where('is_current', true)->value('id');
        $classroom = Classroom::where('nfc_uuid', $nfcUuid)
            ->where('school_year_id', $currentSyId)
            ->first();

        if (! $classroom) {
            return ['status' => 'room_not_found', 'tap' => null, 'schedule' => null, 'classroom' => null];
        }

        $now = Carbon::now();
        $dayOfWeek = $now->format('l'); // "Monday", "Tuesday", etc.

        $schedule = $this->findMatchingSchedule($teacher->id, $classroom->id, $dayOfWeek, $now);

        if (! $schedule) {
            // Log the no-match tap so we have an audit trail
            $tap = TeacherTapLog::create([
                'user_id' => $teacher->id,
                'classroom_id' => $classroom->id,
                'class_schedule_id' => null,
                'tapped_at' => $now,
                'status' => 'no_match',
                'is_late' => false,
                'late_minutes' => 0,
            ]);

            return ['status' => 'no_match', 'tap' => $tap, 'schedule' => null, 'classroom' => $classroom];
        }

        // Idempotency: one tap per schedule slot per calendar day
        $existing = TeacherTapLog::where('user_id', $teacher->id)
            ->where('class_schedule_id', $schedule->id)
            ->whereDate('tapped_at', $now->toDateString())
            ->whereIn('status', ['on_time', 'late'])
            ->first();

        if ($existing) {
            return ['status' => 'already_tapped', 'tap' => $existing, 'schedule' => $schedule, 'classroom' => $classroom];
        }

        $classStart = Carbon::parse($now->toDateString().' '.$schedule->start_time);
        $lateMinutes = (int) max(0, $classStart->diffInMinutes($now, false));
        $isLate = $lateMinutes > self::GRACE_PERIOD_MINUTES;
        $tapStatus = $isLate ? 'late' : 'on_time';

        $tap = TeacherTapLog::create([
            'user_id' => $teacher->id,
            'classroom_id' => $classroom->id,
            'class_schedule_id' => $schedule->id,
            'tapped_at' => $now,
            'status' => $tapStatus,
            'is_late' => $isLate,
            'late_minutes' => $lateMinutes,
        ]);

        return ['status' => $tapStatus, 'tap' => $tap, 'schedule' => $schedule, 'classroom' => $classroom];
    }

    /**
     * Return the schedule for a teacher in a classroom that is currently
     * within the valid tap window.
     */
    private function findMatchingSchedule(int $userId, int $classroomId, string $dayOfWeek, Carbon $now): ?ClassSchedule
    {
        $earlyOpen = $now->copy()->subMinutes(self::EARLY_WINDOW_MINUTES)->format('H:i:s');
        $lateCutoff = $now->copy()->subMinutes(self::LATE_CUTOFF_MINUTES)->format('H:i:s');

        // start_time is within [-30 min, +15 min] of now, and class hasn't ended yet
        return ClassSchedule::with(['subject', 'section', 'academicTerm'])
            ->classes()
            ->where('user_id', $userId)
            ->where('classroom_id', $classroomId)
            ->where('day_of_week', $dayOfWeek)
            ->where('status', 'active')
            ->where('start_time', '>=', $lateCutoff)    // started no more than 30 min ago
            ->where('start_time', '<=', $earlyOpen)     // started or starts within next 15 min
            ->where('end_time', '>', $now->format('H:i:s')) // class not yet over
            ->orderBy('start_time')
            ->first();
    }

    /**
     * Build the "Today" data for the monitoring dashboard.
     *
     * Returns a collection of class_schedules for today, each annotated
     * with tap status (tapped | late | absent | upcoming).
     *
     * @param  int|null  $academicUnitId  Scope to a specific academic unit (for AUH)
     */
    public function todaySchedules(?int $academicUnitId = null): Collection
    {
        $dayOfWeek = Carbon::now()->format('l');
        $now = Carbon::now();

        $currentTermId = DB::table('academic_terms')
            ->join('school_years', 'academic_terms.school_year_id', '=', 'school_years.id')
            ->where('school_years.is_current', true)
            ->orderByDesc('academic_terms.start_date')
            ->value('academic_terms.id');

        $query = ClassSchedule::with([
            'faculty:id,name',
            'subject:id,name,code',
            'section:id,sectionname',
            'classroom:id,name,code',
        ])
            ->where('entry_type', 'class')
            ->where('day_of_week', $dayOfWeek)
            ->where('status', 'active');

        if ($currentTermId) {
            $query->where('academic_term_id', $currentTermId);
        }

        if ($academicUnitId) {
            $query->whereHas('subject', fn ($q) => $q->where('academic_unit_id', $academicUnitId));
        }

        $schedules = $query->orderBy('start_time')->get();

        // Fetch all tap logs for today for these schedule IDs (single query)
        $scheduleIds = $schedules->pluck('id')->filter();
        $tapLogs = TeacherTapLog::whereIn('class_schedule_id', $scheduleIds)
            ->whereDate('tapped_at', today())
            ->whereIn('status', ['on_time', 'late'])
            ->get()
            ->keyBy('class_schedule_id');

        return $schedules->map(function (ClassSchedule $cs) use ($tapLogs, $now) {
            $tap = $tapLogs->get($cs->id);

            $startTime = Carbon::parse(today()->toDateString().' '.$cs->start_time);
            $endTime = Carbon::parse(today()->toDateString().' '.$cs->end_time);

            if ($tap) {
                $displayStatus = $tap->is_late ? 'late' : 'on_time';
            } elseif ($now->lt($startTime->copy()->subMinutes(self::EARLY_WINDOW_MINUTES))) {
                $displayStatus = 'upcoming';
            } elseif ($now->lt($endTime)) {
                $displayStatus = 'no_tap';  // class in session but teacher hasn't tapped
            } else {
                $displayStatus = 'absent';  // class ended, no tap
            }

            return [
                'id' => $cs->id,
                'faculty' => ['id' => $cs->faculty?->id, 'name' => $cs->faculty?->name],
                'subject' => ['name' => $cs->subject?->name, 'code' => $cs->subject?->code],
                'section' => ['name' => $cs->section?->sectionname ?? '—'],
                'classroom' => ['name' => $cs->classroom?->name, 'code' => $cs->classroom?->code],
                'start_time' => $cs->start_time,
                'end_time' => $cs->end_time,
                'tap_status' => $displayStatus,
                'tapped_at' => $tap?->tapped_at?->format('H:i'),
                'late_minutes' => $tap?->late_minutes ?? 0,
            ];
        });
    }

    /**
     * Return paginated tap log history for the given filters.
     */
    public function history(array $filters, ?int $academicUnitId = null): LengthAwarePaginator
    {
        $query = TeacherTapLog::with([
            'teacher:id,name',
            'classroom:id,name,code',
            'classSchedule.subject:id,name,code',
            'classSchedule.section:id,sectionname',
        ]);

        if (! empty($filters['date'])) {
            $query->whereDate('tapped_at', $filters['date']);
        } else {
            $query->whereDate('tapped_at', today());
        }

        if (! empty($filters['teacher_id'])) {
            $query->where('user_id', $filters['teacher_id']);
        }

        if (! empty($filters['classroom_id'])) {
            $query->where('classroom_id', $filters['classroom_id']);
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if ($academicUnitId) {
            $query->whereHas('classSchedule.subject', fn ($q) => $q->where('academic_unit_id', $academicUnitId)
            );
        }

        return $query->orderByDesc('tapped_at')->paginate(15)->withQueryString();
    }

    /**
     * Return the academic unit that $user heads, or null if they're not an AUH.
     */
    public function getHeadedAcademicUnit(User $user): ?AcademicUnit
    {
        return AcademicUnit::where('head_user_id', $user->id)->where('is_active', true)->first();
    }
}
