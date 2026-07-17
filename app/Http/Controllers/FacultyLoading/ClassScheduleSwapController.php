<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\ClassScheduleSwapRequest;
use App\Models\User;
use App\Services\FacultyLoading\ClassScheduleSwapService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClassScheduleSwapController extends Controller
{
    public function __construct(private readonly ClassScheduleSwapService $swaps) {}

    public function store(Request $request, ClassSchedule $classSchedule): RedirectResponse
    {
        abort_unless($classSchedule->entry_type === 'class' && $classSchedule->status !== 'cancelled', 422);
        $user = $request->user();
        $canManage = $user->isSuperAdmin() || $user->hasPermission('faculty_loading.manage');
        abort_unless($canManage || (int) $classSchedule->user_id === (int) $user->id, 403);

        $data = $request->validate([
            'reason' => 'required|string|max:1000',
            'preferred_day' => 'nullable|in:Monday,Tuesday,Wednesday,Thursday,Friday',
            'preferred_start_time' => 'nullable|date_format:H:i',
            'preferred_end_time' => 'nullable|date_format:H:i|after:preferred_start_time',
        ]);

        $duplicate = ClassScheduleSwapRequest::where('source_schedule_id', $classSchedule->id)
            ->where('requested_by', $user->id)
            ->whereIn('status', ['pending', 'pending_ocd'])->exists();
        if ($duplicate) {
            return back()->withErrors(['swap' => 'You already have an open swap request for this class.']);
        }

        $swap = ClassScheduleSwapRequest::create([
            'academic_term_id' => $classSchedule->academic_term_id,
            'source_schedule_id' => $classSchedule->id,
            'requested_by' => $user->id,
            'reason' => $data['reason'],
            'preferences' => [
                'day' => $data['preferred_day'] ?? null,
                'start_time' => $data['preferred_start_time'] ?? null,
                'end_time' => $data['preferred_end_time'] ?? null,
            ],
        ]);

        User::havingRole('CID Chief')->where('status', '<>', 'inactive')->get()->each(
            fn (User $cid) => NotificationService::notifyUser(
                $cid, 'Class Schedule', "Swap Request #{$swap->id}",
                'New Faculty Swap Request', route('faculty-loading.schedules.index', ['term_id' => $classSchedule->academic_term_id]),
                $user->name,
            )
        );

        return back()->with('success', 'Schedule swap request submitted to CID.');
    }

    public function candidates(Request $request, ClassScheduleSwapRequest $swapRequest): JsonResponse
    {
        $this->authorizeManager($request);
        abort_unless(in_array($swapRequest->status, ['pending', 'recommended'], true), 422);

        return response()->json(['candidates' => $this->swaps->candidates($swapRequest)]);
    }

    public function apply(Request $request, ClassScheduleSwapRequest $swapRequest): RedirectResponse
    {
        $this->authorizeManager($request);
        $data = $request->validate([
            'candidate_id' => 'required|string|size:64',
            'pin' => 'nullable|string',
        ]);

        $message = $this->swaps->apply($swapRequest, $data['candidate_id'], $request->user(), $data['pin'] ?? null);

        return back()->with('success', $message);
    }

    public function decline(Request $request, ClassScheduleSwapRequest $swapRequest): RedirectResponse
    {
        $this->authorizeManager($request);
        abort_unless(in_array($swapRequest->status, ['pending', 'recommended'], true), 422);
        $swapRequest->update([
            'status' => 'declined', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(),
        ]);
        NotificationService::notifyUser(
            $swapRequest->requester, 'Class Schedule', "Swap Request #{$swapRequest->id}",
            'Schedule Swap Request Declined', route('faculty-loading.my-schedule', ['term_id' => $swapRequest->academic_term_id]),
        );

        return back()->with('success', 'Schedule swap request declined.');
    }

    public function cancel(Request $request, ClassScheduleSwapRequest $swapRequest): RedirectResponse
    {
        abort_unless((int) $swapRequest->requested_by === (int) $request->user()->id, 403);
        abort_unless($swapRequest->status === 'pending', 422);
        $swapRequest->update(['status' => 'cancelled']);

        return back()->with('success', 'Schedule swap request cancelled.');
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user()->isSuperAdmin() || $request->user()->hasPermission('faculty_loading.manage'), 403);
    }
}
