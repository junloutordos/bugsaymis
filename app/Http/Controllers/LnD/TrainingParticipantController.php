<?php

namespace App\Http\Controllers\LnD;

use App\Http\Controllers\Controller;
use App\Models\TrainingParticipant;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TrainingParticipantController extends Controller
{
    // ── Nominate (bulk or single) ──────────────────────────────────────────────

    public function store(Request $request, TrainingSession $trainingSession)
    {
        $this->authorize('lnd.create');

        $validated = $request->validate([
            'employee_ids'   => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['required', 'exists:users,id'],
        ]);

        $added = 0;
        foreach ($validated['employee_ids'] as $employeeId) {
            $exists = $trainingSession->participants()
                ->where('employee_id', $employeeId)
                ->exists();

            if ($exists) continue;

            if ($trainingSession->max_participants
                && $trainingSession->participants()->count() >= $trainingSession->max_participants
            ) {
                return back()->withErrors(['error' => 'Session is already at maximum capacity.']);
            }

            $trainingSession->participants()->create([
                'employee_id'       => $employeeId,
                'nomination_status' => 'nominated',
                'attendance_status' => 'registered',
                'completion_status' => 'pending',
            ]);
            $added++;
        }

        return back()->with('success', "{$added} participant(s) nominated.");
    }

    // ── Approve / Disapprove nomination ───────────────────────────────────────

    public function approveNomination(Request $request, TrainingParticipant $trainingParticipant)
    {
        $this->authorize('lnd.approve');

        $validated = $request->validate([
            'action'  => ['required', Rule::in(['approved', 'disapproved'])],
            'remarks' => ['nullable', 'string'],
        ]);

        $trainingParticipant->update([
            'nomination_status' => $validated['action'],
            'approved_by'       => Auth::id(),
            'approved_at'       => now(),
            'remarks'           => $validated['remarks'] ?? null,
        ]);

        $label = $validated['action'] === 'approved' ? 'approved' : 'disapproved';

        return back()->with('success', "Nomination {$label}.");
    }

    // ── Upload certificate ─────────────────────────────────────────────────────

    public function uploadCertificate(Request $request, TrainingParticipant $trainingParticipant)
    {
        $this->authorize('lnd.edit');

        $request->validate([
            'certificate'        => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'certificate_issued_date' => ['nullable', 'date'],
        ]);

        // Delete old certificate if any
        if ($trainingParticipant->certificate_path) {
            Storage::disk('public')->delete($trainingParticipant->certificate_path);
        }

        $path = $request->file('certificate')->store('lnd/certificates', 'public');

        $trainingParticipant->update([
            'certificate_path'        => $path,
            'certificate_issued_date' => $request->input('certificate_issued_date'),
            'completion_status'       => 'completed',
        ]);

        return back()->with('success', 'Certificate uploaded.');
    }

    // ── Remove participant ─────────────────────────────────────────────────────

    public function destroy(TrainingParticipant $trainingParticipant)
    {
        $this->authorize('lnd.delete');

        if ($trainingParticipant->nomination_status === 'approved') {
            return back()->withErrors(['error' => 'Cannot remove an already-approved participant.']);
        }

        $trainingParticipant->delete();

        return back()->with('success', 'Participant removed.');
    }

    // ── My trainings (employee self-view) ──────────────────────────────────────

    public function myTrainings(Request $request)
    {
        $this->authorize('lnd.view');

        $records = TrainingParticipant::with([
                'session.program:id,title,type,provider',
                'evaluation',
            ])
            ->forEmployee(Auth::id())
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return inertia('LnD/MyTrainings/Index', [
            'records' => $records,
        ]);
    }
}
