<?php

namespace App\Http\Controllers\PPMP;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\PPMP\Ppmp;
use App\Models\PPMP\PpmpItem;
use App\Models\PPMP\PpmpSetting;
use App\Models\PPMP\PpmpStatusHistory;
use App\Services\PPMP\ComplianceValidationService;
use App\Services\PPMP\CostComputationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PPMPController extends Controller
{
    public function __construct(
        private ComplianceValidationService $complianceService,
        private CostComputationService $costService,
    ) {
    }

    /**
     * List PPMPs — own division or all (based on permission).
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Ppmp::class);

        $user = $request->user();
        $fiscalYear = $request->input('fiscal_year', (int) date('Y'));
        $status = $request->input('status');

        $query = Ppmp::with(['division:id,division_name,acronym', 'preparer:id,name'])
            ->forYear($fiscalYear)
            ->latest();

        // Scope to own division unless user can view all
        if (!$user->hasPermission('ppmp.view_all')) {
            if (!$user->division_id) {
                $ppmps = collect();
                return Inertia::render('PPMP/Index', [
                    'ppmps'       => $ppmps,
                    'filters'     => ['fiscal_year' => $fiscalYear, 'status' => $status, 'division_id' => null],
                    'fiscalYears' => collect([(int) date('Y')]),
                    'divisions'   => [],
                    'deadline'    => null,
                    'canCreate'   => false,
                    'canReview'   => false,
                ]);
            }
            $query->forDivision($user->division_id);
        } elseif ($request->filled('division_id')) {
            $query->forDivision($request->input('division_id'));
        }

        if ($status) {
            $query->byStatus($status);
        }

        $ppmps = $query->get()->map(fn ($p) => array_merge($p->toArray(), [
            'grand_total' => $p->grandTotal(),
            'item_count'  => $p->items()->count(),
        ]));

        $deadline = PpmpSetting::getValue("submission_deadline_{$fiscalYear}");

        return Inertia::render('PPMP/Index', [
            'ppmps'       => $ppmps,
            'filters'     => [
                'fiscal_year' => $fiscalYear,
                'status'      => $status,
                'division_id' => $request->input('division_id'),
            ],
            'fiscalYears' => Ppmp::distinct()->pluck('fiscal_year')->push((int) date('Y'))->unique()->sort()->values(),
            'divisions'   => $user->hasPermission('ppmp.view_all')
                ? Division::where('status', 'active')->orderBy('division_name')->get(['id', 'division_name', 'acronym'])
                : [],
            'deadline'    => $deadline,
            'canCreate'          => $user->hasPermission('ppmp.create'),
            'canReview'          => $user->hasPermission('ppmp.review') || $user->hasPermission('ppmp.approve'),
            'canManageCatalogue' => $user->hasPermission('ppmp.consolidate') || $user->isSuperAdmin(),
        ]);
    }

    /**
     * Show PPMP creation form.
     */
    public function create(Request $request)
    {
        $this->authorize('create', Ppmp::class);

        $user = $request->user();

        if (!$user->division_id) {
            return back()->with('error', 'You must be assigned to a division to create a PPMP.');
        }

        // Previous PPMPs for duplication
        $previousPpmps = Ppmp::forDivision($user->division_id)
            ->where('fiscal_year', '<', (int) date('Y') + 1)
            ->with('division:id,division_name,acronym')
            ->latest('fiscal_year')
            ->get(['id', 'ppmp_number', 'title', 'fiscal_year', 'division_id']);

        // Approved PPMPs that can be supplemented (same year)
        $supplementablePpmps = Ppmp::forDivision($user->division_id)
            ->whereIn('status', [Ppmp::STATUS_APPROVED, Ppmp::STATUS_CONSOLIDATED])
            ->where('is_supplemental', false)
            ->latest('fiscal_year')
            ->get(['id', 'ppmp_number', 'title', 'fiscal_year']);

        return Inertia::render('PPMP/Create', [
            'userDivision'        => $user->division,
            'userOffice'          => $user->office,
            'fiscalYears'         => range((int) date('Y'), (int) date('Y') + 2),
            'previousPpmps'       => $previousPpmps,
            'supplementablePpmps' => $supplementablePpmps,
        ]);
    }

    /**
     * Store a new PPMP.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Ppmp::class);

        $user = $request->user();

        $data = $request->validate([
            'fiscal_year'      => 'required|integer|min:2020',
            'title'            => 'required|string|max:255',
            'source_ppmp_id'   => 'nullable|integer|exists:ppmp,id',
            'is_supplemental'  => 'boolean',
            'parent_ppmp_id'   => 'nullable|integer|exists:ppmp,id',
        ]);

        $isSupplemental = ! empty($data['is_supplemental']);

        // For regular PPMPs, check for existing active one
        if (! $isSupplemental) {
            $exists = Ppmp::forDivision($user->division_id)
                ->forYear($data['fiscal_year'])
                ->where('is_supplemental', false)
                ->whereIn('status', [Ppmp::STATUS_DRAFT, Ppmp::STATUS_PENDING_BAC, Ppmp::STATUS_SUBMITTED])
                ->exists();

            if ($exists) {
                return back()->with('error', 'An active PPMP already exists for your unit for this fiscal year.');
            }
        }

        $division = $user->division;
        if (!$division) {
            return back()->with('error', 'You must be assigned to a division to create a PPMP.');
        }

        $parentNumber = null;
        if ($isSupplemental && ! empty($data['parent_ppmp_id'])) {
            $parent = Ppmp::find($data['parent_ppmp_id']);
            $parentNumber = $parent?->ppmp_number;
        }

        $ppmp = Ppmp::create([
            'ppmp_number'    => Ppmp::generateNumber($data['fiscal_year'], $division, $isSupplemental, $parentNumber),
            'title'          => $data['title'],
            'fiscal_year'    => $data['fiscal_year'],
            'division_id'    => $user->division_id,
            'office_id'      => $user->office_id,
            'prepared_by'    => $user->id,
            'status'         => Ppmp::STATUS_DRAFT,
            'is_supplemental'=> $isSupplemental,
            'parent_ppmp_id' => $isSupplemental ? ($data['parent_ppmp_id'] ?? null) : null,
        ]);

        // Log initial status
        PpmpStatusHistory::create([
            'ppmp_id'     => $ppmp->id,
            'from_status' => null,
            'to_status'   => Ppmp::STATUS_DRAFT,
            'acted_by'    => $user->id,
        ]);

        // Duplicate items from source PPMP if requested
        if (!empty($data['source_ppmp_id'])) {
            $source = Ppmp::find($data['source_ppmp_id']);
            if ($source && $source->division_id === $user->division_id) {
                foreach ($source->items as $item) {
                    $newItem = $item->replicate(['id', 'ppmp_id', 'created_at', 'updated_at']);
                    $newItem->ppmp_id = $ppmp->id;
                    $newItem->unit_cost = 0;
                    // total_quantity and total_cost are recomputed by the saving event
                    $newItem->save();
                }
            }
        }

        return redirect()->route('ppmp.show', $ppmp)->with('success', 'PPMP created.');
    }

    /**
     * Show PPMP detail with items.
     */
    public function show(Ppmp $ppmp)
    {
        $this->authorize('view', $ppmp);

        $user = auth()->user();

        $ppmp->load([
            'division:id,division_name,acronym',
            'office:id,name',
            'preparer:id,name,position',
            'approver:id,name,position',
            'bacReviewer:id,name',
            'parentPpmp:id,ppmp_number,title',
            'statusHistory.actor:id,name',
        ]);

        $items = $ppmp->items()->with('catalogue:id,part')->get()->map(function ($item) {
            $arr = $item->toArray();
            $arr['catalogue_part'] = $item->catalogue?->part;
            return $arr;
        })->values();
        $summary = $this->costService->computePPMPSummary($ppmp->id);

        // PR utilization: PRs linked to this PPMP
        $utilization = DB::table('procurement_items')
            ->join('procurements', 'procurement_items.procurement_id', '=', 'procurements.id')
            ->where('procurements.ppmp_id', $ppmp->id)
            ->selectRaw('COUNT(DISTINCT procurements.id) as pr_count, COALESCE(SUM(procurement_items.quantity * procurement_items.unit_cost), 0) as pr_total')
            ->first();

        return Inertia::render('PPMP/Show', [
            'ppmp'           => $ppmp,
            'items'          => $items,
            'summary'        => $summary,
            'grandTotal'     => $ppmp->grandTotal(),
            'categories'     => PpmpItem::CATEGORY_LABELS,
            'methods'        => PpmpItem::PROCUREMENT_METHODS,
            'fundSources'    => PpmpItem::FUND_SOURCES,
            'quarters'       => PpmpItem::QUARTERS,
            'canEdit'        => $user->can('update', $ppmp),
            'canSubmit'      => $user->can('submit', $ppmp),
            'canBacReview'   => ($user->hasPermission('ppmp.bac_review') || $user->isSuperAdmin()) && $ppmp->canBacReview(),
            'canApprove'     => $user->can('approve', $ppmp),
            'canExport'      => $user->can('export', $ppmp),
            'utilization'    => [
                'pr_count' => (int) $utilization->pr_count,
                'pr_total' => (float) $utilization->pr_total,
            ],
        ]);
    }

    /**
     * Update PPMP header.
     */
    public function update(Request $request, Ppmp $ppmp)
    {
        $this->authorize('update', $ppmp);

        $data = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $ppmp->update($data);

        return back()->with('success', 'PPMP updated.');
    }

    /**
     * Delete a draft PPMP.
     */
    public function destroy(Ppmp $ppmp)
    {
        $this->authorize('delete', $ppmp);

        $ppmp->delete();

        return redirect()->route('ppmp.index')->with('success', 'PPMP deleted.');
    }

    /**
     * Run compliance validation (on-demand, returns JSON).
     */
    public function validatePpmp(Ppmp $ppmp)
    {
        $this->authorize('update', $ppmp);

        $items = $ppmp->items;
        $result = $this->complianceService->validatePPMP($ppmp, $items);

        return response()->json($result);
    }

    /**
     * Submit PPMP for review.
     */
    public function submit(Ppmp $ppmp)
    {
        $this->authorize('submit', $ppmp);

        $items = $ppmp->items;
        $result = $this->complianceService->validatePPMP($ppmp, $items);

        if (!$result['valid']) {
            return back()
                ->with('error', 'PPMP has validation errors. Please fix them before submitting.')
                ->with('validation_result', $result);
        }

        $ppmp->submit(auth()->user());

        return back()->with('success', 'PPMP submitted for review.');
    }

    /**
     * Approve a submitted PPMP.
     */
    public function approve(Ppmp $ppmp)
    {
        $this->authorize('approve', $ppmp);

        $ppmp->approve(auth()->user());

        return back()->with('success', 'PPMP approved.');
    }

    /**
     * Return a submitted PPMP for revision.
     */
    public function returnPpmp(Request $request, Ppmp $ppmp)
    {
        $this->authorize('returnForRevision', $ppmp);

        $data = $request->validate([
            'remarks' => 'required|string|max:1000',
        ]);

        $ppmp->returnForRevision(auth()->user(), $data['remarks']);

        return back()->with('success', 'PPMP returned for revision.');
    }

    /**
     * BAC / Procurement Officer endorsement or return.
     */
    public function bacReview(Request $request, Ppmp $ppmp)
    {
        $user = $request->user();
        abort_unless($user->hasPermission('ppmp.bac_review') || $user->isSuperAdmin(), 403);
        abort_unless($ppmp->canBacReview(), 422, 'PPMP is not awaiting BAC review.');

        $data = $request->validate([
            'action'  => 'required|in:endorse,return',
            'remarks' => 'nullable|string|max:1000',
        ]);

        if ($data['action'] === 'return') {
            abort_unless(filled($data['remarks']), 422, 'Remarks are required when returning a PPMP.');
            $ppmp->bacReturn($user, $data['remarks']);
            return back()->with('success', 'PPMP returned to end-user for revision.');
        }

        $ppmp->endorse($user);
        return back()->with('success', 'PPMP endorsed and forwarded to approver.');
    }
}
