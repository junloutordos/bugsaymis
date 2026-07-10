<?php

namespace App\Http\Controllers\CID;

use App\Http\Controllers\Controller;
use App\Models\CID\Competition;
use App\Models\CID\CompetitionParticipant;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CompetitionController extends Controller
{
    public function index(Request $request): Response
    {
        $competitions = Competition::with([
                'participants.user:id,name,position',
                'participants.student:id,firstname,lastname,middlename,batch',
                'schoolYear:id,name',
                'createdBy:id,name',
                'itJobRequest:id,itjr_no',
            ])
            ->orderByDesc('date_from')
            ->get()
            ->map(fn ($c) => $this->mapCompetition($c));

        $employees = User::where('status', '<>', 'inactive')
            ->orderBy('name')
            ->get(['id', 'name', 'position']);

        $schoolYears = SchoolYear::orderByDesc('name')->get(['id', 'name', 'is_current']);

        return Inertia::render('CID/Competitions/Index', [
            'competitions' => $competitions,
            'employees'    => $employees,
            'schoolYears'  => $schoolYears,
            'levels'       => Competition::LEVELS,
            'canManage'    => $this->canManage(),
        ]);
    }

    /** Student picker — search enrolled students by name (min 2 chars). */
    public function searchStudents(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:2|max:80']);

        $q = trim($request->q);

        $students = Student::where('status', 'Enrolled')
            ->where(fn ($query) => $query
                ->where('lastname', 'like', "%{$q}%")
                ->orWhere('firstname', 'like', "%{$q}%")
                ->orWhere(DB::raw("CONCAT(firstname, ' ', lastname)"), 'like', "%{$q}%"))
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->limit(20)
            ->get(['id', 'firstname', 'lastname', 'middlename', 'batch'])
            ->map(fn ($s) => [
                'id'    => $s->id,
                'name'  => $s->full_name,
                'batch' => $s->batch,
            ]);

        return response()->json($students);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateCompetition($request);

        $competition = DB::transaction(function () use ($data) {
            $competition = Competition::create([
                ...$this->competitionAttributes($data),
                'school_year_id' => SchoolYear::where('is_current', true)->value('id'),
                'created_by'     => Auth::id(),
            ]);

            $this->syncParticipants($competition, $data, coachId: Auth::id());

            return $competition;
        });

        $message = 'Competition record added.';

        // Optional auto-filed ITJR — runs after the competition commits so a
        // filing failure never rolls back the record itself.
        if (! empty($data['file_itjr'])) {
            [$jobRequest, $error] = $this->fileGraphicDesignItjr($competition);
            $message .= $jobRequest
                ? " IT Job Request {$jobRequest->itjr_no} filed for Graphic Design."
                : " However, the Graphic Design ITJR could not be filed: {$error}";
        }

        return back()->with('success', $message);
    }

    public function update(Request $request, Competition $competition): RedirectResponse
    {
        if (! $this->canTouch($competition)) {
            abort(403);
        }

        $data = $this->validateCompetition($request);

        DB::transaction(function () use ($competition, $data) {
            $competition->update($this->competitionAttributes($data));

            // Coach credit stays with the original submitter even when a
            // manage-level user edits the record.
            $existingCoach = $competition->participants()->where('role', 'coach')->value('user_id');
            $this->syncParticipants($competition, $data, coachId: $existingCoach ?? $competition->created_by);
        });

        return back()->with('success', 'Competition record updated.');
    }

    public function destroy(Competition $competition): RedirectResponse
    {
        if (! $this->canTouch($competition)) {
            abort(403);
        }

        $competition->delete(); // participants cascade

        return back()->with('success', 'Competition record deleted.');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function canManage(): bool
    {
        $user = Auth::user();

        return $user->isSuperAdmin() || $user->hasPermission('cid.competitions.manage');
    }

    private function canTouch(Competition $competition): bool
    {
        return $this->canManage() || (int) $competition->created_by === (int) Auth::id();
    }

    private function validateCompetition(Request $request): array
    {
        return $request->validate([
            'title'            => 'required|string|max:255',
            'competition_type' => 'required|in:employee,student',
            'organizer'        => 'nullable|string|max:255',
            'venue'            => 'nullable|string|max:255',
            'level'            => 'required|in:campus,inter_campus,regional,national,international',
            'date_from'        => 'required|date',
            'date_to'          => 'nullable|date|after_or_equal:date_from',
            'represents_pshs'  => 'boolean',
            'remarks'          => 'nullable|string|max:1000',
            'file_itjr'        => 'boolean',

            // Employee competitions — employees with their awards
            'employees'            => 'required_if:competition_type,employee|array',
            'employees.*.user_id'  => 'required|exists:users,id',
            'employees.*.award'    => 'nullable|string|max:150',

            // Student competitions — students with their awards + co-coaches
            'students'               => 'required_if:competition_type,student|array',
            'students.*.student_id'  => 'required|integer',
            'students.*.award'       => 'nullable|string|max:150',
            'co_coaches'             => 'nullable|array',
            'co_coaches.*'           => 'integer|exists:users,id',
        ], [
            'employees.required_if' => 'Add at least one employee participant.',
            'students.required_if'  => 'Add at least one student participant.',
        ]);
    }

    private function competitionAttributes(array $data): array
    {
        return [
            'title'            => $data['title'],
            'competition_type' => $data['competition_type'],
            'organizer'        => $data['organizer'] ?? null,
            'venue'            => $data['venue'] ?? null,
            'level'            => $data['level'],
            'date_from'        => $data['date_from'],
            'date_to'          => $data['date_to'] ?? null,
            'represents_pshs'  => (bool) ($data['represents_pshs'] ?? false),
            'remarks'          => $data['remarks'] ?? null,
        ];
    }

    /**
     * File a Graphic Design ITJR for a freshly created competition via the
     * shared ITJR creation path (approver resolution, MIS auto-assign,
     * numbering, notifications).
     *
     * @return array{0: ?\App\Models\ITJobRequest, 1: ?string} [jobRequest, error]
     */
    private function fileGraphicDesignItjr(Competition $competition): array
    {
        $competition->load([
            'participants.user:id,name',
            'participants.student:id,firstname,lastname,middlename,batch',
        ]);

        $lines = [
            'Requesting a congratulatory graphic (poster / social media card) for the competition below.',
            '',
            "Competition: {$competition->title}",
            "Level: {$competition->level_label}",
            'Date: ' . $competition->date_from->format('F j, Y')
                . ($competition->date_to && ! $competition->date_to->equalTo($competition->date_from)
                    ? ' – ' . $competition->date_to->format('F j, Y') : ''),
        ];

        if ($competition->organizer) {
            $lines[] = "Organizer: {$competition->organizer}";
        }
        if ($competition->venue) {
            $lines[] = "Venue: {$competition->venue}";
        }

        $lines[] = '';
        $lines[] = 'Participants & Awards:';
        foreach ($competition->participants->where('role', 'participant') as $p) {
            $name    = $p->user_id ? $p->user?->name : $p->student?->full_name;
            $lines[] = '- ' . ($name ?? 'Unknown') . ' — ' . ($p->award ?: 'Participant');
        }

        $coach = $competition->participants->firstWhere('role', 'coach');
        if ($coach?->user) {
            $lines[] = "Coach: {$coach->user->name}";
        }
        foreach ($competition->participants->where('role', 'co_coach') as $cc) {
            if ($cc->user) {
                $lines[] = "Co-coach: {$cc->user->name}";
            }
        }

        try {
            $jobRequest = app(\App\Http\Controllers\ITJobRequestController::class)->createJobRequest([
                'category'    => 'Graphic Design',
                'title'       => 'Graphic design — ' . $competition->title,
                'description' => implode("\n", $lines),
                'priority'    => 'normal',
            ], Auth::user());
        } catch (\RuntimeException $e) {
            return [null, $e->getMessage()];
        }

        $competition->update(['it_job_request_id' => $jobRequest->id]);

        return [$jobRequest, null];
    }

    /** Replace all participant rows from the validated payload. */
    private function syncParticipants(Competition $competition, array $data, int $coachId): void
    {
        $competition->participants()->delete();

        $rows = [];

        if ($data['competition_type'] === 'employee') {
            foreach ($data['employees'] as $e) {
                $rows[] = ['user_id' => $e['user_id'], 'student_id' => null,
                           'role' => 'participant', 'award' => $e['award'] ?? null];
            }
        } else {
            foreach ($data['students'] as $s) {
                $rows[] = ['user_id' => null, 'student_id' => $s['student_id'],
                           'role' => 'participant', 'award' => $s['award'] ?? null];
            }

            $rows[] = ['user_id' => $coachId, 'student_id' => null, 'role' => 'coach', 'award' => null];

            foreach (array_unique($data['co_coaches'] ?? []) as $userId) {
                if ((int) $userId === (int) $coachId) {
                    continue;
                }
                $rows[] = ['user_id' => $userId, 'student_id' => null, 'role' => 'co_coach', 'award' => null];
            }
        }

        foreach ($rows as $row) {
            $competition->participants()->create($row);
        }
    }

    private function mapCompetition(Competition $c): array
    {
        $participants = $c->participants->where('role', 'participant')->values();
        $coach        = $c->participants->firstWhere('role', 'coach');
        $coCoaches    = $c->participants->where('role', 'co_coach')->values();

        return [
            'id'               => $c->id,
            'title'            => $c->title,
            'competition_type' => $c->competition_type,
            'organizer'        => $c->organizer,
            'venue'            => $c->venue,
            'level'            => $c->level,
            'level_label'      => $c->level_label,
            'date_from'        => $c->date_from?->format('Y-m-d'),
            'date_to'          => $c->date_to?->format('Y-m-d'),
            'represents_pshs'  => $c->represents_pshs,
            'school_year'      => $c->schoolYear?->name,
            'remarks'          => $c->remarks,
            'created_by'       => $c->created_by,
            'created_by_name'  => $c->createdBy?->name,
            'itjr_no'          => $c->itJobRequest?->itjr_no,
            'participants'     => $participants->map(fn ($p) => [
                'user_id'    => $p->user_id,
                'student_id' => $p->student_id,
                'name'       => $p->user_id ? $p->user?->name : $p->student?->full_name,
                'batch'      => $p->student?->batch,
                'award'      => $p->award,
            ])->all(),
            'coach'      => $coach?->user ? ['id' => $coach->user->id, 'name' => $coach->user->name] : null,
            'co_coaches' => $coCoaches->map(fn ($p) => [
                'id'   => $p->user_id,
                'name' => $p->user?->name,
            ])->all(),
        ];
    }
}
