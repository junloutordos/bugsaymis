<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentClearance\StudentClearance;
use App\Services\StudentClearance\StudentClearanceService;
use Inertia\Inertia;
use Inertia\Response;

class ClearanceController extends Controller
{
    public function show(StudentClearanceService $service): Response
    {
        $student = Student::where('pisaysystemID', session('student_pisaysystemID'))->firstOrFail();
        $period = $service->activeOrLatestPeriod();

        $clearance = $period
            ? StudentClearance::with([
                'period.schoolYear:id,name,is_current',
                'section:id,sectionname,levelid',
                'adviser:id,name',
                'items.assignedUser:id,name',
                'items.signer:id,name',
            ])
                ->where('student_clearance_period_id', $period->id)
                ->where('student_id', $student->id)
                ->first()
            : null;

        return Inertia::render('StudentPortal/Clearance/Show', [
            'student' => [
                'id'        => $student->id,
                'full_name' => $student->full_name,
                'pisays_id' => $student->pisaysystemID,
            ],
            'period' => $period ? [
                'id'       => $period->id,
                'title'    => $period->title,
                'status'   => $period->status,
                'sy_name'  => $period->schoolYear?->name,
                'opens_at' => $period->opens_at?->format('Y-m-d'),
                'closes_at'=> $period->closes_at?->format('Y-m-d'),
            ] : null,
            'clearance' => $clearance ? $this->serializeClearance($clearance) : null,
        ]);
    }

    private function serializeClearance(StudentClearance $clearance): array
    {
        return [
            'id'           => $clearance->id,
            'status'       => $clearance->status,
            'grade_level'  => $clearance->grade_level,
            'section_name' => $clearance->section?->sectionname,
            'adviser_name' => $clearance->adviser?->name,
            'finalized_at' => $clearance->finalized_at?->format('Y-m-d H:i'),
            'items'        => $clearance->items->map(fn ($item) => [
                'id'                => $item->id,
                'requirement_label' => $item->requirement_label,
                'requirement_type'  => $item->requirement_type,
                'requirement_group' => $item->requirement_group,
                'status'            => $item->status,
                'remarks'           => $item->remarks,
                'accountability'    => $item->accountability,
                'assigned_to'       => $item->assignedUser?->name,
                'signed_by'         => $item->signer?->name,
                'signed_at'         => $item->signed_at?->format('Y-m-d H:i'),
            ])->values(),
        ];
    }
}
