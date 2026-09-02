<?php

namespace App\Services;

use App\Models\FacultyLoading\AcademicUnit;
use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\ClassScheduleDayAdjustment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\TeacherTapLog;
use App\Models\User;
use App\Services\FacultyLoading\AdjustedClassScheduleService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TeacherAttendanceService
{
    // Minutes before class start that a tap is accepted
    const EARLY_WINDOW_MINUTES = 15;

    // Minutes after class start before "on_time" becomes "late"
    const GRACE_PERIOD_MINUTES = 5;

    public function __construct(
        private readonly AdjustedClassScheduleService $adjustedScheduleService,
    ) {}

    /**
     * Record a tap for the given teacher in the given room.
     *
     * Returns an array:
     *   status        => 'on_time' | 'late' | 'no_match' | 'already_tapped' | 'room_not_found'
     *   tap           => TeacherTapLog|null
     *   schedule      => ClassSchedule|null (with subject, section relations loaded)
     *   classroom     => Classroom|null
     */
    public function tap(
        string $nfcUuid,
        User $teacher,
        string $channel = 'nfc',
        ?string $ip = null,
        ?float $lat = null,
        ?float $lng = null,
        ?string $locationStatus = null,
        ?string $networkStatus = null,
    ): array {
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

        $tapMeta = [
            'channel' => $channel,
            'ip_address' => $ip,
            'location_status' => $locationStatus,
            'network_status' => $networkStatus,
            'latitude' => $lat,
            'longitude' => $lng,
        ];

        if (! $schedule) {
            // Log the no-match tap so we have an audit trail
            $tap = TeacherTapLog::create(array_merge([
                'user_id' => $teacher->id,
                'classroom_id' => $classroom->id,
                'class_schedule_id' => null,
                'tapped_at' => $now,
                'status' => 'no_match',
                'is_late' => false,
                'late_minutes' => 0,
            ], $tapMeta));

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

        $tap = TeacherTapLog::create(array_merge([
            'user_id' => $teacher->id,
            'classroom_id' => $classroom->id,
            'class_schedule_id' => $schedule->id,
            'class_schedule_day_adjustment_id' => $schedule->day_adjustment_id,
            'tapped_at' => $now,
            'status' => $tapStatus,
            'is_late' => $isLate,
            'late_minutes' => $lateMinutes,
        ], $tapMeta));

        return ['status' => $tapStatus, 'tap' => $tap, 'schedule' => $schedule, 'classroom' => $classroom];
    }

    /**
     * Return the schedule for a teacher in a classroom that is currently
     * within the valid tap window.
     *
     * On a day with a published Adjusted Day schedule, the window is
     * evaluated against that day's frozen adjusted start/end times — not
     * the teacher's regular weekly ClassSchedule times — and a class
     * bumped off the adjusted timetable entirely never matches. The
     * matched model's start_time/end_time are overwritten in-memory (never
     * persisted) with the effective times so lateness calc and the tap
     * confirmation UI both reflect what actually applied today.
     */
    private function findMatchingSchedule(int $userId, int $classroomId, string $dayOfWeek, Carbon $now): ?ClassSchedule
    {
        [$adjustedEntries, $unplacedIds] = $this->loadAdjustedScheduleMaps($now->toDateString());

        // Candidates are pulled without a DB-level time-window filter since
        // the effective window may differ (and may not even exist, for an
        // unplaced entry) from the raw stored times — the window check
        // below runs in PHP against each candidate's effective times.
        $candidates = ClassSchedule::with(['subject', 'section', 'academicTerm'])
            ->classes()
            ->where('user_id', $userId)
            ->where('classroom_id', $classroomId)
            ->where('day_of_week', $dayOfWeek)
            ->where('status', 'active')
            ->get();

        $earlyOpen = $now->copy()->addMinutes(self::EARLY_WINDOW_MINUTES)->format('H:i:s');
        $nowTime = $now->format('H:i:s');

        return $candidates
            ->map(fn (ClassSchedule $schedule) => $this->applyEffectiveTimes($schedule, $adjustedEntries, $unplacedIds))
            ->filter()
            ->filter(fn (ClassSchedule $schedule) => $schedule->start_time <= $earlyOpen && $schedule->end_time > $nowTime)
            ->sortBy('start_time')
            ->first();
    }

    /**
     * @param  array<int,array{start_time:string,end_time:string,adjustment_id:int}>  $adjustedEntries
     * @param  array<int,bool>  $unplacedIds
     */
    private function applyEffectiveTimes(ClassSchedule $schedule, array $adjustedEntries, array $unplacedIds): ?ClassSchedule
    {
        if (isset($unplacedIds[$schedule->id])) {
            // Bumped off today's published adjusted timetable entirely —
            // the regular weekly slot does not apply today.
            return null;
        }

        if (isset($adjustedEntries[$schedule->id])) {
            $schedule->start_time = $adjustedEntries[$schedule->id]['start_time'];
            $schedule->end_time = $adjustedEntries[$schedule->id]['end_time'];
            $schedule->is_adjusted_day = true;
            $schedule->day_adjustment_id = $adjustedEntries[$schedule->id]['adjustment_id'];

            return $schedule;
        }

        // No published adjustment today, or this grade wasn't in its scope
        // ("regular_schedule_applies") — the regular weekly slot stands.
        $schedule->is_adjusted_day = false;
        $schedule->day_adjustment_id = null;

        return $schedule;
    }

    /**
     * Flatten every published adjustment's frozen snapshot for the given
     * date into two lookups keyed by class_schedule_id: adjusted
     * start/end times, and ids bumped to "unplaced" (not held today).
     * Matching by class_schedule_id (rather than section+time, as
     * App\Services\Sos\LocationResolverService does for its analogous
     * problem) correctly handles the rare case of multiple grade-scoped
     * adjustments sharing the same effective_date.
     *
     * @return array{0:array<int,array{start_time:string,end_time:string,adjustment_id:int}>,1:array<int,bool>}
     */
    private function loadAdjustedScheduleMaps(string $dateStr): array
    {
        $entries = [];
        $unplaced = [];

        $adjustments = ClassScheduleDayAdjustment::published()->forDate($dateStr)->get();

        foreach ($adjustments as $adjustment) {
            $snapshot = $this->adjustedScheduleService->printableSnapshot($adjustment);

            foreach ($snapshot['grades'] ?? [] as $grade) {
                foreach ($grade['sections'] ?? [] as $section) {
                    foreach ($section['entries'] ?? [] as $entry) {
                        $entries[$entry['id']] = [
                            'start_time' => $entry['start_time'],
                            'end_time' => $entry['end_time'],
                            'adjustment_id' => $adjustment->id,
                        ];
                    }

                    foreach ($section['unplaced_entries'] ?? [] as $unplacedEntry) {
                        $unplaced[$unplacedEntry['id']] = true;
                    }
                }
            }
        }

        return [$entries, $unplaced];
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

        [$adjustedEntries, $unplacedIds] = $this->loadAdjustedScheduleMaps($now->toDateString());

        // Fetch all tap logs for today for these schedule IDs (single query)
        $scheduleIds = $schedules->pluck('id')->filter();
        $tapLogs = TeacherTapLog::whereIn('class_schedule_id', $scheduleIds)
            ->whereDate('tapped_at', today())
            ->whereIn('status', ['on_time', 'late'])
            ->get()
            ->keyBy('class_schedule_id');

        return $schedules->map(function (ClassSchedule $cs) use ($tapLogs, $now, $adjustedEntries, $unplacedIds) {
            $tap = $tapLogs->get($cs->id);

            if (isset($unplacedIds[$cs->id])) {
                // Bumped off today's published adjusted timetable — this
                // class isn't happening today, so it can't be "absent".
                return [
                    'id' => $cs->id,
                    'faculty' => ['id' => $cs->faculty?->id, 'name' => $cs->faculty?->name],
                    'subject' => ['name' => $cs->subject?->name, 'code' => $cs->subject?->code],
                    'section' => ['name' => $cs->section?->sectionname ?? '—'],
                    'classroom' => ['name' => $cs->classroom?->name, 'code' => $cs->classroom?->code],
                    'start_time' => $cs->start_time,
                    'end_time' => $cs->end_time,
                    'tap_status' => 'not_held',
                    'tapped_at' => null,
                    'late_minutes' => 0,
                    'channel' => null,
                    'is_adjusted_day' => true,
                ];
            }

            $isAdjustedDay = isset($adjustedEntries[$cs->id]);
            $effectiveStart = $isAdjustedDay ? $adjustedEntries[$cs->id]['start_time'] : $cs->start_time;
            $effectiveEnd = $isAdjustedDay ? $adjustedEntries[$cs->id]['end_time'] : $cs->end_time;

            $startTime = Carbon::parse(today()->toDateString().' '.$effectiveStart);
            $endTime = Carbon::parse(today()->toDateString().' '.$effectiveEnd);

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
                'start_time' => $effectiveStart,
                'end_time' => $effectiveEnd,
                'tap_status' => $displayStatus,
                'tapped_at' => $tap?->tapped_at?->format('H:i'),
                'late_minutes' => $tap?->late_minutes ?? 0,
                'is_adjusted_day' => $isAdjustedDay,
                'channel' => $tap?->channel,
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
