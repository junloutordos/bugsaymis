<?php

namespace App\Http\Controllers\StudentClearance;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentClearance\StudentClearanceItem;
use App\Services\StudentClearance\StudentClearanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SignatoryQueueController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $permissionNames = StudentClearanceItem::whereNotNull('assigned_permission')
            ->distinct()
            ->pluck('assigned_permission')
            ->filter(fn ($permission) => $user->hasPermission($permission))
            ->values();

        $items = StudentClearanceItem::query()
            ->with([
                'clearance.period.schoolYear:id,name,is_current',
                'clearance.section:id,sectionname,levelid',
                'assignedUser:id,name',
                'signer:id,name',
            ])
            ->where(function ($q) use ($user, $permissionNames) {
                $q->where('assigned_user_id', $user->id);

                if ($permissionNames->isNotEmpty()) {
                    $q->orWhereIn('assigned_permission', $permissionNames);
                }

                if ($user->hasAnyPermission(['students.clearance.manage', 'students.clearance.admin', 'students.clearance.registrar'])) {
                    $q->orWhereNotNull('id');
                }
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderByRaw("FIELD(status, 'pending', 'hold', 'returned', 'cleared', 'waived', 'not_applicable')")
            ->latest('id')
            ->limit(250)
            ->get();

        $students = Student::whereIn('id', $items->pluck('clearance.student_id')->unique())
            ->get(['id', 'firstname', 'lastname', 'middlename', 'pisaysystemID'])
            ->keyBy('id');

        return Inertia::render('StudentClearance/SignatoryQueue', [
            'filters' => [
                'status' => $request->input('status', 'pending'),
            ],
            'items' => $items->map(function (StudentClearanceItem $item) use ($students) {
                $student = $students->get($item->clearance->student_id);

                return [
                    'id'                => $item->id,
                    'clearance_id'      => $item->clearance->id,
                    'student_name'      => $student?->full_name ?? 'Unknown student',
                    'pisays_id'         => $student?->pisaysystemID ?? $item->clearance->pisaysystem_id,
                    'grade_level'       => $item->clearance->grade_level,
                    'section_name'      => $item->clearance->section?->sectionname,
                    'period_title'      => $item->clearance->period?->title,
                    'requirement_label' => $item->requirement_label,
                    'requirement_group' => $item->requirement_group,
                    'requirement_type'  => $item->requirement_type,
                    'status'            => $item->status,
                    'remarks'           => $item->remarks,
                    'accountability'    => $item->accountability,
                    'blocker_summary'   => $item->blocker_summary,
                    'signed_by'         => $item->signer?->name,
                    'signed_at'         => $item->signed_at?->format('Y-m-d H:i'),
                ];
            }),
        ]);
    }

    public function update(StudentClearanceItem $item, Request $request, StudentClearanceService $service): RedirectResponse
    {
        abort_unless($service->canActOnItem($request->user(), $item), 403);

        $data = $request->validate([
            'status'         => ['required', 'in:pending,cleared,hold,returned,waived,not_applicable'],
            'remarks'        => ['nullable', 'string', 'max:5000'],
            'accountability' => ['nullable', 'string', 'max:5000'],
        ]);

        $service->updateItem($item, $request->user(), $data);

        return back()->with('success', 'Clearance item updated.');
    }
}
