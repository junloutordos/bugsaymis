<?php

namespace App\Services\StudentPortal;

use App\Models\ResidenceHall\RhApplication;
use App\Models\ResidenceHall\RhIntern;
use App\Models\StudentClearance\StudentClearance;
use App\Models\StudentClearance\StudentClearancePeriod;
use Illuminate\Support\Facades\DB;

/**
 * Builds the student portal dashboard overview (form completion, RH status,
 * clearance summary) — used by both the web portal and the mobile API.
 */
class PortalDashboardService
{
    // Sections students must complete each enrollment
    public const SECTION_LABELS = [
        'academic'       => 'Academic Preferences',
        'activities'     => 'Activities & Awards',
        'social'         => 'Social & Personality',
        'career'         => 'Career & Vocation',
        'residence'      => 'Residence & Transport',
        'health'         => 'Physical Health',
        'allergies'      => 'Allergies',
        'immunizations'  => 'Immunizations',
        'medical_history'=> 'Medical History',
        'vitamins'       => 'Vitamins & Supplements',
    ];

    public function overview(string $pisayID, int $gradeLevel): array
    {
        $student = DB::table('students')
            ->where('pisaysystemID', $pisayID)
            ->first();

        $sy = DB::table('school_years')->where('is_current', 1)->first();

        $submitted = FormSubmissions::map($pisayID, $sy?->id);

        $completion = [];
        foreach (self::SECTION_LABELS as $key => $label) {
            $completion[] = [
                'key'          => $key,
                'label'        => $label,
                'submitted'    => isset($submitted[$key]),
                'submitted_at' => $submitted[$key] ?? null,
                'group'        => in_array($key, FormSubmissions::MEDICAL_SECTIONS) ? 'medical' : 'profile',
            ];
        }

        $totalDone = count(array_filter($completion, fn ($s) => $s['submitted']));

        $rhApplication = ($sy && $student)
            ? RhApplication::where('student_id', $student->id)
                ->where('school_year_id', $sy->id)
                ->latest()
                ->first(['id', 'status', 'preferred_hall', 'created_at'])
            : null;

        $intern = ($sy && $student)
            ? RhIntern::where('student_id', $student->id)
                ->where('school_year_id', $sy->id)
                ->where('status', 'active')
                ->first(['id', 'bed_number'])
            : null;

        $clearancePeriod = $sy
            ? StudentClearancePeriod::where('school_year_id', $sy->id)
                ->orderByRaw("FIELD(status, 'open', 'draft', 'closed', 'archived')")
                ->latest('id')
                ->first()
            : null;

        $clearance = ($clearancePeriod && $student)
            ? StudentClearance::with('items:id,student_clearance_id,status,blocker_summary')
                ->where('student_clearance_period_id', $clearancePeriod->id)
                ->where('student_id', $student->id)
                ->first()
            : null;

        $clearanceStatus = null;
        if ($clearance) {
            $done = $clearance->items->whereIn('status', ['cleared', 'waived', 'not_applicable'])->count();
            $total = $clearance->items->count();
            $clearanceStatus = [
                'period_title'  => $clearancePeriod->title,
                'status'        => $clearance->status,
                'done'          => $done,
                'total'         => $total,
                'pending'       => max(0, $total - $done),
                'holds'         => $clearance->items->whereIn('status', ['hold', 'returned'])->count(),
                'blockers'      => $clearance->items->pluck('blocker_summary')->filter()->count(),
                'finalized_at'  => $clearance->finalized_at?->format('Y-m-d H:i'),
            ];
        } elseif ($clearancePeriod) {
            $clearanceStatus = [
                'period_title' => $clearancePeriod->title,
                'status'       => 'not_generated',
                'done'         => 0,
                'total'        => 0,
                'pending'      => 0,
                'holds'        => 0,
                'blockers'     => 0,
                'finalized_at' => null,
            ];
        }

        return [
            'student'         => $student,
            'grade_level'     => $gradeLevel,
            'school_year'     => $sy?->name,
            'completion'      => $completion,
            'total_done'      => $totalDone,
            'total'           => count(self::SECTION_LABELS),
            'rhApplication'   => $rhApplication,
            'intern'          => $intern,
            'clearanceStatus' => $clearanceStatus,
        ];
    }
}
