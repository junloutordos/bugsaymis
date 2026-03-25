<?php

namespace App\Http\Controllers;

use App\Models\GuidanceSessionReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class GuidanceSessionReportController extends Controller
{
    // ─── Auth guard ───────────────────────────────────────────────────────────

    private function authorizeGuidance(): void
    {
        $user    = Auth::user();
        $roleIds = array_filter(array_map('intval', array_map('trim', explode(',', $user->role_id ?? ''))));
        if (! (in_array(17, $roleIds) || in_array(1, $roleIds))) {
            abort(403, 'Forbidden');
        }
    }

    // ─── Auto CS No. ──────────────────────────────────────────────────────────

    private function nextCsNo(): string
    {
        $year  = Carbon::now()->year;
        $last  = GuidanceSessionReport::where('cs_no', 'like', "CS-{$year}-%")
            ->orderByDesc('cs_no')
            ->value('cs_no');

        $seq   = $last ? ((int) substr($last, -4)) + 1 : 1;
        return sprintf('CS-%d-%04d', $year, $seq);
    }

    // ─── Current school year helper ───────────────────────────────────────────

    private function currentSchoolYear(): string
    {
        $now = Carbon::now();
        return $now->month >= 6
            ? $now->year . '-' . ($now->year + 1)
            : ($now->year - 1) . '-' . $now->year;
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $this->authorizeGuidance();

        $query = GuidanceSessionReport::with('accomplishedByUser:id,name,position')
            ->orderByDesc('date_filed')
            ->orderByDesc('id');

        if ($request->filled('q')) {
            $q = $request->query('q');
            $query->where('client_name', 'like', "%{$q}%")
                  ->orWhere('cs_no', 'like', "%{$q}%");
        }

        if ($request->filled('school_year')) {
            $query->where('school_year', $request->query('school_year'));
        }

        $reports = $query->get()->map(function ($r) {
            $arr = $r->toArray();
            $arr['date_filed'] = $r->getRawOriginal('date_filed');
            return $arr;
        });

        // Distinct school years for filter dropdown
        $schoolYears = GuidanceSessionReport::select('school_year')
            ->distinct()
            ->orderByDesc('school_year')
            ->pluck('school_year');

        return Inertia::render('Guidance/SessionReports/Index', [
            'reports'       => $reports,
            'schoolYears'   => $schoolYears,
            'currentUser'   => Auth::user()->only('id', 'name', 'position'),
            'defaultSY'     => $this->currentSchoolYear(),
        ]);
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $this->authorizeGuidance();

        $data = $request->validate([
            'consultation_id'      => ['nullable', 'integer'],
            'date_filed'           => ['required', 'date'],
            'school_year'          => ['required', 'string', 'max:20'],
            'client_name'          => ['required', 'string', 'max:255'],
            'client_age'           => ['nullable', 'integer', 'min:1', 'max:99'],
            'client_sex'           => ['nullable', 'in:Male,Female'],
            'grade_section'        => ['nullable', 'string', 'max:100'],
            'batch'                => ['nullable', 'string', 'max:50'],
            'referral_strategies'  => ['required', 'array', 'min:1'],
            'referral_strategies.*'=> ['string'],
            'concern'              => ['nullable', 'string', 'max:2000'],
            'difficulties_presented' => ['nullable', 'string', 'max:5000'],
            'counselor_notes'      => ['nullable', 'string'],
            'accomplished_by'      => ['required', 'integer', 'exists:users,id'],
        ]);

        $report = GuidanceSessionReport::create(array_merge($data, [
            'cs_no' => $this->nextCsNo(),
        ]));

        $report->load('accomplishedByUser:id,name,position');
        $arr = $report->toArray();
        $arr['date_filed'] = $report->getRawOriginal('date_filed');

        return response()->json(['message' => 'Session report created.', 'report' => $arr], 201);
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function update(Request $request, GuidanceSessionReport $sessionReport)
    {
        $this->authorizeGuidance();

        $data = $request->validate([
            'date_filed'           => ['required', 'date'],
            'school_year'          => ['required', 'string', 'max:20'],
            'client_name'          => ['required', 'string', 'max:255'],
            'client_age'           => ['nullable', 'integer', 'min:1', 'max:99'],
            'client_sex'           => ['nullable', 'in:Male,Female'],
            'grade_section'        => ['nullable', 'string', 'max:100'],
            'batch'                => ['nullable', 'string', 'max:50'],
            'referral_strategies'  => ['required', 'array', 'min:1'],
            'referral_strategies.*'=> ['string'],
            'concern'              => ['nullable', 'string', 'max:2000'],
            'difficulties_presented' => ['nullable', 'string', 'max:5000'],
            'counselor_notes'      => ['nullable', 'string'],
            'accomplished_by'      => ['required', 'integer', 'exists:users,id'],
        ]);

        $sessionReport->update($data);
        $sessionReport->load('accomplishedByUser:id,name,position');
        $arr = $sessionReport->toArray();
        $arr['date_filed'] = $sessionReport->getRawOriginal('date_filed');

        return response()->json(['message' => 'Session report updated.', 'report' => $arr]);
    }

    // ─── Print ────────────────────────────────────────────────────────────────

    public function print(GuidanceSessionReport $sessionReport)
    {
        $this->authorizeGuidance();

        $sessionReport->load('accomplishedByUser:id,name,position');
        $arr = $sessionReport->toArray();
        $arr['date_filed'] = $sessionReport->getRawOriginal('date_filed');

        return Inertia::render('Guidance/SessionReports/Print', [
            'report'  => $arr,
            'printer' => Auth::user()->only('id', 'name', 'position'),
        ]);
    }
}
