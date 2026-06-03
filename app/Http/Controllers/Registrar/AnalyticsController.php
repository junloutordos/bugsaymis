<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\Registrar\StudentAcademicStanding;
use App\Models\Registrar\StudentAnnualGrade;
use App\Models\Registrar\StudentEnrollment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('students.analytics.view');

        $currentId    = SchoolYear::where('is_current', true)->value('id');
        $schoolYearId = (int) $request->input('school_year_id', $currentId);
        $schoolYears  = SchoolYear::orderByDesc('start_date')->get(['id', 'name', 'is_current']);

        // ── Enrollment stats ───────────────────────────────────────────────────
        $enrollmentByGrade = StudentEnrollment::where('school_year_id', $schoolYearId)
            ->selectRaw('grade_level, status, COUNT(*) as count')
            ->groupBy('grade_level', 'status')
            ->get()
            ->groupBy('grade_level')
            ->map(fn ($rows) => $rows->keyBy('status')->map->count);

        $enrollmentSummary = collect(range(7, 12))->map(fn ($g) => [
            'grade'       => $g,
            'label'       => "Grade {$g}",
            'enrolled'    => $enrollmentByGrade->get($g, collect())->get('enrolled', 0),
            'dropped'     => $enrollmentByGrade->get($g, collect())->get('dropped', 0),
            'transferred' => $enrollmentByGrade->get($g, collect())->get('transferred_out', 0),
            'total'       => $enrollmentByGrade->get($g, collect())->sum(),
        ])->values();

        $totalEnrolled   = $enrollmentSummary->sum('enrolled');
        $totalDropped    = $enrollmentSummary->sum('dropped');
        $totalTransferred = $enrollmentSummary->sum('transferred');

        // ── Section utilisation ────────────────────────────────────────────────
        $sections = Section::where('school_year_id', $schoolYearId)
            ->where('is_active', true)
            ->get(['id', 'sectionname', 'levelid', 'capacity']);

        $enrolledPerSection = StudentEnrollment::where('school_year_id', $schoolYearId)
            ->where('status', 'enrolled')
            ->selectRaw('section_id, COUNT(*) as count')
            ->groupBy('section_id')
            ->pluck('count', 'section_id');

        $sectionStats = $sections->map(fn ($s) => [
            'id'        => $s->id,
            'name'      => $s->sectionname,
            'grade'     => $s->levelid,
            'capacity'  => $s->capacity,
            'enrolled'  => $enrolledPerSection->get($s->id, 0),
            'pct'       => $s->capacity > 0
                ? round($enrolledPerSection->get($s->id, 0) / $s->capacity * 100)
                : 0,
        ])->sortBy(['grade', 'name'])->values();

        // ── Academic standing distribution ─────────────────────────────────────
        $standingDist = StudentAcademicStanding::where('school_year_id', $schoolYearId)
            ->selectRaw('standing, COUNT(*) as count')
            ->groupBy('standing')
            ->pluck('count', 'standing');

        // ── Honors distribution ────────────────────────────────────────────────
        $honorsDist = StudentAcademicStanding::where('school_year_id', $schoolYearId)
            ->where('honors', '<>', 'None')
            ->selectRaw('honors, COUNT(*) as count')
            ->groupBy('honors')
            ->pluck('count', 'honors');

        // ── At-risk students (failing subjects) ───────────────────────────────
        $atRisk = StudentAnnualGrade::where('school_year_id', $schoolYearId)
            ->where('final_ge', '>', 3.0)
            ->selectRaw('student_id, COUNT(*) as failed_count')
            ->groupBy('student_id')
            ->orderByDesc('failed_count')
            ->limit(20)
            ->get();

        // ── GWA distribution (by range) ────────────────────────────────────────
        $gwaBuckets = StudentAcademicStanding::where('school_year_id', $schoolYearId)
            ->whereNotNull('gwa')
            ->get('gwa')
            ->groupBy(function ($s) {
                $g = (float) $s->gwa;
                if ($g <= 1.25) return '1.00–1.25';
                if ($g <= 1.50) return '1.26–1.50';
                if ($g <= 1.75) return '1.51–1.75';
                if ($g <= 2.00) return '1.76–2.00';
                if ($g <= 2.50) return '2.01–2.50';
                if ($g <= 3.00) return '2.51–3.00';
                return '> 3.00 (Failed)';
            })
            ->map->count()
            ->toArray();

        return Inertia::render('Registrar/Analytics/Index', [
            'schoolYears'        => $schoolYears,
            'selectedSchoolYear' => $schoolYearId,
            'totals' => [
                'enrolled'    => $totalEnrolled,
                'dropped'     => $totalDropped,
                'transferred' => $totalTransferred,
                'total'       => $totalEnrolled + $totalDropped + $totalTransferred,
            ],
            'enrollmentByGrade' => $enrollmentSummary,
            'sectionStats'      => $sectionStats,
            'standingDist'      => $standingDist,
            'honorsDist'        => $honorsDist,
            'atRisk'            => $atRisk,
            'gwaBuckets'        => $gwaBuckets,
        ]);
    }
}
