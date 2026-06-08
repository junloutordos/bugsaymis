<?php

namespace App\Http\Controllers\PPMP;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Office;
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

    public function index(Request $request)
    {
        $this->authorize('viewAny', Ppmp::class);

        $user = $request->user();
        $fiscalYear = $request->input('fiscal_year', (int) date('Y'));
        $status = $request->input('status');

        $isDivisionReviewer  = $user->hasPermission('ppmp.division_review') || $user->isSuperAdmin();
        $isPropertyOfficer   = $user->hasPermission('ppmp.property_officer_review') && !$user->isSuperAdmin();
        $isBudgetOfficer     = $user->hasPermission('ppmp.budget_officer_review') && !$user->isSuperAdmin();
        $isOcd               = $user->hasPermission('ppmp.approve') && !$user->isSuperAdmin();

        $query = Ppmp::with(['division:id,division_name,acronym', 'office:id,name', 'preparer:id,name'])
            ->forYear($fiscalYear)
            ->latest();

        if ($user->hasPermission('ppmp.view_all') || $user->isSuperAdmin()) {
            if ($request->filled('division_id')) {
                $query->forDivision($request->input('division_id'));
            }
        } elseif ($isPropertyOfficer) {
            // Property Officer sees all Division PPMPs and Property PPMPs
            $query->whereIn('ppmp_type', [Ppmp::PPMP_TYPE_DIVISION, Ppmp::PPMP_TYPE_PROPERTY]);
        } elseif ($isBudgetOfficer || $isOcd) {
            // Budget Officer and OCD see all Property PPMPs
            $query->where('ppmp_type', Ppmp::PPMP_TYPE_PROPERTY);
        } else {
            if (!$user->division_id) {
                return Inertia::render('PPMP/Index', $this->emptyIndexProps($fiscalYear, $status));
            }

            if ($isDivisionReviewer) {
                $query->forDivision($user->division_id);
            } elseif ($user->office_id) {
                $query->where('office_id', $user->office_id);
            } else {
                $query->forDivision($user->division_id);
            }
        }

        if ($status) {
            $query->byStatus($status);
        }

        $ppmps = $query->get()->map(fn ($p) => array_merge($p->toArray(), [
            'grand_total' => $p->grandTotal(),
            'item_count'  => $p->items()->count(),
        ]));

        $deadline = PpmpSetting::getValue("submission_deadline_{$fiscalYear}");

        // Approvable unit PPMPs (for Division Chief consolidation banner)
        $approvableUnitPpmps = $isDivisionReviewer && $user->division_id
            ? Ppmp::forDivision($user->division_id)
                ->forYear($fiscalYear)
                ->where('ppmp_type', Ppmp::PPMP_TYPE_UNIT)
                ->where('status', Ppmp::STATUS_DIVISION_APPROVED)
                ->whereNull('division_ppmp_id')
                ->with('office:id,name')
                ->get(['id', 'ppmp_number', 'title', 'office_id'])
                ->map(fn ($p) => [
                    'id'          => $p->id,
                    'ppmp_number' => $p->ppmp_number,
                    'title'       => $p->title,
                    'office_name' => $p->office?->name,
                    'grand_total' => $p->grandTotal(),
                ])
            : [];

        // Pending division review (for Division Chief banner)
        $pendingDivisionPpmps = $isDivisionReviewer && $user->division_id
            ? Ppmp::forDivision($user->division_id)
                ->forYear($fiscalYear)
                ->where('ppmp_type', Ppmp::PPMP_TYPE_UNIT)
                ->where('status', Ppmp::STATUS_PENDING_DIVISION)
                ->with('office:id,name')
                ->get(['id', 'ppmp_number', 'title', 'office_id'])
                ->map(fn ($p) => [
                    'id'          => $p->id,
                    'ppmp_number' => $p->ppmp_number,
                    'title'       => $p->title,
                    'office_name' => $p->office?->name,
                ])
            : [];

        // Approvable division PPMPs (for Property Officer consolidation banner)
        $approvableDivisionPpmps = $isPropertyOfficer
            ? Ppmp::forYear($fiscalYear)
                ->where('ppmp_type', Ppmp::PPMP_TYPE_DIVISION)
                ->where('status', Ppmp::STATUS_PROPERTY_OFFICER_APPROVED)
                ->whereNull('property_ppmp_id')
                ->with('division:id,division_name,acronym')
                ->get(['id', 'ppmp_number', 'title', 'division_id'])
                ->map(fn ($p) => [
                    'id'            => $p->id,
                    'ppmp_number'   => $p->ppmp_number,
                    'title'         => $p->title,
                    'division_name' => $p->division?->division_name,
                    'grand_total'   => $p->grandTotal(),
                ])
            : [];

        // Pending property officer review (for Property Officer banner)
        $pendingPropertyOfficerPpmps = $isPropertyOfficer
            ? Ppmp::forYear($fiscalYear)
                ->where('ppmp_type', Ppmp::PPMP_TYPE_DIVISION)
                ->where('status', Ppmp::STATUS_PENDING_PROPERTY_OFFICER)
                ->with('division:id,division_name')
                ->get(['id', 'ppmp_number', 'title', 'division_id'])
                ->map(fn ($p) => [
                    'id'            => $p->id,
                    'ppmp_number'   => $p->ppmp_number,
                    'title'         => $p->title,
                    'division_name' => $p->division?->division_name,
                ])
            : [];

        return Inertia::render('PPMP/Index', [
            'ppmps'                      => $ppmps,
            'filters'                    => [
                'fiscal_year' => $fiscalYear,
                'status'      => $status,
                'division_id' => $request->input('division_id'),
            ],
            'fiscalYears'                => Ppmp::distinct()->pluck('fiscal_year')->push((int) date('Y'))->unique()->sort()->values(),
            'divisions'                  => ($user->hasPermission('ppmp.view_all') || $user->isSuperAdmin())
                ? Division::where('status', 'active')->orderBy('division_name')->get(['id', 'division_name', 'acronym'])
                : [],
            'deadline'                   => $deadline,
            'canCreate'                  => $user->hasPermission('ppmp.create'),
            'canReview'                  => $user->hasPermission('ppmp.review') || $user->hasPermission('ppmp.approve'),
            'canDivisionReview'          => $isDivisionReviewer,
            'canPropertyOfficerReview'   => $isPropertyOfficer,
            'canManageCatalogue'         => $user->hasPermission('ppmp.consolidate') || $user->isSuperAdmin(),
            'approvableUnitPpmps'        => $approvableUnitPpmps,
            'pendingDivisionPpmps'       => $pendingDivisionPpmps,
            'approvableDivisionPpmps'    => $approvableDivisionPpmps,
            'pendingPropertyOfficerPpmps'=> $pendingPropertyOfficerPpmps,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Ppmp::class);

        $user = $request->user();

        if (!$user->division_id) {
            return back()->with('error', 'You must be assigned to a division to create a PPMP.');
        }

        $unitScope = $user->office_id
            ? Ppmp::where('office_id', $user->office_id)
            : Ppmp::forDivision($user->division_id);

        $previousPpmps = (clone $unitScope)
            ->where('fiscal_year', '<', (int) date('Y') + 1)
            ->with('division:id,division_name,acronym')
            ->latest('fiscal_year')
            ->get(['id', 'ppmp_number', 'title', 'fiscal_year', 'division_id']);

        $supplementablePpmps = (clone $unitScope)
            ->whereIn('status', [Ppmp::STATUS_APPROVED, Ppmp::STATUS_CONSOLIDATED])
            ->where('is_supplemental', false)
            ->latest('fiscal_year')
            ->get(['id', 'ppmp_number', 'title', 'fiscal_year']);

        return Inertia::render('PPMP/Create', [
            'userDivision'        => Division::find($user->division_id),
            'userOffice'          => Office::find($user->office_id),
            'fiscalYears'         => range((int) date('Y'), (int) date('Y') + 2),
            'previousPpmps'       => $previousPpmps,
            'supplementablePpmps' => $supplementablePpmps,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Ppmp::class);

        $user = $request->user();

        $data = $request->validate([
            'fiscal_year'     => 'required|integer|min:2020',
            'title'           => 'required|string|max:255',
            'source_ppmp_id'  => 'nullable|integer|exists:ppmp,id',
            'is_supplemental' => 'boolean',
            'parent_ppmp_id'  => 'nullable|integer|exists:ppmp,id',
        ]);

        $isSupplemental = !empty($data['is_supplemental']);

        if (!$isSupplemental) {
            $scope = $user->office_id
                ? Ppmp::where('office_id', $user->office_id)
                : Ppmp::forDivision($user->division_id);

            $exists = $scope
                ->forYear($data['fiscal_year'])
                ->where('is_supplemental', false)
                ->whereNotIn('status', [Ppmp::STATUS_APPROVED, Ppmp::STATUS_CONSOLIDATED])
                ->exists();

            if ($exists) {
                return back()->with('error', 'An active PPMP already exists for your office for this fiscal year.');
            }
        }

        $division = Division::find($user->division_id);
        if (!$division) {
            return back()->with('error', 'You must be assigned to a division to create a PPMP.');
        }

        $parentNumber = null;
        if ($isSupplemental && !empty($data['parent_ppmp_id'])) {
            $parent = Ppmp::find($data['parent_ppmp_id']);
            $parentNumber = $parent?->ppmp_number;
        }

        $ppmp = Ppmp::create([
            'ppmp_number'     => Ppmp::generateNumber($data['fiscal_year'], $division, $isSupplemental, $parentNumber),
            'title'           => $data['title'],
            'fiscal_year'     => $data['fiscal_year'],
            'division_id'     => $user->division_id,
            'office_id'       => $user->office_id,
            'prepared_by'     => $user->id,
            'status'          => Ppmp::STATUS_DRAFT,
            'is_supplemental' => $isSupplemental,
            'parent_ppmp_id'  => $isSupplemental ? ($data['parent_ppmp_id'] ?? null) : null,
        ]);

        PpmpStatusHistory::create([
            'ppmp_id'     => $ppmp->id,
            'from_status' => null,
            'to_status'   => Ppmp::STATUS_DRAFT,
            'acted_by'    => $user->id,
        ]);

        if (!empty($data['source_ppmp_id'])) {
            $source = Ppmp::find($data['source_ppmp_id']);
            if ($source && $source->division_id === $user->division_id) {
                foreach ($source->items as $item) {
                    $newItem = $item->replicate(['id', 'ppmp_id', 'created_at', 'updated_at']);
                    $newItem->ppmp_id = $ppmp->id;
                    $newItem->unit_cost = 0;
                    $newItem->save();
                }
            }
        }

        return redirect()->route('ppmp.show', $ppmp)->with('success', 'PPMP created.');
    }

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
            'divisionReviewer:id,name',
            'propertyOfficerReviewer:id,name',
            'budgetOfficerReviewer:id,name',
            'ocdReviewer:id,name',
            'dbmSubmitter:id,name',
            'parentPpmp:id,ppmp_number,title',
            'divisionPpmp:id,ppmp_number,title,status',
            'propertyPpmp:id,ppmp_number,title,status',
            'unitPpmps:id,ppmp_number,title,status,office_id,prepared_by',
            'statusHistory.actor:id,name',
        ]);

        // Load division PPMPs for property type
        $divisionPpmps = $ppmp->ppmp_type === Ppmp::PPMP_TYPE_PROPERTY
            ? $ppmp->divisionPpmps()->with('division:id,division_name,acronym')->get(['id', 'ppmp_number', 'title', 'status', 'division_id'])
            : collect();

        // Load unit PPMPs for division type
        $unitPpmps = $ppmp->ppmp_type === Ppmp::PPMP_TYPE_DIVISION
            ? $ppmp->unitPpmps()->with('office:id,name')->get(['id', 'ppmp_number', 'title', 'status', 'office_id'])
            : collect();

        $items = $ppmp->items()->with('catalogue:id,part')->get()->map(function ($item) {
            $arr = $item->toArray();
            $arr['catalogue_part'] = $item->catalogue?->part;
            return $arr;
        })->values();

        $summary = $this->costService->computePPMPSummary($ppmp->id);

        $utilization = DB::table('procurement_items')
            ->join('procurements', 'procurement_items.procurement_id', '=', 'procurements.id')
            ->where('procurements.ppmp_id', $ppmp->id)
            ->selectRaw('COUNT(DISTINCT procurements.id) as pr_count, COALESCE(SUM(procurement_items.quantity * procurement_items.unit_cost), 0) as pr_total')
            ->first();

        return Inertia::render('PPMP/Show', [
            'ppmp'                      => $ppmp,
            'items'                     => $items,
            'summary'                   => $summary,
            'grandTotal'                => $ppmp->grandTotal(),
            'categories'                => PpmpItem::CATEGORY_LABELS,
            'methods'                   => PpmpItem::PROCUREMENT_METHODS,
            'fundSources'               => PpmpItem::FUND_SOURCES,
            'quarters'                  => PpmpItem::QUARTERS,
            'canEdit'                   => $user->can('update', $ppmp),
            'canSubmit'                 => $user->can('submit', $ppmp),
            'canDivisionReview'         => $user->can('divisionReview', $ppmp),
            'canBacReview'              => ($user->hasPermission('ppmp.bac_review') || $user->isSuperAdmin()) && $ppmp->canBacReview(),
            'canPropertyOfficerReview'  => $user->can('propertyOfficerReview', $ppmp),
            'canBudgetOfficerReview'    => $user->can('budgetOfficerReview', $ppmp),
            'canOcdApprove'             => $user->can('ocdApprove', $ppmp),
            'canOcdReturn'              => $user->can('ocdReturn', $ppmp),
            'canSubmitToDbm'            => $user->can('submitToDbm', $ppmp),
            'canApprove'                => $user->can('approve', $ppmp),
            'canExport'                 => $user->can('export', $ppmp),
            'utilization'               => [
                'pr_count' => (int) $utilization->pr_count,
                'pr_total' => (float) $utilization->pr_total,
            ],
            'unitPpmps'     => $unitPpmps,
            'divisionPpmps' => $divisionPpmps,
        ]);
    }

    public function update(Request $request, Ppmp $ppmp)
    {
        $this->authorize('update', $ppmp);

        $data = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $ppmp->update($data);

        return back()->with('success', 'PPMP updated.');
    }

    public function destroy(Ppmp $ppmp)
    {
        $this->authorize('delete', $ppmp);

        $ppmp->delete();

        return redirect()->route('ppmp.index')->with('success', 'PPMP deleted.');
    }

    public function validatePpmp(Ppmp $ppmp)
    {
        $this->authorize('update', $ppmp);

        $items = $ppmp->items;
        $result = $this->complianceService->validatePPMP($ppmp, $items);

        return response()->json($result);
    }

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

        $messages = [
            Ppmp::PPMP_TYPE_UNIT     => 'PPMP submitted to Division Chief for review.',
            Ppmp::PPMP_TYPE_DIVISION => 'Division PPMP submitted to Property Officer for review.',
            Ppmp::PPMP_TYPE_PROPERTY => 'Property PPMP submitted to Budget Officer for evaluation.',
        ];

        return back()->with('success', $messages[$ppmp->ppmp_type] ?? 'PPMP submitted.');
    }

    public function approve(Ppmp $ppmp)
    {
        $this->authorize('approve', $ppmp);

        $ppmp->approve(auth()->user());

        return back()->with('success', 'PPMP approved.');
    }

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
     * Division Chief endorsement or return of unit PPMP.
     */
    public function divisionReview(Request $request, Ppmp $ppmp)
    {
        $this->authorize('divisionReview', $ppmp);

        $data = $request->validate([
            'action'  => 'required|in:endorse,return',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();

        if ($data['action'] === 'return') {
            abort_unless(filled($data['remarks']), 422, 'Remarks are required when returning a PPMP.');
            $ppmp->divisionReturn($user, $data['remarks']);
            return back()->with('success', 'PPMP returned to the unit for revision.');
        }

        $ppmp->divisionEndorse($user);
        return back()->with('success', 'Unit PPMP approved and marked for consolidation.');
    }

    /**
     * Division Chief consolidates all division_approved unit PPMPs into one Division PPMP.
     */
    public function divisionConsolidate(Request $request)
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermission('ppmp.division_review') || $user->isSuperAdmin(),
            403
        );
        abort_unless($user->division_id, 422, 'You must be assigned to a division.');

        $data = $request->validate([
            'fiscal_year' => 'required|integer|min:2020',
            'title'       => 'required|string|max:255',
        ]);

        $division = Division::find($user->division_id);
        abort_unless($division, 422, 'Division not found.');

        $unitPpmps = Ppmp::forDivision($user->division_id)
            ->forYear($data['fiscal_year'])
            ->where('ppmp_type', Ppmp::PPMP_TYPE_UNIT)
            ->where('status', Ppmp::STATUS_DIVISION_APPROVED)
            ->whereNull('division_ppmp_id')
            ->with('items')
            ->get();

        abort_if($unitPpmps->isEmpty(), 422, 'No approved unit PPMPs to consolidate.');

        $divisionPpmp = DB::transaction(function () use ($user, $data, $division, $unitPpmps) {
            $ppmp = Ppmp::create([
                'ppmp_number'     => Ppmp::generateDivisionNumber($data['fiscal_year'], $division),
                'title'           => $data['title'],
                'fiscal_year'     => $data['fiscal_year'],
                'division_id'     => $user->division_id,
                'office_id'       => null,
                'prepared_by'     => $user->id,
                'status'          => Ppmp::STATUS_DRAFT,
                'ppmp_type'       => Ppmp::PPMP_TYPE_DIVISION,
                'is_supplemental' => false,
            ]);

            PpmpStatusHistory::create([
                'ppmp_id'     => $ppmp->id,
                'from_status' => null,
                'to_status'   => Ppmp::STATUS_DRAFT,
                'acted_by'    => $user->id,
            ]);

            foreach ($unitPpmps as $unit) {
                foreach ($unit->items as $item) {
                    $newItem = $item->replicate(['id', 'ppmp_id', 'created_at', 'updated_at']);
                    $newItem->ppmp_id = $ppmp->id;
                    $newItem->save();
                }

                $unit->division_ppmp_id = $ppmp->id;
                $unit->save();
            }

            return $ppmp;
        });

        return redirect()->route('ppmp.show', $divisionPpmp)
            ->with('success', 'Division PPMP created with ' . $unitPpmps->count() . ' unit PPMP(s) consolidated.');
    }

    /**
     * Property Officer endorsement or return of division PPMP.
     */
    public function propertyOfficerReview(Request $request, Ppmp $ppmp)
    {
        $this->authorize('propertyOfficerReview', $ppmp);

        $data = $request->validate([
            'action'  => 'required|in:endorse,return',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();

        if ($data['action'] === 'return') {
            abort_unless(filled($data['remarks']), 422, 'Remarks are required when returning a PPMP.');
            $ppmp->propertyOfficerReturn($user, $data['remarks']);
            return back()->with('success', 'Division PPMP returned to the Division Chief for revision.');
        }

        $ppmp->propertyOfficerEndorse($user);
        return back()->with('success', 'Division PPMP approved and marked for Property PPMP consolidation.');
    }

    /**
     * Property Officer consolidates all property_officer_approved division PPMPs into one Property PPMP.
     */
    public function propertyConsolidate(Request $request)
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermission('ppmp.property_officer_review') || $user->isSuperAdmin(),
            403
        );

        $data = $request->validate([
            'fiscal_year' => 'required|integer|min:2020',
            'title'       => 'required|string|max:255',
        ]);

        $divisionPpmps = Ppmp::forYear($data['fiscal_year'])
            ->where('ppmp_type', Ppmp::PPMP_TYPE_DIVISION)
            ->where('status', Ppmp::STATUS_PROPERTY_OFFICER_APPROVED)
            ->whereNull('property_ppmp_id')
            ->with('items')
            ->get();

        abort_if($divisionPpmps->isEmpty(), 422, 'No approved division PPMPs to consolidate.');

        $propertyPpmp = DB::transaction(function () use ($user, $data, $divisionPpmps) {
            $ppmp = Ppmp::create([
                'ppmp_number'     => Ppmp::generatePropertyNumber($data['fiscal_year']),
                'title'           => $data['title'],
                'fiscal_year'     => $data['fiscal_year'],
                'division_id'     => null,
                'office_id'       => null,
                'prepared_by'     => $user->id,
                'status'          => Ppmp::STATUS_DRAFT,
                'ppmp_type'       => Ppmp::PPMP_TYPE_PROPERTY,
                'is_supplemental' => false,
            ]);

            PpmpStatusHistory::create([
                'ppmp_id'     => $ppmp->id,
                'from_status' => null,
                'to_status'   => Ppmp::STATUS_DRAFT,
                'acted_by'    => $user->id,
            ]);

            foreach ($divisionPpmps as $division) {
                foreach ($division->items as $item) {
                    $newItem = $item->replicate(['id', 'ppmp_id', 'created_at', 'updated_at']);
                    $newItem->ppmp_id = $ppmp->id;
                    $newItem->save();
                }

                $division->property_ppmp_id = $ppmp->id;
                $division->save();
            }

            return $ppmp;
        });

        return redirect()->route('ppmp.show', $propertyPpmp)
            ->with('success', 'Property PPMP created with ' . $divisionPpmps->count() . ' division PPMP(s) consolidated.');
    }

    /**
     * Budget Officer endorsement or return of property PPMP.
     */
    public function budgetOfficerReview(Request $request, Ppmp $ppmp)
    {
        $this->authorize('budgetOfficerReview', $ppmp);

        $data = $request->validate([
            'action'  => 'required|in:endorse,return',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();

        if ($data['action'] === 'return') {
            abort_unless(filled($data['remarks']), 422, 'Remarks are required when returning a PPMP.');
            $ppmp->budgetOfficerReturn($user, $data['remarks']);
            return back()->with('success', 'Property PPMP returned to Property Officer for revision.');
        }

        $ppmp->budgetOfficerEndorse($user);
        return back()->with('success', 'Property PPMP submitted to OCD for final approval.');
    }

    /**
     * OCD final approval of property PPMP.
     */
    public function ocdApprove(Ppmp $ppmp)
    {
        $this->authorize('ocdApprove', $ppmp);

        $ppmp->ocdApprove(auth()->user());

        return back()->with('success', 'PPMP/APP-CSE approved. Budget Officer may now submit to DBM.');
    }

    /**
     * OCD returns property PPMP for revision.
     */
    public function ocdReturn(Request $request, Ppmp $ppmp)
    {
        $this->authorize('ocdReturn', $ppmp);

        $data = $request->validate([
            'remarks' => 'required|string|max:1000',
        ]);

        $ppmp->ocdReturn(auth()->user(), $data['remarks']);

        return back()->with('success', 'PPMP returned to Budget Officer for revision.');
    }

    /**
     * Budget Officer submits approved property PPMP to DBM.
     */
    public function submitToDbm(Ppmp $ppmp)
    {
        $this->authorize('submitToDbm', $ppmp);

        $ppmp->submitToDbm(auth()->user());

        return back()->with('success', 'PPMP/APP-CSE submitted to DBM.');
    }

    /**
     * BAC / Procurement Officer endorsement or return (legacy).
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

    private function emptyIndexProps(int $fiscalYear, ?string $status): array
    {
        return [
            'ppmps'                       => collect(),
            'filters'                     => ['fiscal_year' => $fiscalYear, 'status' => $status, 'division_id' => null],
            'fiscalYears'                 => collect([(int) date('Y')]),
            'divisions'                   => [],
            'deadline'                    => null,
            'canCreate'                   => false,
            'canReview'                   => false,
            'canDivisionReview'           => false,
            'canPropertyOfficerReview'    => false,
            'canManageCatalogue'          => false,
            'approvableUnitPpmps'         => [],
            'pendingDivisionPpmps'        => [],
            'approvableDivisionPpmps'     => [],
            'pendingPropertyOfficerPpmps' => [],
        ];
    }
}
