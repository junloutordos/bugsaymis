<?php

namespace App\Http\Controllers\LnD;

use App\Http\Controllers\Controller;
use App\Models\LearningProgram;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TrainingSessionController extends Controller
{
    // ── Index ──────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $this->authorize('lnd.view');

        $query = TrainingSession::with(['program:id,title,type'])
            ->withCount(['participants', 'participants as attended_count' => fn ($q) =>
                $q->where('attendance_status', 'attended')
            ]);

        if ($programId = $request->input('program_id')) {
            $query->where('learning_program_id', $programId);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($mode = $request->input('mode')) {
            $query->where('mode', $mode);
        }

        if ($from = $request->input('from')) {
            $query->where('session_date', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->where('session_date', '<=', $to);
        }

        $sessions = $query->orderByDesc('session_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $programs = LearningProgram::active()
            ->orderBy('title')
            ->get(['id', 'title', 'type']);

        return Inertia::render('LnD/TrainingSessions/Index', [
            'sessions' => $sessions,
            'programs' => $programs,
            'filters'  => $request->only(['program_id', 'status', 'mode', 'from', 'to']),
        ]);
    }

    // ── Store ──────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $this->authorize('lnd.create');

        $validated = $request->validate([
            'learning_program_id' => ['required', 'exists:learning_programs,id'],
            'session_date'        => ['required', 'date'],
            'start_time'          => ['required', 'date_format:H:i'],
            'end_time'            => ['required', 'date_format:H:i', 'after:start_time'],
            'venue'               => ['nullable', 'string', 'max:255'],
            'mode'                => ['required', Rule::in(['face_to_face', 'online', 'blended'])],
            'facilitator'         => ['nullable', 'string', 'max:255'],
            'max_participants'    => ['nullable', 'integer', 'min:1'],
            'status'              => ['required', Rule::in(['scheduled', 'ongoing', 'completed', 'cancelled'])],
            'remarks'             => ['nullable', 'string'],
        ]);

        $session = TrainingSession::create($validated);

        return back()->with('success', "Training session on {$session->session_date->format('M d, Y')} created.");
    }

    // ── Show ───────────────────────────────────────────────────────────────────

    public function show(TrainingSession $trainingSession)
    {
        $this->authorize('lnd.view');

        $trainingSession->load([
            'program:id,title,type,competency_area,provider',
            'participants' => fn ($q) => $q->with([
                'employee:id,name,email',
                'approvedBy:id,name',
                'evaluation',
            ])->orderBy('nomination_status'),
        ]);

        return Inertia::render('LnD/TrainingSessions/Show', [
            'session' => $trainingSession,
        ]);
    }

    // ── Update ─────────────────────────────────────────────────────────────────

    public function update(Request $request, TrainingSession $trainingSession)
    {
        $this->authorize('lnd.edit');

        $validated = $request->validate([
            'learning_program_id' => ['required', 'exists:learning_programs,id'],
            'session_date'        => ['required', 'date'],
            'start_time'          => ['required', 'date_format:H:i'],
            'end_time'            => ['required', 'date_format:H:i', 'after:start_time'],
            'venue'               => ['nullable', 'string', 'max:255'],
            'mode'                => ['required', Rule::in(['face_to_face', 'online', 'blended'])],
            'facilitator'         => ['nullable', 'string', 'max:255'],
            'max_participants'    => ['nullable', 'integer', 'min:1'],
            'status'              => ['required', Rule::in(['scheduled', 'ongoing', 'completed', 'cancelled'])],
            'remarks'             => ['nullable', 'string'],
        ]);

        $trainingSession->update($validated);

        return back()->with('success', 'Training session updated.');
    }

    // ── Destroy ────────────────────────────────────────────────────────────────

    public function destroy(TrainingSession $trainingSession)
    {
        $this->authorize('lnd.delete');

        if ($trainingSession->participants()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete a session that already has participants.']);
        }

        $trainingSession->delete();

        return back()->with('success', 'Training session deleted.');
    }

    // ── Mark Attendance ────────────────────────────────────────────────────────

    public function markAttendance(Request $request, TrainingSession $trainingSession)
    {
        $this->authorize('lnd.edit');

        $validated = $request->validate([
            'attendance' => ['required', 'array'],
            'attendance.*.participant_id' => ['required', 'exists:training_participants,id'],
            'attendance.*.status'         => ['required', Rule::in(['attended', 'absent', 'excused'])],
        ]);

        foreach ($validated['attendance'] as $entry) {
            $trainingSession->participants()
                ->where('id', $entry['participant_id'])
                ->update(['attendance_status' => $entry['status']]);
        }

        return back()->with('success', 'Attendance updated.');
    }

    // ── Complete Session ───────────────────────────────────────────────────────

    public function complete(TrainingSession $trainingSession)
    {
        $this->authorize('lnd.edit');

        if ($trainingSession->status === 'completed') {
            return back()->withErrors(['error' => 'Session is already completed.']);
        }

        $trainingSession->update(['status' => 'completed']);

        // Auto-set completion status for attended participants
        $trainingSession->participants()
            ->where('attendance_status', 'attended')
            ->where('completion_status', 'pending')
            ->update(['completion_status' => 'completed']);

        return back()->with('success', 'Session marked as completed. Attended participants set to completed.');
    }
}
