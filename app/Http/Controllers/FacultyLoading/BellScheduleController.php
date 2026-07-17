<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\BellScheduleOverride;
use App\Services\FacultyLoading\SchedulingConstants;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bell Schedule editor — lets Administrators and the CID Chief edit the
 * "constant" daily blocks (flag ceremony, recess, lunch, consultation /
 * home bound, homeroom, class periods) per grade group and day pattern.
 *
 * Edits are stored as overrides on top of SchedulingConstants' hardcoded
 * defaults; a "reset" removes the override and restores the built-in schedule.
 * Changes only affect FUTURE schedule generation — already-plotted
 * class_schedules rows are never touched.
 */
class BellScheduleController extends Controller
{
    /** Block types the editor accepts. Only CLASS/ILP_ONLY are teachable. */
    private const TYPES = [
        'CLASS', 'ILP_ONLY', 'FLAG', 'HOMEROOM', 'ADVISING',
        'RECESS', 'LUNCH', 'CONSULT', 'ACTIVITY', 'WELLNESS', 'OTHER',
    ];

    private function authorizeEditor(): void
    {
        $user = Auth::user();
        abort_unless($user->isSuperAdmin() || $user->hasRole('CID Chief'), 403);
    }

    public function index(): Response
    {
        $this->authorizeEditor();

        $overridden = BellScheduleOverride::pluck('timetable_key')->all();

        $timetables = collect(SchedulingConstants::EDITABLE_TIMETABLES)
            ->map(fn ($label, $key) => [
                'key'           => $key,
                'label'         => $label,
                'rows'          => SchedulingConstants::effectiveTimetableRows($key),
                'is_overridden' => in_array($key, $overridden, true),
            ])
            ->values();

        return Inertia::render('FacultyLoading/BellSchedule/Index', [
            'timetables' => $timetables,
            'types'      => self::TYPES,
            'teachingTypes' => ['CLASS', 'ILP_ONLY'],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorizeEditor();

        $data = $request->validate([
            'timetable_key'   => 'required|string|in:'.implode(',', array_keys(SchedulingConstants::EDITABLE_TIMETABLES)),
            'rows'            => 'required|array|min:1',
            'rows.*.start'    => 'required|date_format:H:i',
            'rows.*.end'      => 'required|date_format:H:i|after:rows.*.start',
            'rows.*.type'     => 'required|in:'.implode(',', self::TYPES),
            'rows.*.label'    => 'required|string|max:60',
        ]);

        // Reject overlapping blocks — the timetable must read cleanly top to bottom.
        $rows = collect($data['rows'])
            ->sortBy(fn ($r) => $r['start'])
            ->values()
            ->all();
        for ($i = 1; $i < count($rows); $i++) {
            if ($rows[$i]['start'] < $rows[$i - 1]['end']) {
                return response()->json([
                    'message' => "Blocks overlap: {$rows[$i - 1]['label']} ({$rows[$i - 1]['start']}–{$rows[$i - 1]['end']}) "
                        ."and {$rows[$i]['label']} ({$rows[$i]['start']}–{$rows[$i]['end']}).",
                ], 422);
            }
        }

        // Normalise to the {start,end,type,label} shape the engine expects.
        $clean = array_map(fn ($r) => [
            'start' => $r['start'],
            'end'   => $r['end'],
            'type'  => $r['type'],
            'label' => $r['label'],
        ], $rows);

        BellScheduleOverride::updateOrCreate(
            ['timetable_key' => $data['timetable_key']],
            ['rows' => $clean, 'updated_by' => Auth::id()],
        );
        SchedulingConstants::flushOverrideCache();

        return response()->json(['message' => 'Bell schedule updated. New generations will use these times.']);
    }

    public function reset(Request $request): JsonResponse
    {
        $this->authorizeEditor();

        $data = $request->validate([
            'timetable_key' => 'required|string|in:'.implode(',', array_keys(SchedulingConstants::EDITABLE_TIMETABLES)),
        ]);

        BellScheduleOverride::where('timetable_key', $data['timetable_key'])->delete();
        SchedulingConstants::flushOverrideCache();

        return response()->json([
            'message' => 'Reset to the built-in schedule.',
            'rows'    => SchedulingConstants::defaultTimetableRows($data['timetable_key']),
        ]);
    }
}
