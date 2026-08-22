<?php

namespace App\Http\Controllers;

use App\Models\StudentProfileChangeRequest;
use App\Services\StudentProfileChangeRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StudentProfileChangeRequestController extends Controller
{
    public function index()
    {
        $this->authorize('manage-students');

        $requests = StudentProfileChangeRequest::with('reviewer')
            ->orderByRaw("status = 'pending' desc")
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($r) {
                $student = DB::table('students')->where('id', $r->student_id)->first(['firstname', 'lastname', 'pisaysystemID']);

                return [
                    'id' => $r->id,
                    'student_name' => $student ? trim("{$student->lastname}, {$student->firstname}") : 'Unknown',
                    'pisaysystemID' => $student->pisaysystemID ?? null,
                    'requested_changes' => $r->requested_changes,
                    'status' => $r->status,
                    'reviewer' => $r->reviewer?->name,
                    'reviewed_at' => $r->reviewed_at?->format('M d, Y g:i A'),
                    'review_notes' => $r->review_notes,
                    'submitted_at' => $r->created_at->format('M d, Y g:i A'),
                ];
            });

        return Inertia::render('Students/ChangeRequests/Index', [
            'requests' => $requests,
        ]);
    }

    public function approve(StudentProfileChangeRequest $changeRequest, StudentProfileChangeRequestService $service)
    {
        $this->authorize('manage-students');
        abort_unless($changeRequest->status === 'pending', 422, 'This request has already been reviewed.');

        $service->approve($changeRequest, auth()->user());

        return back()->with('success', 'Update approved and applied to the student record.');
    }

    public function reject(Request $request, StudentProfileChangeRequest $changeRequest, StudentProfileChangeRequestService $service)
    {
        $this->authorize('manage-students');
        abort_unless($changeRequest->status === 'pending', 422, 'This request has already been reviewed.');

        $validated = $request->validate(['review_notes' => 'required|string|max:500']);

        $service->reject($changeRequest, auth()->user(), $validated['review_notes']);

        return back()->with('success', 'Update request rejected.');
    }
}
