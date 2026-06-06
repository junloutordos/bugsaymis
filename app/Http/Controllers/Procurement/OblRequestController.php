<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Procurement;
use App\Models\Procurement\OblRequest;
use App\Services\Procurement\ORSService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OblRequestController extends Controller
{
    public function __construct(private ORSService $service) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $canViewAll = $user->isSuperAdmin() || $user->hasAnyPermission([
            'procurement.ors.dc_sign', 'procurement.ors.budget_sign',
            'procurement.ors.bookkeep', 'procurement.ors.account', 'procurement.ors.ocd_sign',
        ]);

        $query = OblRequest::with(['creator:id,name', 'divisionChief:id,name', 'budgetOfficer:id,name'])->latest();
        if (! $canViewAll) {
            $query->where('created_by', $user->id);
        }

        // Pending-my-action
        $pendingQuery = OblRequest::with(['creator:id,name'])->latest();
        if ($user->hasPermission('procurement.ors.dc_sign')) {
            $pendingQuery->where('status', 'pending_dc');
        } elseif ($user->hasPermission('procurement.ors.budget_sign')) {
            $pendingQuery->where('status', 'pending_bo');
        } elseif ($user->hasPermission('procurement.ors.bookkeep')) {
            $pendingQuery->where('status', 'pending_bookkeeper');
        } elseif ($user->hasPermission('procurement.ors.account')) {
            $pendingQuery->where('status', 'pending_accountant');
        } elseif ($user->hasPermission('procurement.ors.ocd_sign')) {
            $pendingQuery->where('status', 'pending_ocd');
        } else {
            $pendingQuery->whereRaw('0=1');
        }

        return Inertia::render('Procurements/ORS/Index', [
            'orsRecords'    => $query->get()->map(fn ($o) => $this->formatORS($o)),
            'pendingAction' => $pendingQuery->get()->map(fn ($o) => $this->formatORS($o)),
            'prs'           => Procurement::where('status', 'approved')->orderByDesc('id')
                ->get(['id', 'pr_no', 'purpose'])
                ->map(fn ($p) => ['id' => $p->id, 'label' => "{$p->pr_no} — {$p->purpose}"]),
            'divisions'     => Division::where('status', '<>', 'inactive')->orderBy('division_name')->get(['id', 'division_name']),
            'currentUser'   => [
                'id'          => $user->id,
                'division_id' => $user->division_id,
                'permissions' => [
                    'canCreate'       => $user->hasPermission('procurement.ors.create'),
                    'canDcSign'       => $user->hasPermission('procurement.ors.dc_sign'),
                    'canBudgetSign'   => $user->hasPermission('procurement.ors.budget_sign'),
                    'canBookkeep'     => $user->hasPermission('procurement.ors.bookkeep'),
                    'canAccount'      => $user->hasPermission('procurement.ors.account'),
                    'canOcdSign'      => $user->hasPermission('procurement.ors.ocd_sign'),
                ],
            ],
        ]);
    }

    public function show(OblRequest $ors, Request $request)
    {
        $user = $request->user();

        $canView = $ors->created_by === $user->id
            || $user->isSuperAdmin()
            || $user->hasAnyPermission([
                'procurement.ors.dc_sign', 'procurement.ors.budget_sign',
                'procurement.ors.bookkeep', 'procurement.ors.account', 'procurement.ors.ocd_sign',
            ]);
        abort_unless($canView, 403);

        $ors->load(['creator:id,name', 'procurement:id,pr_no', 'division:id,division_name',
            'divisionChief:id,name', 'budgetOfficer:id,name', 'bookkeeper:id,name',
            'accountant:id,name', 'ocd:id,name', 'canvaser:id,name']);

        return Inertia::render('Procurements/ORS/Show', [
            'ors'         => $this->formatORS($ors, detailed: true),
            'currentUser' => [
                'id'          => $user->id,
                'permissions' => [
                    'canDcSign'     => $user->hasPermission('procurement.ors.dc_sign'),
                    'canBudgetSign' => $user->hasPermission('procurement.ors.budget_sign'),
                    'canBookkeep'   => $user->hasPermission('procurement.ors.bookkeep'),
                    'canAccount'    => $user->hasPermission('procurement.ors.account'),
                    'canOcdSign'    => $user->hasPermission('procurement.ors.ocd_sign'),
                ],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasPermission('procurement.ors.create') || $user->isSuperAdmin(), 403);

        $data = $request->validate([
            'procurement_id'  => 'nullable|exists:procurements,id',
            'po_number'       => 'nullable|string|max:50',
            'supplier_name'   => 'nullable|string|max:200',
            'account_title'   => 'nullable|string|max:200',
            'object_code'     => 'nullable|string|max:50',
            'activity_title'  => 'nullable|string|max:200',
            'activity_date'   => 'nullable|date',
            'amount'          => 'required|numeric|min:0',
            'division_id'     => 'nullable|exists:divisions,id',
        ]);

        OblRequest::create(array_merge($data, [
            'created_by' => $user->id,
            'division_id' => $data['division_id'] ?? $user->division_id,
            'status'     => 'draft',
        ]));

        return back()->with('success', 'ORS record created.');
    }

    public function submitForApproval(Request $request, OblRequest $ors)
    {
        $user = $request->user();
        abort_unless($ors->created_by === $user->id || $user->isSuperAdmin(), 403);
        abort_unless(in_array($ors->status, ['draft', 'returned_bo', 'returned_bookkeeper']), 422, 'Cannot submit at this stage.');

        $this->service->submit($ors, $user);

        return back()->with('success', 'ORS submitted for Division Chief signature.');
    }

    public function dcSign(Request $request, OblRequest $ors)
    {
        abort_unless($request->user()->hasPermission('procurement.ors.dc_sign') || $request->user()->isSuperAdmin(), 403);
        abort_unless($ors->status === 'pending_dc', 422);

        $this->service->dcSign($ors, $request->user());

        return back()->with('success', 'ORS signed by Division Chief.');
    }

    public function logAndForward(Request $request, OblRequest $ors)
    {
        $user = $request->user();
        abort_unless($ors->created_by === $user->id || $user->isSuperAdmin(), 403);
        abort_unless($ors->status === 'pending_bo', 422);

        $data = $request->validate([
            'log_po_number'       => 'nullable|string|max:50',
            'log_activity_title'  => 'nullable|string|max:200',
            'log_activity_date'   => 'nullable|date',
            'log_amount'          => 'nullable|numeric|min:0',
        ]);

        $this->service->logAndForward($ors, $user, $data);

        return back()->with('success', 'ORS logged and ready for Budget Officer.');
    }

    public function budgetOfficerAction(Request $request, OblRequest $ors)
    {
        abort_unless($request->user()->hasPermission('procurement.ors.budget_sign') || $request->user()->isSuperAdmin(), 403);
        abort_unless($ors->status === 'pending_bo', 422);

        $data = $request->validate([
            'action'     => 'required|in:approve,return',
            'ors_number' => 'nullable|string|max:50',
            'remarks'    => 'nullable|string|max:500',
        ]);

        $this->service->budgetOfficerAction($ors, $request->user(), $data['action'], $data['ors_number'] ?? '', $data['remarks'] ?? '');

        $msg = $data['action'] === 'approve' ? 'ORS numbered and forwarded to Bookkeeper.' : 'ORS returned to originator.';

        return back()->with('success', $msg);
    }

    public function bookkeeperAction(Request $request, OblRequest $ors)
    {
        abort_unless($request->user()->hasPermission('procurement.ors.bookkeep') || $request->user()->isSuperAdmin(), 403);
        abort_unless($ors->status === 'pending_bookkeeper', 422);

        $data = $request->validate([
            'action'  => 'required|in:forward,return',
            'remarks' => 'nullable|string|max:500',
        ]);

        $this->service->bookkeeperAction($ors, $request->user(), $data['action'], $data['remarks'] ?? '');

        $msg = $data['action'] === 'forward' ? 'ORS forwarded to Accountant.' : 'ORS returned for compliance.';

        return back()->with('success', $msg);
    }

    public function accountantSign(Request $request, OblRequest $ors)
    {
        abort_unless($request->user()->hasPermission('procurement.ors.account') || $request->user()->isSuperAdmin(), 403);
        abort_unless($ors->status === 'pending_accountant', 422);

        $data = $request->validate(['remarks' => 'nullable|string|max:500']);
        $this->service->accountantSign($ors, $request->user(), $data['remarks'] ?? '');

        return back()->with('success', 'ORS signed by Accountant and forwarded to Campus Director.');
    }

    public function ocdSign(Request $request, OblRequest $ors)
    {
        abort_unless($request->user()->hasPermission('procurement.ors.ocd_sign') || $request->user()->isSuperAdmin(), 403);
        abort_unless($ors->status === 'pending_ocd', 422);

        $this->service->ocdSign($ors, $request->user());

        return back()->with('success', 'ORS signed by Campus Director.');
    }

    public function canvaserReceive(Request $request, OblRequest $ors)
    {
        // Canvaser marks receipt (any authenticated user in canvaser role)
        abort_unless($ors->status === 'at_canvaser', 422);

        $this->service->canvaserReceive($ors, $request->user());

        return back()->with('success', 'ORS marked as completed — forwarded to supplier.');
    }

    // ── Format helper ─────────────────────────────────────────────────────────

    private function formatORS(OblRequest $o, bool $detailed = false): array
    {
        $base = [
            'id'             => $o->id,
            'ors_number'     => $o->ors_number,
            'po_number'      => $o->po_number,
            'supplier_name'  => $o->supplier_name,
            'activity_title' => $o->activity_title,
            'activity_date'  => $o->activity_date?->toDateString(),
            'amount'         => $o->amount,
            'status'         => $o->status,
            'status_label'   => $o->statusLabel(),
            'is_logged'      => $o->isLogged(),
            'creator'        => $o->creator?->only('id', 'name'),
            'created_at'     => $o->created_at?->toDateString(),
            'procurement'    => $o->procurement?->only('id', 'pr_no'),
        ];

        if ($detailed) {
            $base = array_merge($base, [
                'account_title'              => $o->account_title,
                'object_code'                => $o->object_code,
                'division'                   => $o->division?->only('id', 'division_name'),
                'logged_at'                  => $o->logged_at?->toISOString(),
                'log_po_number'              => $o->log_po_number,
                'log_activity_title'         => $o->log_activity_title,
                'log_activity_date'          => $o->log_activity_date?->toDateString(),
                'log_amount'                 => $o->log_amount,
                'division_chief'             => $o->divisionChief?->only('id', 'name'),
                'division_chief_at'          => $o->division_chief_at?->toISOString(),
                'budget_officer'             => $o->budgetOfficer?->only('id', 'name'),
                'budget_officer_at'          => $o->budget_officer_at?->toISOString(),
                'budget_officer_remarks'     => $o->budget_officer_remarks,
                'budget_officer_returned_at' => $o->budget_officer_returned_at?->toISOString(),
                'budget_officer_return_reason' => $o->budget_officer_return_reason,
                'bookkeeper'                 => $o->bookkeeper?->only('id', 'name'),
                'bookkeeper_at'              => $o->bookkeeper_at?->toISOString(),
                'bookkeeper_remarks'         => $o->bookkeeper_remarks,
                'bookkeeper_returned_at'     => $o->bookkeeper_returned_at?->toISOString(),
                'bookkeeper_return_reason'   => $o->bookkeeper_return_reason,
                'accountant'                 => $o->accountant?->only('id', 'name'),
                'accountant_at'              => $o->accountant_at?->toISOString(),
                'accountant_remarks'         => $o->accountant_remarks,
                'ocd'                        => $o->ocd?->only('id', 'name'),
                'ocd_at'                     => $o->ocd_at?->toISOString(),
                'canvaser'                   => $o->canvaser?->only('id', 'name'),
                'canvaser_at'                => $o->canvaser_at?->toISOString(),
            ]);
        }

        return $base;
    }
}
