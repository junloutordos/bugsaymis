<?php

namespace App\Http\Controllers\ScienceLab;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\ScienceLab\LabReservation;
use App\Models\ScienceLab\ScienceLabProfile;
use App\Services\ScienceLab\LabControlNumberService;
use App\Services\ScienceLab\LabPdfService;
use App\Traits\SignsLabDocuments;
use App\Models\FacultyLoading\SchoolYear;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class LabReservationController extends Controller
{
    use SignsLabDocuments;

    public function __construct(private LabControlNumberService $controlNumbers) {}

    public function index(Request $request)
    {
        $user = Auth::user();
        $canManage = $user->hasPermission('lab.manage');

        $reservations = LabReservation::with(['room:id,name', 'requester:id,name', 'endorser:id,name', 'approver:id,name'])
            ->when(! $canManage, fn ($q) => $q->where('requested_by_id', $user->id))
            ->when($request->string('status')->value(), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')->get();

        return Inertia::render('ScienceLab/Reservations', [
            'reservations' => $reservations,
            'labRooms'     => $this->labRooms(),
            'schoolYear'   => SchoolYear::where('is_current', true)->first(['id', 'name']),
            'canManage'    => $canManage,
            'hasPin'       => ! empty($user->signature_pin),
            'filters'      => $request->only('status'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'grade_level_section' => ['nullable', 'string', 'max:100'],
            'number_of_students'  => ['nullable', 'integer', 'min:0'],
            'subject'             => ['nullable', 'string', 'max:150'],
            'teacher_in_charge'   => ['nullable', 'string', 'max:150'],
            'date_start'          => ['required', 'date'],
            'date_end'            => ['nullable', 'date', 'after_or_equal:date_start'],
            'time_start'          => ['nullable', 'date_format:H:i'],
            'time_end'            => ['nullable', 'date_format:H:i'],
            'room_id'             => ['nullable', 'integer', 'exists:rooms,id'],
            'requester_type'      => ['required', Rule::in(['teacher', 'student'])],
            'student_group'       => ['nullable', 'array'],
            'student_group.*'     => ['nullable', 'string', 'max:150'],
            'remarks'             => ['nullable', 'string', 'max:1000'],
        ]);

        $data['control_no']      = $this->controlNumbers->next(LabControlNumberService::RESERVATION, 'lab_reservations');
        $data['school_year_id']  = SchoolYear::where('is_current', true)->value('id');
        $data['requested_by_id'] = Auth::id();
        $data['requester_name']  = Auth::user()->name;
        $data['status']          = 'pending';

        $reservation = LabReservation::create($data);

        return back()->with('success', "Reservation {$reservation->control_no} filed.");
    }

    public function endorse(Request $request, LabReservation $reservation)
    {
        $request->validate(['pin' => ['required', 'string']]);
        abort_unless(in_array($reservation->status, ['pending']), 422, 'Reservation is not pending endorsement.');

        $this->verifyLabPin(Auth::user(), $request->input('pin'));

        $reservation->update([
            'endorsed_by_id' => Auth::id(),
            'endorsed_at'    => now(),
            'status'         => 'endorsed',
        ]);
        $this->signLabDocument(Auth::user(), $reservation, 'endorsement', 'Laboratory Reservation Form');

        return back()->with('success', 'Reservation endorsed.');
    }

    public function approve(Request $request, LabReservation $reservation)
    {
        abort_unless(Auth::user()->hasPermission('lab.manage'), 403);
        $request->validate(['pin' => ['required', 'string']]);
        abort_unless(in_array($reservation->status, ['pending', 'endorsed']), 422, 'Reservation cannot be approved from its current state.');

        $this->verifyLabPin(Auth::user(), $request->input('pin'));

        $reservation->update([
            'approved_by_id' => Auth::id(),
            'approved_at'    => now(),
            'status'         => 'approved',
        ]);
        $this->signLabDocument(Auth::user(), $reservation, 'approval', 'Laboratory Reservation Form');

        return back()->with('success', 'Reservation approved and scheduled.');
    }

    public function decline(Request $request, LabReservation $reservation)
    {
        abort_unless(Auth::user()->hasPermission('lab.manage'), 403);
        $data = $request->validate(['decline_reason' => ['required', 'string', 'max:500']]);
        $reservation->update(['status' => 'declined', 'decline_reason' => $data['decline_reason']]);

        return back()->with('success', 'Reservation declined.');
    }

    public function cancel(LabReservation $reservation)
    {
        abort_unless(
            $reservation->requested_by_id === Auth::id() || Auth::user()->hasPermission('lab.manage'),
            403
        );
        abort_if(in_array($reservation->status, ['completed', 'cancelled']), 422, 'Cannot cancel this reservation.');
        $reservation->update(['status' => 'cancelled']);

        return back()->with('success', 'Reservation cancelled.');
    }

    public function complete(LabReservation $reservation)
    {
        abort_unless(Auth::user()->hasPermission('lab.manage'), 403);
        $reservation->update(['status' => 'completed']);

        return back()->with('success', 'Reservation marked completed.');
    }

    public function pdf(LabReservation $reservation, LabPdfService $pdf)
    {
        $reservation->load(['room:id,name', 'requester:id,name', 'endorser:id,name', 'approver:id,name', 'schoolYear:id,name']);

        return $pdf->reservation($reservation);
    }

    private function labRooms()
    {
        $ids = ScienceLabProfile::pluck('room_id');

        return Room::whereIn('id', $ids)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name]);
    }
}
