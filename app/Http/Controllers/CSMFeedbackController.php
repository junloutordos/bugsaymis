<?php

namespace App\Http\Controllers;

use App\Models\CsmResponse;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class CSMFeedbackController extends Controller
{
    // ── Module display names ──────────────────────────────────────────────────
    private const MODULE_LABELS = [
        'App\\Models\\ITJobRequest'   => 'IT Job Request',
        'App\\Models\\FacilityRequest'=> 'Facility Request',
        'App\\Models\\WorkRequest'    => 'Work Request',
        'App\\Models\\VehicleRequest' => 'Vehicle Request',
        'App\\Models\\ServiceRequest' => 'Service Request',
    ];

    private function isAdmin(): bool
    {
        return Auth::user()->hasAnyRole(['Administrator', 'MIS']);
    }

    // ── SQD average (excludes N/A = 6) ───────────────────────────────────────
    private function sqdAvg(CsmResponse $r): ?float
    {
        $vals = [];
        for ($i = 0; $i <= 8; $i++) {
            $v = $r->{"sqd{$i}"};
            if ($v && $v < 6) $vals[] = $v;
        }
        return count($vals) ? round(array_sum($vals) / count($vals), 2) : null;
    }

    private function sqdAvgFromArray(array $row): ?float
    {
        $vals = [];
        for ($i = 0; $i <= 8; $i++) {
            $v = $row["sqd{$i}"] ?? null;
            if ($v && $v < 6) $vals[] = $v;
        }
        return count($vals) ? round(array_sum($vals) / count($vals), 2) : null;
    }

    private function adjectival(?float $avg): string
    {
        if ($avg === null) return '—';
        if ($avg >= 4.5) return 'Excellent';
        if ($avg >= 3.5) return 'Very Good';
        if ($avg >= 2.5) return 'Satisfactory';
        if ($avg >= 1.5) return 'Fair';
        return 'Poor';
    }

    // ── DASHBOARD ─────────────────────────────────────────────────────────────

    public function dashboard()
    {
        abort_unless($this->isAdmin(), 403);

        $now   = Carbon::now();
        $total = CsmResponse::count();
        $thisMonth = CsmResponse::whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)->count();

        // Overall SQD average (all responses, all dimensions, exclude N/A)
        $sqdAvgs = [];
        for ($i = 0; $i <= 8; $i++) {
            $sqdAvgs["sqd{$i}"] = round(
                CsmResponse::where("sqd{$i}", '<', 6)->avg("sqd{$i}") ?? 0, 2
            );
        }
        $overallAvg = count(array_filter($sqdAvgs))
            ? round(array_sum($sqdAvgs) / count(array_filter($sqdAvgs)), 2)
            : 0;

        // By module
        $byModule = CsmResponse::selectRaw('respondable_type, COUNT(*) as count')
            ->groupBy('respondable_type')
            ->get()
            ->map(fn ($r) => [
                'label' => self::MODULE_LABELS[$r->respondable_type] ?? $r->respondable_type,
                'count' => (int) $r->count,
            ])->values();

        // By client type
        $byClientType = CsmResponse::selectRaw('client_type, COUNT(*) as count')
            ->groupBy('client_type')
            ->pluck('count', 'client_type');

        // By sex
        $bySex = CsmResponse::selectRaw('sex, COUNT(*) as count')
            ->whereNotNull('sex')
            ->groupBy('sex')
            ->pluck('count', 'sex');

        // Monthly trend (last 12 months)
        $monthlyTrend = collect(range(11, 0))->map(function ($i) use ($now) {
            $d = $now->copy()->subMonths($i);
            return [
                'month' => $d->format('M Y'),
                'count' => CsmResponse::whereYear('created_at', $d->year)
                    ->whereMonth('created_at', $d->month)->count(),
            ];
        })->values();

        // CC1 breakdown
        $cc1Breakdown = CsmResponse::selectRaw('cc1, COUNT(*) as count')
            ->whereNotNull('cc1')
            ->groupBy('cc1')
            ->orderBy('cc1')
            ->pluck('count', 'cc1');

        // Adjectival distribution (computed from overall avg per response)
        $adjectivalDist = ['Excellent' => 0, 'Very Good' => 0, 'Satisfactory' => 0, 'Fair' => 0, 'Poor' => 0];
        CsmResponse::select(['sqd0','sqd1','sqd2','sqd3','sqd4','sqd5','sqd6','sqd7','sqd8'])->get()->each(function ($r) use (&$adjectivalDist) {
            $avg = $this->sqdAvg($r);
            $adj = $this->adjectival($avg);
            if (isset($adjectivalDist[$adj])) $adjectivalDist[$adj]++;
        });

        return Inertia::render('CSM/Dashboard', [
            'total'           => $total,
            'thisMonth'       => $thisMonth,
            'overallAvg'      => $overallAvg,
            'sqdAvgs'         => $sqdAvgs,
            'byModule'        => $byModule,
            'byClientType'    => $byClientType,
            'bySex'           => $bySex,
            'monthlyTrend'    => $monthlyTrend,
            'cc1Breakdown'    => $cc1Breakdown,
            'adjectivalDist'  => $adjectivalDist,
        ]);
    }

    // ── LIST ──────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        abort_unless($this->isAdmin(), 403);

        $query = CsmResponse::with('user:id,name')
            ->orderByDesc('created_at');

        if ($request->filled('module')) {
            $query->where('respondable_type', $request->module);
        }
        if ($request->filled('client_type')) {
            $query->where('client_type', $request->client_type);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('month')) {
            [$y, $m] = explode('-', $request->month);
            $query->whereYear('created_at', $y)->whereMonth('created_at', $m);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$s}%"))
                  ->orWhere('suggestions', 'like', "%{$s}%")
                  ->orWhere('office_availed', 'like', "%{$s}%");
            });
        }

        $responses = $query->paginate(20)->withQueryString();

        // Append computed fields
        $responses->getCollection()->transform(function ($r) {
            $avg = $this->sqdAvg($r);
            $r->avg_sqd        = $avg;
            $r->adjectival     = $this->adjectival($avg);
            $r->module_label   = self::MODULE_LABELS[$r->respondable_type] ?? $r->respondable_type;
            return $r;
        });

        return Inertia::render('CSM/Index', [
            'responses'    => $responses,
            'moduleLabels' => self::MODULE_LABELS,
            'filters'      => $request->only(['module', 'client_type', 'date_from', 'date_to', 'month', 'search']),
        ]);
    }

    // ── SHOW ──────────────────────────────────────────────────────────────────

    public function show(CsmResponse $csmResponse)
    {
        abort_unless($this->isAdmin(), 403);

        $csmResponse->load('user:id,name,position');

        // Load related request for context
        $related = null;
        try {
            $model = $csmResponse->respondable;
            if ($model) {
                $related = [
                    'module' => self::MODULE_LABELS[$csmResponse->respondable_type] ?? $csmResponse->respondable_type,
                    'title'  => $model->title ?? $model->activity ?? $model->service_type ?? $model->purpose ?? "#{$model->id}",
                    'status' => $model->status ?? null,
                    'id'     => $model->id,
                ];
            }
        } catch (\Throwable) {}

        $avg = $this->sqdAvg($csmResponse);

        return Inertia::render('CSM/Show', [
            'response'   => $csmResponse,
            'related'    => $related,
            'avgSqd'     => $avg,
            'adjectival' => $this->adjectival($avg),
            'moduleLabel'=> self::MODULE_LABELS[$csmResponse->respondable_type] ?? $csmResponse->respondable_type,
        ]);
    }

    // ── EXPORT ────────────────────────────────────────────────────────────────

    public function export(Request $request)
    {
        abort_unless($this->isAdmin(), 403);

        $query = CsmResponse::with('user:id,name')->orderByDesc('created_at');

        if ($request->filled('module'))      $query->where('respondable_type', $request->module);
        if ($request->filled('client_type')) $query->where('client_type', $request->client_type);
        if ($request->filled('date_from'))   $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->filled('date_to'))     $query->whereDate('created_at', '<=', $request->date_to);
        if ($request->filled('month')) {
            [$y, $m] = explode('-', $request->month);
            $query->whereYear('created_at', $y)->whereMonth('created_at', $m);
        }

        $responses = $query->get();

        $spreadsheet = new Spreadsheet();
        $ws = $spreadsheet->getActiveSheet();
        $ws->setTitle('CSM Feedback');

        // Header
        $headers = [
            'Date', 'Module', 'Respondent', 'Client Type', 'Sex', 'Age',
            'Region', 'Office Availed', 'Service Availed',
            'CC1', 'CC2', 'CC3',
            'SQD0', 'SQD1', 'SQD2', 'SQD3', 'SQD4', 'SQD5', 'SQD6', 'SQD7', 'SQD8',
            'Avg SQD', 'Adjectival', 'Suggestions',
        ];
        foreach ($headers as $col => $header) {
            $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1) . '1';
            $ws->setCellValue($cell, $header);
        }
        $ws->getStyle('A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . '1')
           ->getFont()->setBold(true);
        $ws->getStyle('A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . '1')
           ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('BDD7EE');

        foreach ($responses as $i => $r) {
            $row = $i + 2;
            $avg = $this->sqdAvg($r);
            $data = [
                $r->created_at?->format('m/d/Y'),
                self::MODULE_LABELS[$r->respondable_type] ?? $r->respondable_type,
                $r->user?->name ?? '—',
                $r->client_type,
                $r->sex,
                $r->age,
                $r->region_of_residence,
                $r->office_availed,
                is_array($r->service_availed) ? implode(', ', $r->service_availed) : $r->service_availed,
                $r->cc1, $r->cc2, $r->cc3,
                $r->sqd0, $r->sqd1, $r->sqd2, $r->sqd3, $r->sqd4,
                $r->sqd5, $r->sqd6, $r->sqd7, $r->sqd8,
                $avg,
                $this->adjectival($avg),
                $r->suggestions,
            ];
            foreach ($data as $col => $val) {
                $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1) . $row;
                $ws->setCellValue($cell, $val);
            }
        }

        // Auto-width
        foreach (range(1, count($headers)) as $col) {
            $ws->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'csm_export_') . '.xlsx';
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($tempFile);

        $filename = 'CSM_Feedback_' . now()->format('Y-m-d') . '.xlsx';
        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }
}
