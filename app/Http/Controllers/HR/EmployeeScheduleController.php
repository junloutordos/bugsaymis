<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\EmployeeSchedule;
use App\Models\HR\SchedulePreset;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeeScheduleController extends Controller
{
    public function index()
    {
        $this->authorize('hr.schedule.manage');

        return Inertia::render('HR/Schedules/Index', [
            'presets'    => SchedulePreset::orderBy('name')->get(),
            'employees'  => User::where('status', 'active')
                ->select('id', 'name', 'emp_category', 'badge_id')
                ->orderBy('emp_category')
                ->orderBy('name')
                ->get()
                ->groupBy('emp_category'),
            'categories' => User::where('status', 'active')
                ->whereNotNull('emp_category')
                ->where('emp_category', '!=', '')
                ->distinct()
                ->orderBy('emp_category')
                ->pluck('emp_category'),
            // Current assignments: latest active schedule per employee
            'assignments' => EmployeeSchedule::with('user:id,name,emp_category')
                ->whereNull('end_date')
                ->where('is_default', true)
                ->get(),
        ]);
    }

    private function validatePreset(Request $request, ?int $ignoreId = null): array
    {
        $uniqueRule = $ignoreId
            ? "required|string|max:100|unique:schedule_presets,name,{$ignoreId}"
            : 'required|string|max:100|unique:schedule_presets,name';

        $data = $request->validate([
            'name'                   => $uniqueRule,
            'schedule_type'          => 'required|in:fixed,shifting',
            'daily_schedules'        => 'required|array|min:1',
            'daily_schedules.*.time_in'  => 'required|date_format:H:i',
            'daily_schedules.*.time_out' => 'required|date_format:H:i',
            'grace_period_minutes'   => 'nullable|integer|min:0|max:60',
            'late_threshold_minutes' => 'nullable|integer|min:0|max:480',
            'half_day_hours'         => 'nullable|integer|min:1|max:8',
            'remarks'                => 'nullable|string|max:255',
        ]);

        // Ensure only valid day keys
        $validDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $data['daily_schedules'] = array_filter(
            $data['daily_schedules'],
            fn ($day) => in_array($day, $validDays),
            ARRAY_FILTER_USE_KEY
        );

        return $data;
    }

    public function storePreset(Request $request)
    {
        $this->authorize('hr.schedule.manage');

        $data = $this->validatePreset($request);
        SchedulePreset::create($data);

        return back()->with('success', "Schedule preset \"{$data['name']}\" created.");
    }

    public function updatePreset(Request $request, SchedulePreset $preset)
    {
        $this->authorize('hr.schedule.manage');

        $data = $this->validatePreset($request, $preset->id);
        $preset->update($data);

        return back()->with('success', "Schedule preset \"{$preset->name}\" updated.");
    }

    public function destroyPreset(SchedulePreset $preset)
    {
        $this->authorize('hr.schedule.manage');

        $preset->delete();

        return back()->with('success', 'Schedule preset deleted.');
    }

    /**
     * Bulk-assign a preset to the selected employees.
     * Creates/updates the default employee_schedules row for each.
     */
    public function assign(Request $request)
    {
        $this->authorize('hr.schedule.manage');

        $data = $request->validate([
            'preset_id'      => 'required|exists:schedule_presets,id',
            'user_ids'       => 'required|array|min:1',
            'user_ids.*'     => 'exists:users,id',
            'effective_date' => 'required|date',
        ]);

        $preset = SchedulePreset::findOrFail($data['preset_id']);

        foreach ($data['user_ids'] as $userId) {
            // Close any existing open default schedule
            EmployeeSchedule::where('user_id', $userId)
                ->where('is_default', true)
                ->whereNull('end_date')
                ->update(['end_date' => now()->subDay()->toDateString()]);

            // Create new default schedule from preset
            EmployeeSchedule::create([
                'user_id'                => $userId,
                'name'                   => $preset->name,
                'schedule_type'          => $preset->schedule_type,
                'work_days'              => $preset->getWorkDays(),
                'daily_schedules'        => $preset->daily_schedules,
                'time_in'                => null,
                'time_out'               => null,
                'grace_period_minutes'   => $preset->grace_period_minutes,
                'late_threshold_minutes' => $preset->late_threshold_minutes,
                'half_day_hours'         => $preset->half_day_hours,
                'effective_date'         => $data['effective_date'],
                'end_date'               => null,
                'is_default'             => true,
                'remarks'                => $preset->remarks,
            ]);
        }

        $count = count($data['user_ids']);
        return back()->with('success', "Assigned \"{$preset->name}\" to {$count} employee(s) effective {$data['effective_date']}.");
    }
}
