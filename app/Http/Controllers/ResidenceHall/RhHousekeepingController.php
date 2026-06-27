<?php

namespace App\Http\Controllers\ResidenceHall;

use App\Http\Controllers\Controller;
use App\Models\ResidenceHall\RhHousekeepingCheck;
use App\Models\ResidenceHall\RhHousekeepingConforme;
use App\Models\ResidenceHall\RhRoom;
use App\Models\ResidenceHall\RhIntern;
use App\Services\Discipline\StudentResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RhHousekeepingController extends Controller
{
    const ITEMS = [
        'floor'          => 'Floor',
        'walls_ceiling'  => 'Walls / Ceiling',
        'windows_sills'  => 'Windows & Sills',
        'doors'          => 'Doors',
        'furniture'      => 'Furniture & Fixtures',
        'comfort_room'   => 'Comfort Room',
        'shower'         => 'Shower Area',
        'sink'           => 'Sink / Lavatory',
        'trash_bins'     => 'Trash Bins',
        'personal_items' => 'Personal Items',
        'electrical'     => 'Electrical Fixtures',
        'beds'           => 'Beds / Mattresses',
    ];

    private function hallScope(): ?string
    {
        $user = Auth::user();
        if ($user->isSuperAdmin()) return null;
        return $user->rh_hall ?: null;
    }

    public function index(Request $request)
    {
        $this->authorize('rh.housekeeping.manage');

        $hall      = $this->hallScope();
        $checkDate = $request->input('check_date', now()->toDateString());

        $rooms = RhRoom::when($hall, fn($q) => $q->where('residence_hall', $hall))
            ->where('status', 'active')
            ->orderBy('residence_hall')
            ->orderBy('room_number')
            ->get();

        $checks = RhHousekeepingCheck::with(['inspector', 'conformes.intern'])
            ->whereIn('rh_room_id', $rooms->pluck('id'))
            ->where('check_date', $checkDate)
            ->get()
            ->keyBy('rh_room_id');

        $roomData = $rooms->map(function ($room) use ($checks) {
            $check     = $checks->get($room->id);
            $internIds = $room->activeInterns()->pluck('id');
            return [
                'id'             => $room->id,
                'residence_hall' => $room->residence_hall,
                'room_number'    => $room->room_number,
                'floor'          => $room->floor,
                'capacity'       => $room->capacity,
                'check'          => $check ? [
                    'id'            => $check->id,
                    'scores'        => $check->scores,
                    'total_score'   => $check->total_score,
                    'average_score' => $check->average_score,
                    'passed'        => $check->passed,
                    'inspector'     => $check->inspector?->name,
                    'conforme_ids'  => $check->conformes->pluck('rh_intern_id'),
                ] : null,
                'intern_ids'     => $internIds,
            ];
        });

        // Dormers for conforme signing — resolve student names
        $internRows = RhIntern::whereIn('rh_room_id', $rooms->pluck('id'))
            ->where('status', 'active')
            ->get(['id', 'rh_room_id', 'student_id']);

        $studentIds = $internRows->pluck('student_id')->unique()->values();
        $nameMap = $studentIds->isNotEmpty()
            ? DB::table('students')
                ->whereIn('id', $studentIds)
                ->get(['id', 'lastname', 'firstname'])
                ->keyBy('id')
                ->map(fn($s) => trim($s->lastname . ', ' . $s->firstname))
                ->toArray()
            : [];

        $interns = $internRows->map(fn($i) => [
            'id'      => $i->id,
            'room_id' => $i->rh_room_id,
            'name'    => $nameMap[$i->student_id] ?? ('Dormer #' . $i->id),
        ]);

        return Inertia::render('ResidenceHall/Housekeeping/Index', [
            'rooms'      => $roomData,
            'checkDate'  => $checkDate,
            'items'      => self::ITEMS,
            'interns'    => $interns,
            'myHall'     => $hall,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('rh.housekeeping.manage');

        $data = $request->validate([
            'rh_room_id' => 'required|exists:rh_rooms,id',
            'check_date' => 'required|date',
            'scores'     => 'required|array',
            'scores.*'   => 'required|integer|min:1|max:4',
        ]);

        $room = RhRoom::findOrFail($data['rh_room_id']);
        $hall = $this->hallScope();
        if ($hall && $room->residence_hall !== $hall) abort(403);

        $scores = array_intersect_key($data['scores'], self::ITEMS);
        $total  = array_sum($scores);
        $avg    = count($scores) > 0 ? round($total / count($scores), 2) : 0;

        RhHousekeepingCheck::updateOrCreate(
            ['rh_room_id' => $data['rh_room_id'], 'check_date' => $data['check_date']],
            [
                'inspected_by'  => Auth::id(),
                'scores'        => $scores,
                'total_score'   => $total,
                'average_score' => $avg,
                'passed'        => $avg >= 3,
            ]
        );

        return back()->with('success', 'Housekeeping check saved.');
    }

    public function conforme(Request $request, RhHousekeepingCheck $rhHousekeepingCheck)
    {
        $this->authorize('rh.housekeeping.manage');

        $data = $request->validate([
            'rh_intern_id' => 'required|exists:rh_interns,id',
        ]);

        RhHousekeepingConforme::updateOrCreate(
            [
                'rh_housekeeping_check_id' => $rhHousekeepingCheck->id,
                'rh_intern_id'             => $data['rh_intern_id'],
            ],
            ['conforme_at' => now()]
        );

        return back()->with('success', 'Conforme recorded.');
    }
}
