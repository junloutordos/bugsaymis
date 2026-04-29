<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\DtrRecord;
use App\Models\HR\EmployeeSchedule;
use App\Models\HR\SchedulePreset;
use App\Models\User;
use App\Services\HR\DTRService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeeScheduleController extends Controller
{
    /* =====================================================
     | HR ADMIN — MANAGE PRESETS + VIEW SUBMISSIONS
     |=====================================================*/

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
            // Current active assignments: latest default approved schedule per employee
            'assignments' => EmployeeSchedule::with('user:id,name,emp_category')
                ->whereNull('end_date')
                ->where('is_default', true)
                ->where('status', 'approved')
                ->get(),
            // Pending submissions from employees
            'pendingSubmissions' => EmployeeSchedule::with('user:id,name,emp_category')
                ->where('status', 'pending')
                ->latest()
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
     * Bulk-assign a preset to the selected employees (HR-initiated).
     */
    public function assign(Request $request, DTRService $dtrService)
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
            $newSchedule = EmployeeSchedule::create([
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
                'status'                 => 'approved',
                'remarks'                => $preset->remarks,
            ]);

            // Recompute unlocked DTR records from the effective date onwards
            $latestRecord = DtrRecord::where('user_id', $userId)
                ->where('work_date', '>=', $data['effective_date'])
                ->where('is_locked', false)
                ->orderByDesc('work_date')
                ->value('work_date');

            if ($latestRecord) {
                DtrRecord::where('user_id', $userId)
                    ->where('work_date', '>=', $data['effective_date'])
                    ->where('is_locked', false)
                    ->update(['schedule_id' => $newSchedule->id]);

                $dtrService->recomputeForUser($userId, $data['effective_date'], $latestRecord);
            }
        }

        $count = count($data['user_ids']);
        return back()->with('success', "Assigned \"{$preset->name}\" to {$count} employee(s) effective {$data['effective_date']}.");
    }

    /* =====================================================
     | HR ADMIN — APPROVE / REJECT EMPLOYEE SUBMISSIONS
     |=====================================================*/

    public function approveSubmission(Request $request, EmployeeSchedule $schedule, DTRService $dtrService)
    {
        $this->authorize('hr.schedule.manage');

        if ($schedule->status !== 'pending') {
            return back()->with('error', 'This submission has already been processed.');
        }

        $userId        = $schedule->user_id;
        $effectiveDate = $schedule->effective_date->toDateString();

        // Close any existing open default schedule
        EmployeeSchedule::where('user_id', $userId)
            ->where('is_default', true)
            ->whereNull('end_date')
            ->update(['end_date' => now()->subDay()->toDateString()]);

        // Approve: make this the new active default
        $schedule->update([
            'status'     => 'approved',
            'is_default' => true,
        ]);

        // Recompute unlocked DTR records from the effective date onwards
        $latestRecord = DtrRecord::where('user_id', $userId)
            ->where('work_date', '>=', $effectiveDate)
            ->where('is_locked', false)
            ->orderByDesc('work_date')
            ->value('work_date');

        if ($latestRecord) {
            DtrRecord::where('user_id', $userId)
                ->where('work_date', '>=', $effectiveDate)
                ->where('is_locked', false)
                ->update(['schedule_id' => $schedule->id]);

            $dtrService->recomputeForUser($userId, $effectiveDate, $latestRecord);
        }

        return back()->with('success', "Schedule approved for {$schedule->user->name}.");
    }

    public function rejectSubmission(Request $request, EmployeeSchedule $schedule)
    {
        $this->authorize('hr.schedule.manage');

        if ($schedule->status !== 'pending') {
            return back()->with('error', 'This submission has already been processed.');
        }

        $request->validate(['reason' => 'nullable|string|max:500']);

        $schedule->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->input('reason'),
        ]);

        return back()->with('success', 'Schedule submission rejected.');
    }

    /* =====================================================
     | EMPLOYEE SELF-SERVICE — VIEW + SUBMIT SCHEDULE
     |=====================================================*/

    public function mySchedule(Request $request)
    {
        $user = $request->user();

        $currentSchedule = EmployeeSchedule::where('user_id', $user->id)
            ->where('is_default', true)
            ->whereNull('end_date')
            ->where('status', 'approved')
            ->latest('effective_date')
            ->first();

        $pendingSubmission = EmployeeSchedule::where('user_id', $user->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        $history = EmployeeSchedule::where('user_id', $user->id)
            ->whereIn('status', ['approved', 'rejected'])
            ->where('is_default', false)
            ->latest('effective_date')
            ->take(10)
            ->get();

        return Inertia::render('HR/Schedules/MySchedule', [
            'currentSchedule'   => $currentSchedule,
            'pendingSubmission' => $pendingSubmission,
            'presets'           => SchedulePreset::orderBy('name')->get(),
            'history'           => $history,
        ]);
    }

    public function submit(Request $request)
    {
        $user = $request->user();

        // Prevent duplicate pending submissions
        if (EmployeeSchedule::where('user_id', $user->id)->where('status', 'pending')->exists()) {
            return back()->with('error', 'You already have a pending submission. Cancel it first before submitting a new one.');
        }

        $data = $request->validate([
            'preset_id'                         => 'nullable|exists:schedule_presets,id',
            'name'                              => 'required|string|max:100',
            'daily_schedules'                   => 'required|array|min:1',
            'daily_schedules.*.time_in'         => 'required|date_format:H:i',
            'daily_schedules.*.time_out'        => 'required|date_format:H:i',
            'daily_schedules.*.work_from_home'  => 'nullable|boolean',
            'effective_date'                    => 'required|date|after_or_equal:today',
            'remarks'                           => 'nullable|string|max:255',
        ]);

        $validDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $dailySchedules = array_filter(
            $data['daily_schedules'],
            fn ($day) => in_array($day, $validDays),
            ARRAY_FILTER_USE_KEY
        );

        $preset = $data['preset_id'] ? SchedulePreset::find($data['preset_id']) : null;

        EmployeeSchedule::create([
            'user_id'                => $user->id,
            'name'                   => $data['name'],
            'schedule_type'          => $preset?->schedule_type ?? 'fixed',
            'work_days'              => array_keys($dailySchedules),
            'daily_schedules'        => $dailySchedules,
            'time_in'                => null,
            'time_out'               => null,
            'grace_period_minutes'   => $preset?->grace_period_minutes ?? 15,
            'late_threshold_minutes' => $preset?->late_threshold_minutes ?? 240,
            'half_day_hours'         => $preset?->half_day_hours ?? 4,
            'effective_date'         => $data['effective_date'],
            'end_date'               => null,
            'is_default'             => false,
            'status'                 => 'pending',
            'remarks'                => $data['remarks'] ?? null,
        ]);

        return back()->with('success', 'Work schedule submitted for HR review.');
    }

    public function cancelSubmission(Request $request, EmployeeSchedule $schedule)
    {
        if ($schedule->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($schedule->status !== 'pending') {
            return back()->with('error', 'This submission cannot be cancelled.');
        }

        $schedule->delete();

        return back()->with('success', 'Schedule submission cancelled.');
    }
}
