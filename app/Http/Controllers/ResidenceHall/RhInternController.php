<?php

namespace App\Http\Controllers\ResidenceHall;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\ResidenceHall\RhApplication;
use App\Models\ResidenceHall\RhIntern;
use App\Models\ResidenceHall\RhRoom;
use App\Services\Discipline\StudentResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RhInternController extends Controller
{
    public function __construct(private StudentResolver $students) {}

    public function index(Request $request)
    {
        $this->authorize('rh.interns.view');

        $user   = $request->user();
        $hall   = $user->rh_hall;
        $syId   = $request->query('school_year_id');
        $status = $request->query('status', 'active');
        $search = trim($request->query('search', ''));

        $schoolYears = SchoolYear::orderByDesc('id')->get(['id', 'name', 'is_current']);
        $currentSy   = $schoolYears->firstWhere('is_current', true);
        $activeSyId  = $syId ?: $currentSy?->id;

        $interns = RhIntern::with(['room:id,residence_hall,room_number,floor'])
            ->when($activeSyId, fn ($q) => $q->where('school_year_id', $activeSyId))
            ->when($hall, fn ($q) => $q->whereHas('room', fn ($r) => $r->where('residence_hall', $hall)))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('created_at', 'desc')
            ->get();

        // Batch-resolve student names
        $studentIds = $interns->pluck('student_id')->unique()->values();
        $studentMap = $this->studentNameMap($studentIds);

        $interns->transform(function ($i) use ($studentMap) {
            $i->student_name = $studentMap[$i->student_id] ?? ('Student #' . $i->student_id);
            return $i;
        });

        // Client-side search on resolved names
        if ($search) {
            $lower = mb_strtolower($search);
            $interns = $interns->filter(
                fn ($i) => str_contains(mb_strtolower($i->student_name), $lower)
            )->values();
        }

        return Inertia::render('ResidenceHall/Interns/Index', [
            'interns'     => $interns,
            'schoolYears' => $schoolYears,
            'activeSyId'  => (int) $activeSyId,
            'filters'     => compact('status', 'search'),
            'myHall'      => $hall,
        ]);
    }

    public function show(RhIntern $rhIntern)
    {
        $this->authorize('rh.interns.view');

        $hall = auth()->user()->rh_hall;
        if ($hall && $rhIntern->room?->residence_hall !== $hall) {
            abort(403);
        }

        $rhIntern->load([
            'room:id,residence_hall,room_number,floor,capacity',
            'waiver',
            'appliances',
            'leavePasses' => fn ($q) => $q->latest()->limit(10),
            'incidents'   => fn ($q) => $q->latest()->limit(10),
            'feeLedger'   => fn ($q) => $q->orderByDesc('period_year')->orderByDesc('period_month'),
        ]);

        $student = $this->students->resolve($rhIntern->student_id);

        // Available rooms for reassignment (same hall, not full)
        $availableRooms = RhRoom::where('residence_hall', $rhIntern->room?->residence_hall ?? ($hall ?? 'BRH'))
            ->where('status', 'active')
            ->withCount(['activeInterns'])
            ->get()
            ->filter(fn ($r) => $r->active_interns_count < $r->capacity || $r->id === $rhIntern->rh_room_id)
            ->values();

        return Inertia::render('ResidenceHall/Interns/Show', [
            'intern'         => $rhIntern,
            'student'        => $student,
            'availableRooms' => $availableRooms,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('rh.interns.manage');

        $validated = $request->validate([
            'student_id'           => ['required', 'integer'],
            'school_year_id'       => ['required', 'integer'],
            'rh_room_id'           => ['required', 'exists:rh_rooms,id'],
            'bed_number'           => ['nullable', 'string', 'max:10'],
            'check_in_date'        => ['nullable', 'date'],
            'lodging_fee_monthly'  => ['required', 'numeric', 'min:0'],
            'contract_start'       => ['nullable', 'date'],
            'contract_end'         => ['nullable', 'date'],
        ]);

        $room = RhRoom::findOrFail($validated['rh_room_id']);
        $hall = request()->user()->rh_hall;
        if ($hall && $room->residence_hall !== $hall) {
            abort(403);
        }

        if ($room->activeInterns()->count() >= $room->capacity) {
            return back()->with('error', 'Room is already at full capacity.');
        }

        // Prevent duplicate active intern per student/SY
        $exists = RhIntern::where('student_id', $validated['student_id'])
            ->where('school_year_id', $validated['school_year_id'])
            ->whereIn('status', ['active', 'suspended'])
            ->exists();

        if ($exists) {
            return back()->with('error', 'This student already has an active dormer record for the selected school year.');
        }

        RhIntern::create($validated);

        return back()->with('success', 'Dormer enrolled and room assigned.');
    }

    public function update(Request $request, RhIntern $rhIntern)
    {
        $this->authorize('rh.interns.manage');

        $validated = $request->validate([
            'rh_room_id'          => ['nullable', 'exists:rh_rooms,id'],
            'bed_number'          => ['nullable', 'string', 'max:10'],
            'check_in_date'       => ['nullable', 'date'],
            'check_out_date'      => ['nullable', 'date'],
            'lodging_fee_monthly' => ['nullable', 'numeric', 'min:0'],
            'contract_start'      => ['nullable', 'date'],
            'contract_end'        => ['nullable', 'date'],
            'status'              => ['nullable', 'in:active,checked_out,suspended,terminated'],
        ]);

        $rhIntern->update(array_filter($validated, fn ($v) => $v !== null));

        return back()->with('success', 'Dormer record updated.');
    }

    public function checkOut(RhIntern $rhIntern)
    {
        $this->authorize('rh.interns.manage');

        $rhIntern->update([
            'status'         => 'checked_out',
            'check_out_date' => now()->toDateString(),
        ]);

        return back()->with('success', 'Dormer checked out successfully.');
    }

    private function studentNameMap($studentIds): array
    {
        if ($studentIds->isEmpty()) {
            return [];
        }

        return \Illuminate\Support\Facades\DB::table('students')
            ->whereIn('id', $studentIds)
            ->get(['id', 'lastname', 'firstname'])
            ->keyBy('id')
            ->map(fn ($s) => trim($s->lastname . ', ' . $s->firstname))
            ->toArray();
    }
}
