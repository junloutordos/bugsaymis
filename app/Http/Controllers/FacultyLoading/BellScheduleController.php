<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\BellScheduleOverride;
use App\Models\FacultyLoading\BellScheduleSetting;
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
        'CLASS', 'ILP_ONLY', 'FLAG', 'HOMEROOM', 'ADVISING', 'DEAD',
        'RECESS', 'LUNCH', 'CONSULT', 'ACTIVITY', 'WELLNESS', 'OTHER',
    ];

    private function authorizeEditor(): void
    {
        $user = Auth::user();
        abort_unless($user->isSuperAdmin() || $user->hasRole('CID Chief'), 403);
    }

    /** Special-window settings: shape drives which sub-editor the UI renders. */
    private const SETTING_META = [
        'WEDNESDAY_WELLNESS'       => ['label' => 'Wednesday Wellness Block',       'shape' => 'window'],
        'WEDNESDAY_ALP'            => ['label' => 'Wednesday ALP (campus default)', 'shape' => 'window'],
        'WEDNESDAY_ALP_BY_GRADE'   => ['label' => 'Wednesday ALP — per-grade override', 'shape' => 'grade_windows'],
        'WEDNESDAY_ACTIVITY_START' => ['label' => 'Wednesday Activity Cutoff (per group)', 'shape' => 'group_times'],
        'WEDNESDAY_FULL_GRADES'    => ['label' => 'Grades exempt from the Wednesday cutoff', 'shape' => 'grades'],
        'FRIDAY_FLAG_RETREAT'      => ['label' => 'Friday Flag Retreat Ceremony',   'shape' => 'window'],
        'FRIDAY_ILA_GRADES'        => ['label' => 'Grades with no in-person Friday classes (ILA)', 'shape' => 'grades'],
        'GRADE8_OVERFLOW_SLOTS'    => ['label' => 'Grade 8 Overflow Periods (per day)', 'shape' => 'day_slots'],
    ];

    private const GROUPS = ['G7G8', 'G9G10', 'G11G12'];

    public function index(): Response
    {
        $this->authorizeEditor();

        $overridden = BellScheduleOverride::pluck('timetable_key')->all();
        $editedSettings = BellScheduleSetting::pluck('setting_key')->all();

        $timetables = collect(SchedulingConstants::EDITABLE_TIMETABLES)
            ->map(fn ($label, $key) => [
                'key'           => $key,
                'label'         => $label,
                'rows'          => SchedulingConstants::effectiveTimetableRows($key),
                'is_overridden' => in_array($key, $overridden, true),
            ])
            ->values();

        $settings = collect(self::SETTING_META)
            ->map(fn ($meta, $key) => [
                'key'           => $key,
                'label'         => $meta['label'],
                'shape'         => $meta['shape'],
                'value'         => SchedulingConstants::setting($key),
                'is_overridden' => in_array($key, $editedSettings, true),
            ])
            ->values();

        return Inertia::render('FacultyLoading/BellSchedule/Index', [
            'timetables'    => $timetables,
            'types'         => self::TYPES,
            'teachingTypes' => ['CLASS', 'ILP_ONLY'],
            'settings'      => $settings,
            'groups'        => self::GROUPS,
            'grades'        => [7, 8, 9, 10, 11, 12],
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

    // ── Special windows ──────────────────────────────────────────────────────

    public function updateSetting(Request $request): JsonResponse
    {
        $this->authorizeEditor();

        $data = $request->validate([
            'setting_key' => 'required|string|in:'.implode(',', SchedulingConstants::SETTING_KEYS),
            'value'       => 'required',
        ]);

        [$ok, $clean, $error] = $this->validateSettingValue($data['setting_key'], $data['value']);
        if (! $ok) {
            return response()->json(['message' => $error], 422);
        }

        BellScheduleSetting::updateOrCreate(
            ['setting_key' => $data['setting_key']],
            ['value' => $clean, 'updated_by' => Auth::id()],
        );
        SchedulingConstants::flushOverrideCache();

        return response()->json(['message' => 'Saved. New generations will use these times.']);
    }

    public function resetSetting(Request $request): JsonResponse
    {
        $this->authorizeEditor();

        $data = $request->validate([
            'setting_key' => 'required|string|in:'.implode(',', SchedulingConstants::SETTING_KEYS),
        ]);

        BellScheduleSetting::where('setting_key', $data['setting_key'])->delete();
        SchedulingConstants::flushOverrideCache();

        return response()->json([
            'message' => 'Reset to the built-in default.',
            'value'   => SchedulingConstants::defaultSetting($data['setting_key']),
        ]);
    }

    /**
     * Per-shape validation + normalisation for a special-window setting.
     *
     * @return array{0:bool,1:mixed,2:?string} [ok, cleanValue, error]
     */
    private function validateSettingValue(string $key, mixed $value): array
    {
        $shape = self::SETTING_META[$key]['shape'] ?? null;
        $isTime = fn ($t) => is_string($t) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t);
        $window = function ($w) use ($isTime) {
            return is_array($w) && $isTime($w['start'] ?? null) && $isTime($w['end'] ?? null)
                && $w['start'] < $w['end'];
        };

        switch ($shape) {
            case 'window':
                if (! $window($value)) {
                    return [false, null, 'Start and end must be valid times with end after start.'];
                }
                return [true, ['start' => $value['start'], 'end' => $value['end']], null];

            case 'grade_windows':
                // { "8": {start,end}, ... } — every key a grade 7–12, every value a window.
                $clean = [];
                foreach ((array) $value as $grade => $w) {
                    $g = (int) $grade;
                    if ($g < 7 || $g > 12 || ! $window($w)) {
                        return [false, null, "Invalid ALP window for grade {$grade}."];
                    }
                    $clean[$g] = ['start' => $w['start'], 'end' => $w['end']];
                }
                return [true, $clean, null];

            case 'group_times':
                $clean = [];
                foreach (self::GROUPS as $group) {
                    $t = $value[$group] ?? null;
                    if (! $isTime($t)) {
                        return [false, null, "Invalid cutoff time for {$group}."];
                    }
                    $clean[$group] = $t;
                }
                return [true, $clean, null];

            case 'grades':
                $clean = [];
                foreach ((array) $value as $g) {
                    $g = (int) $g;
                    if ($g < 7 || $g > 12) {
                        return [false, null, 'Grades must be 7–12.'];
                    }
                    $clean[] = $g;
                }
                return [true, array_values(array_unique($clean)), null];

            case 'day_slots':
                // { "Tuesday": [ {start,end,type,label}, ... ], ... }
                $clean = [];
                foreach ((array) $value as $day => $rows) {
                    if (! in_array($day, SchedulingConstants::DAYS, true)) {
                        return [false, null, "Unknown day: {$day}."];
                    }
                    $dayRows = [];
                    foreach ((array) $rows as $r) {
                        if (! $window($r) || ! in_array($r['type'] ?? '', self::TYPES, true) || empty($r['label'])) {
                            return [false, null, "Invalid overflow block on {$day}."];
                        }
                        $dayRows[] = [
                            'start' => $r['start'], 'end' => $r['end'],
                            'type'  => $r['type'],  'label' => substr((string) $r['label'], 0, 60),
                        ];
                    }
                    // Reject overlaps within a day.
                    usort($dayRows, fn ($a, $b) => $a['start'] <=> $b['start']);
                    for ($i = 1; $i < count($dayRows); $i++) {
                        if ($dayRows[$i]['start'] < $dayRows[$i - 1]['end']) {
                            return [false, null, "Overflow blocks overlap on {$day}."];
                        }
                    }
                    if ($dayRows !== []) {
                        $clean[$day] = $dayRows;
                    }
                }
                return [true, $clean, null];

            default:
                return [false, null, 'Unknown setting.'];
        }
    }
}
