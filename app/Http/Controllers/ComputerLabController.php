<?php

namespace App\Http\Controllers;

use App\Models\AtlasSentinelRelease;
use App\Models\ComputerLabBooking;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\ICTEquipment;
use App\Models\Room;
use App\Services\ComputerLabSchedulingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class ComputerLabController extends Controller
{
    const LAB_ROWS = 5;
    const LAB_COLS = 6;

    public function index(Request $request)
    {
        $currentTerm = AcademicTerm::where('is_current', true)->first();
        $termId = (int) $request->input('term_id', $currentTerm?->id);
        $term = $termId ? AcademicTerm::with('schoolYear')->find($termId) : null;
        $weekStart = $this->resolveWeekStart($request, $term);

        return Inertia::render('ITJobRequests/ComputerLabs/Index', [
            'labs' => self::summary(),
            'terms' => AcademicTerm::with('schoolYear')->orderByDesc('start_date')->get()->map(fn ($item) => [
                'id' => $item->id,
                'label' => $item->full_label,
                'is_current' => $item->is_current,
            ]),
            'selectedTermId' => $termId ?: null,
            'weekStart' => $weekStart->toDateString(),
            ...$this->calendarPayload($term, $weekStart, $request->integer('lab_id') ?: null),
            'filters' => $request->only(['term_id', 'week_start', 'lab_id']),
            'capabilities' => [
                'canBook' => $this->canBook($request),
                'canManage' => $this->canManage($request),
                'canViewEquipment' => $request->user()->isSuperAdmin() || $request->user()->hasPermission('it.equipment.view'),
            ],
        ]);
    }

    /**
     * Per-lab unit/enrollment/risk counts — shared with the MIS Dashboard's
     * fleet health summary so both stay in sync off one query.
     */
    public static function summary()
    {
        $rooms = Room::where('room_type', 'Computer Laboratory')->orderBy('name')->get();

        $equipments = ICTEquipment::where('category', 'CPU/System Unit')
            ->whereIn('room_id', $rooms->pluck('id'))
            ->with('agentDevice')
            ->get()
            ->groupBy('room_id');

        return $rooms->map(function ($room) use ($equipments) {
            $units = $equipments->get($room->id, collect());

            return [
                'room' => $room,
                'total' => $units->count(),
                'enrolled' => $units->filter(fn ($u) => $u->agentDevice)->count(),
                'critical' => $units->filter(fn ($u) => $u->agentDevice?->risk_tier === 'critical')->count(),
            ];
        })->values();
    }

    public function show(Request $request, Room $room)
    {
        abort_unless($room->room_type === 'Computer Laboratory', 404);

        $equipments = ICTEquipment::where('room_id', $room->id)
            ->where('category', 'CPU/System Unit')
            ->with([
                'agentDevice.healthSnapshot',
                'agentDevice.hardwareInventory',
                'agentDevice.softwareInventory',
                'agentDevice.securityStatus',
            ])
            ->orderBy('lab_seat_row')
            ->orderBy('lab_seat_col')
            ->get();

        return Inertia::render('ITJobRequests/ComputerLabs/Show', [
            'room'               => $room,
            'equipments'         => $equipments,
            'rows'               => self::LAB_ROWS,
            'cols'               => self::LAB_COLS,
            'latestAgentVersion' => AtlasSentinelRelease::latestRelease()?->version,
            'scheduleUrl'        => route('computer-labs.index', ['lab_id' => $room->id]),
        ]);
    }

    public function storeBooking(Request $request, ComputerLabSchedulingService $scheduler): RedirectResponse
    {
        abort_unless($this->canBook($request), 403);

        $data = $request->validate([
            'room_id' => [
                'required',
                Rule::exists('rooms', 'id')->where(fn ($query) => $query->where('room_type', 'Computer Laboratory')),
            ],
            'academic_term_id' => 'required|exists:academic_terms,id',
            'booking_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'title' => 'required|string|max:180',
            'purpose' => 'required|string|max:2000',
        ]);

        $term = AcademicTerm::findOrFail($data['academic_term_id']);
        $date = Carbon::parse($data['booking_date']);
        if (! $term->start_date || ! $term->end_date) {
            return back()->withErrors(['academic_term_id' => 'Set the academic term start and end dates before accepting laboratory bookings.']);
        }
        if ($date->lt($term->start_date) || $date->gt($term->end_date)) {
            return back()->withErrors(['booking_date' => 'The booking date must fall within the selected academic term.']);
        }

        if ($scheduler->conflictsFor(
            (int) $data['room_id'],
            $date,
            $data['start_time'],
            $data['end_time'],
        )->isNotEmpty()) {
            return back()->withErrors(['booking' => 'That laboratory is already occupied during the requested period.']);
        }

        ComputerLabBooking::create([
            ...$data,
            'day_of_week' => $date->format('l'),
            'booking_type' => 'other',
            'requested_by' => Auth::id(),
            'status' => 'pending',
        ]);

        return back()->with('success', 'Computer laboratory booking submitted for approval.');
    }

    public function approveBooking(
        Request $request,
        ComputerLabBooking $booking,
        ComputerLabSchedulingService $scheduler,
    ): RedirectResponse {
        abort_unless($this->canManage($request) && ! $booking->isPriorityClass(), 403);
        $scheduler->approve($booking, (int) Auth::id());

        return back()->with('success', 'Computer laboratory booking approved.');
    }

    public function rejectBooking(Request $request, ComputerLabBooking $booking): RedirectResponse
    {
        abort_unless($this->canManage($request) && ! $booking->isPriorityClass(), 403);

        $data = $request->validate(['reason' => 'nullable|string|max:1000']);
        $booking->update([
            'status' => 'rejected',
            'decision_by' => Auth::id(),
            'decided_at' => now(),
            'conflict_note' => $data['reason'] ?? null,
        ]);

        return back()->with('success', 'Computer laboratory booking rejected.');
    }

    public function cancelBooking(Request $request, ComputerLabBooking $booking): RedirectResponse
    {
        abort_unless(! $booking->isPriorityClass(), 403);
        abort_unless($this->canManage($request) || (int) $booking->requested_by === (int) Auth::id(), 403);
        abort_if(in_array($booking->status, ['rejected', 'cancelled'], true), 422, 'This booking is already closed.');

        $booking->update([
            'status' => 'cancelled',
            'decision_by' => $this->canManage($request) ? Auth::id() : null,
            'decided_at' => now(),
        ]);

        return back()->with('success', 'Computer laboratory booking cancelled.');
    }

    public function moveBooking(
        Request $request,
        ComputerLabBooking $booking,
        ComputerLabSchedulingService $scheduler,
    ): RedirectResponse {
        abort_unless($this->canManage($request), 403);

        $data = $request->validate([
            'room_id' => [
                'required',
                Rule::exists('rooms', 'id')->where(fn ($query) => $query->where('room_type', 'Computer Laboratory')),
            ],
        ]);

        $scheduler->moveToRoom($booking, (int) $data['room_id']);

        return back()->with('success', 'Computer laboratory schedule moved successfully.');
    }

    public function synchronize(
        Request $request,
        ComputerLabSchedulingService $scheduler,
    ): RedirectResponse {
        abort_unless($this->canManage($request), 403);
        $data = $request->validate(['academic_term_id' => 'required|exists:academic_terms,id']);
        $result = $scheduler->synchronizeTerm((int) $data['academic_term_id']);

        $message = "{$result['confirmed']} priority class reservation(s) synchronized.";
        if ($result['unassigned']) {
            $message .= " {$result['unassigned']} class(es) still need a laboratory.";
        }
        if ($result['displaced']) {
            $message .= " {$result['displaced']} other booking(s) returned to pending after a timetable change.";
        }

        return back()->with('success', $message);
    }

    public function updateSeat(Request $request, ICTEquipment $equipment)
    {
        $validator = Validator::make($request->all(), [
            'lab_seat_row' => 'nullable|integer|min:1|max:' . self::LAB_ROWS,
            'lab_seat_col' => 'nullable|integer|min:1|max:' . self::LAB_COLS,
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $data = $validator->validated();

        if ($data['lab_seat_row'] !== null && $data['lab_seat_col'] !== null) {
            $occupied = ICTEquipment::where('room_id', $equipment->room_id)
                ->where('id', '<>', $equipment->id)
                ->where('lab_seat_row', $data['lab_seat_row'])
                ->where('lab_seat_col', $data['lab_seat_col'])
                ->exists();

            if ($occupied) {
                return response()->json(['message' => 'That seat is already taken.'], 422);
            }
        }

        $equipment->update($data);

        return response()->json(['equipment' => $equipment]);
    }

    private function calendarPayload(?AcademicTerm $term, Carbon $weekStart, ?int $labId): array
    {
        $days = collect(range(0, 5))->map(function ($offset) use ($weekStart) {
            $date = $weekStart->copy()->addDays($offset);

            return [
                'name' => $date->format('l'),
                'date' => $date->toDateString(),
                'label' => $date->format('M j'),
            ];
        });

        if (! $term) {
            return ['days' => $days, 'bookings' => [], 'unassigned' => [], 'pendingBookings' => []];
        }

        $relations = [
            'room:id,name,code,capacity',
            'requester:id,name',
            'classSchedule.subject:id,code,name',
            'classSchedule.faculty:id,name',
            'classSchedule.section:id,sectionname,levelid',
        ];

        $priority = ComputerLabBooking::with($relations)
            ->where('academic_term_id', $term->id)
            ->where('booking_type', 'priority_class')
            ->when($labId, fn ($query) => $query->where('room_id', $labId))
            ->get();

        $other = ComputerLabBooking::with($relations)
            ->where('academic_term_id', $term->id)
            ->where('booking_type', 'other')
            ->whereBetween('booking_date', [$weekStart->toDateString(), $weekStart->copy()->addDays(5)->toDateString()])
            ->when($labId, fn ($query) => $query->where('room_id', $labId))
            ->get();

        $dateByDay = $days->pluck('date', 'name');
        $visibleOther = $other->where('status', 'approved');
        if ($this->canManage(request())) {
            $visibleOther = $visibleOther->concat($other->where('status', 'pending'));
        } else {
            $visibleOther = $visibleOther->concat($other->where('status', 'pending')->where('requested_by', Auth::id()));
        }

        $bookings = $priority->where('status', 'confirmed')
            ->filter(fn ($booking) => $dateByDay->has($booking->day_of_week))
            ->filter(function ($booking) use ($term, $dateByDay) {
                $date = Carbon::parse($dateByDay->get($booking->day_of_week));

                return ! $term->start_date || ! $term->end_date
                    || $date->betweenIncluded($term->start_date, $term->end_date);
            })
            ->concat($visibleOther)
            ->sortBy(fn ($booking) => $booking->booking_date?->format('Y-m-d').$booking->start_time)
            ->map(fn ($booking) => $this->bookingArray(
                $booking,
                $booking->booking_date?->format('Y-m-d') ?? $dateByDay->get($booking->day_of_week),
            ))
            ->values();

        return [
            'days' => $days,
            'bookings' => $bookings,
            'unassigned' => $priority->where('status', 'unassigned')->map(fn ($booking) => $this->bookingArray($booking, null))->values(),
            'pendingBookings' => $this->canManage(request())
                ? $other->where('status', 'pending')->map(fn ($booking) => $this->bookingArray($booking, $booking->booking_date?->format('Y-m-d')))->values()
                : [],
        ];
    }

    private function bookingArray(ComputerLabBooking $booking, ?string $date): array
    {
        return [
            'id' => $booking->id,
            'room_id' => $booking->room_id,
            'room_name' => $booking->room?->name,
            'date' => $date,
            'day_of_week' => $booking->day_of_week,
            'start_time' => substr((string) $booking->start_time, 0, 5),
            'end_time' => substr((string) $booking->end_time, 0, 5),
            'booking_type' => $booking->booking_type,
            'title' => $booking->title,
            'purpose' => $booking->purpose,
            'status' => $booking->status,
            'conflict_note' => $booking->conflict_note,
            'requester' => $booking->requester?->name,
            'faculty' => $booking->classSchedule?->faculty?->name,
            'section' => $booking->classSchedule?->section?->sectionname,
            'subject' => $booking->classSchedule?->subject?->name,
            'can_cancel' => ! $booking->isPriorityClass()
                && ($this->canManage(request()) || (int) $booking->requested_by === (int) Auth::id())
                && ! in_array($booking->status, ['rejected', 'cancelled'], true),
            'can_move' => $this->canManage(request())
                && (($booking->isPriorityClass() && $booking->status === 'confirmed')
                    || (! $booking->isPriorityClass() && $booking->status === 'approved')),
        ];
    }

    private function resolveWeekStart(Request $request, ?AcademicTerm $term): Carbon
    {
        if ($request->filled('week_start')) {
            return Carbon::parse((string) $request->string('week_start'))->startOfWeek(Carbon::MONDAY);
        }

        $today = today();
        if ($term?->start_date && $term?->end_date && ! $today->betweenIncluded($term->start_date, $term->end_date)) {
            return $term->start_date->copy()->startOfWeek(Carbon::MONDAY);
        }

        return $today->startOfWeek(Carbon::MONDAY);
    }

    private function canBook(Request $request): bool
    {
        return $request->user()->isSuperAdmin()
            || $request->user()->hasAnyPermission(['computer_labs.book', 'computer_labs.manage']);
    }

    private function canManage(Request $request): bool
    {
        return $request->user()->isSuperAdmin()
            || $request->user()->hasAnyPermission(['computer_labs.manage', 'faculty_loading.manage']);
    }
}
