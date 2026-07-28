<?php

namespace App\Http\Controllers\HomeroomAttendance;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\Section;
use App\Models\HomeroomAttendance\ActivityLog;
use App\Services\HomeroomAttendance\ActivityAttendanceService;
use App\Services\HomeroomAttendance\RosterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ActivityAttendanceController extends Controller
{
    public function __construct(
        private RosterService $roster,
        private ActivityAttendanceService $activity,
    ) {
    }

    // ── GET /homeroom-attendance/activities ────────────────────────────────────

    public function index(Request $request)
    {
        $user = Auth::user();
        $sy = $this->roster->currentSchoolYear();
        $term = $this->roster->currentAcademicTerm($sy);

        $sections = $this->roster->accessibleSections($user, $term->id);
        abort_if($sections->isEmpty(), 403, 'You have no homeroom advisory sections assigned.');

        $sectionId = (int) $request->query('section', $sections->first()->id);
        abort_unless($sections->pluck('id')->contains($sectionId), 403, 'You do not have access to that section.');

        $logs = ActivityLog::where('section_id', $sectionId)
            ->withCount('records')
            ->orderByDesc('activity_date')
            ->get(['id', 'section_id', 'title', 'activity_date', 'is_off_campus']);

        return Inertia::render('HomeroomAttendance/Activities/Index', [
            'sections'  => $sections->map(fn (Section $s) => ['id' => $s->id, 'name' => $s->sectionname, 'level' => $s->levelid]),
            'sectionId' => $sectionId,
            'logs'      => $logs,
        ]);
    }

    // ── GET /homeroom-attendance/activities/create ─────────────────────────────

    public function create(Request $request)
    {
        $user = Auth::user();
        $sy = $this->roster->currentSchoolYear();
        $term = $this->roster->currentAcademicTerm($sy);

        $sections = $this->roster->accessibleSections($user, $term->id);
        abort_if($sections->isEmpty(), 403, 'You have no homeroom advisory sections assigned.');

        $sectionId = (int) $request->query('section', $sections->first()->id);
        abort_unless($sections->pluck('id')->contains($sectionId), 403, 'You do not have access to that section.');

        $students = $this->roster->studentsForSection($sectionId, $sy->id);

        return Inertia::render('HomeroomAttendance/Activities/Create', [
            'sections'  => $sections->map(fn (Section $s) => ['id' => $s->id, 'name' => $s->sectionname, 'level' => $s->levelid]),
            'sectionId' => $sectionId,
            'students'  => $students->map(fn ($s) => [
                'student_id' => $s->id,
                'name'       => trim("{$s->lastname}, {$s->firstname} {$s->middlename}"),
                'sex'        => $s->sex,
            ])->values(),
        ]);
    }

    // ── POST /homeroom-attendance/activities ───────────────────────────────────

    public function store(Request $request)
    {
        $user = Auth::user();
        $sy = $this->roster->currentSchoolYear();
        $term = $this->roster->currentAcademicTerm($sy);

        $data = $request->validate([
            'section_id'              => ['required', 'integer'],
            'title'                    => ['required', 'string', 'max:255'],
            'activity_date'            => ['required', 'date'],
            'is_off_campus'            => ['sometimes', 'boolean'],
            'rows'                      => ['required', 'array', 'min:1'],
            'rows.*.student_id'        => ['required', 'integer'],
            'rows.*.am_status'         => ['required', 'in:present,absent'],
            'rows.*.pm_status'         => ['required', 'in:present,absent'],
            'rows.*.remarks'           => ['nullable', 'string', 'max:255'],
        ]);

        abort_unless(
            $this->roster->canAccessSection($user, (int) $data['section_id'], $term->id),
            403,
            'You do not have access to that section.',
        );

        $this->activity->record(
            (int) $data['section_id'],
            $sy->id,
            $data['title'],
            $data['activity_date'],
            $data['is_off_campus'] ?? false,
            $user->id,
            $data['rows'],
        );

        return redirect()
            ->route('homeroom-attendance.activities.index', ['section' => $data['section_id']])
            ->with('success', 'Activity attendance recorded.');
    }
}
