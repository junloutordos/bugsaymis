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
            'canCreate'   => $user->hasPermission('ppmp.create'),
            'canReview'   => $user->hasPermission('ppmp.review') || $user->hasPermission('ppmp.approve'),
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

        return Inertia::render('PPMP/Create', [
            'userDivision' => $user->division,
            'userOffice'   => $user->office,
            'fiscalYears'  => range((int) date('Y'), (int) date('Y') + 2),
            'previousPpmps' => $previousPpmps,
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
            'fiscal_year'    => 'required|integer|min:2020',
            'title'          => 'required|string|max:255',
            'source_ppmp_id' => 'nullable|integer|exists:ppmp,id',
        ]);

        // Check for existing active PPMP
        $exists = Ppmp::forDivision($user->division_id)
            ->forYear($data['fiscal_year'])
            ->whereIn('status', [Ppmp::STATUS_DRAFT, Ppmp::STATUS_SUBMITTED])
            ->exists();

        if ($exists) {
            return back()->with('error', 'An active PPMP already exists for your unit for this fiscal year.');
        }

        $division = $user->division;
        if (!$division) {
            return back()->with('error', 'You must be assigned to a division to create a PPMP.');
        }

        $ppmp = Ppmp::create([
            'ppmp_number' => Ppmp::generateNumber($data['fiscal_year'], $division),
            'title'       => $data['title'],
            'fiscal_year' => $data['fiscal_year'],
            'division_id' => $user->division_id,
            'office_id'   => $user->office_id,
            'prepared_by' => $user->id,
            'status'      => Ppmp::STATUS_DRAFT,
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
            'statusHistory.actor:id,name',
        ]);

        $items = $ppmp->items()->get();
        $summary = $this->costService->computePPMPSummary($ppmp->id);

        return Inertia::render('PPMP/Show', [
            'ppmp'           => $ppmp,
            'items'          => $items,
            'summary'        => $summary,
            'grandTotal'     => $ppmp->grandTotal(),
            'categories'     => PpmpItem::CATEGORY_LABELS,
            'methods'        => PpmpItem::PROCUREMENT_METHODS,
            'canEdit'        => $user->can('update', $ppmp),
            'canSubmit'      => $user->can('submit', $ppmp),
            'canApprove'     => $user->can('approve', $ppmp),
            'canExport'      => $user->can('export', $ppmp),
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
}
