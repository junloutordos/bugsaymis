<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\PPMP\Ppmp;
use App\Models\Procurement;
use App\Models\ProcurementItem;
use App\Models\Unit;
use App\Services\Procurement\PRPdfService;
use App\Services\Procurement\PRService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseRequestController extends Controller
{
    public function __construct(private PRService $service) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Procurement::with(['items', 'requester:id,name', 'divisionChief:id,name', 'procurementOfficer:id,name', 'ocd:id,name'])
            ->latest();

        $canViewAll = $user->isSuperAdmin() || $user->hasAnyPermission(['procurement.pr.dc_sign', 'procurement.pr.number', 'procurement.pr.ocd_sign']);

        if (! $canViewAll) {
            $query->where('requested_by', $user->id);
        }

        // Pending-my-action filter
        $pendingQuery = Procurement::with(['items', 'requester:id,name'])->latest();
        if ($user->hasPermission('procurement.pr.dc_sign')) {
            $pendingQuery->whereIn('status', ['pending_dc', 'supplemental_pending_dc']);
        } elseif ($user->hasPermission('procurement.pr.number')) {
            $pendingQuery->where('status', 'pending_procurement');
        } elseif ($user->hasPermission('procurement.pr.ocd_sign')) {
            $pendingQuery->whereIn('status', ['pending_ocd', 'supplemental_pending_ocd']);
        } elseif ($user->hasPermission('procurement.pr.bo_initial')) {
            $pendingQuery->where('status', 'supplemental_pending_bo');
        } else {
            $pendingQuery->whereRaw('0=1');
        }

        // Approved PPMPs for the user's division (for linking a PR to a PPMP)
        $availablePpmps = Ppmp::whereIn('status', [Ppmp::STATUS_APPROVED, Ppmp::STATUS_CONSOLIDATED])
            ->when($user->division_id && ! $canViewAll, fn ($q) => $q->where('division_id', $user->division_id))
            ->orderByDesc('fiscal_year')
            ->get(['id', 'ppmp_number', 'title', 'fiscal_year', 'division_id'])
            ->map(fn ($p) => ['id' => $p->id, 'label' => "{$p->ppmp_number} — {$p->title} (FY {$p->fiscal_year})"])
            ->values();

        return Inertia::render('Procurements/Index', [
            'procurements'        => $query->get()->map(fn ($p) => $this->formatPR($p)),
            'pendingAction'       => $pendingQuery->get()->map(fn ($p) => $this->formatPR($p)),
            'units'               => Unit::orderBy('name')->get(['id', 'name']),
            'divisions'           => Division::where('status', '<>', 'inactive')->orderBy('division_name')->get(['id', 'division_name']),
            'availablePpmps'      => $availablePpmps,
            'canViewAll'          => $canViewAll,
            'currentUser'         => [
                'id'          => $user->id,
                'name'        => $user->name,
                'division_id' => $user->division_id,
                'permissions' => [
                    'canCreate'   => $user->hasPermission('procurement.pr.create'),
                    'canDcSign'   => $user->hasPermission('procurement.pr.dc_sign'),
                    'canNumber'   => $user->hasPermission('procurement.pr.number'),
                    'canOcdSign'  => $user->hasPermission('procurement.pr.ocd_sign'),
                    'canBoInitial' => $user->hasPermission('procurement.pr.bo_initial'),
                ],
            ],
        ]);
    }

    public function show(Procurement $procurement, Request $request)
    {
        $user = $request->user();

        // Access: owner, admins, or users with action permissions
        $canView = $procurement->requested_by == $user->id
            || $user->isSuperAdmin()
            || $user->hasAnyPermission(['procurement.pr.dc_sign', 'procurement.pr.number', 'procurement.pr.ocd_sign', 'procurement.pr.bo_initial']);

        abort_unless($canView, 403);

        $procurement->load(['items', 'requester:id,name', 'divisionChief:id,name', 'procurementOfficer:id,name', 'ocd:id,name', 'rejectedByUser:id,name', 'division:id,division_name', 'rfqs']);

        return Inertia::render('Procurements/Show', [
            'procurement' => $this->formatPR($procurement, detailed: true),
            'currentUser' => [
                'id'          => $user->id,
                'permissions' => [
                    'canDcSign'    => $user->hasPermission('procurement.pr.dc_sign'),
                    'canNumber'    => $user->hasPermission('procurement.pr.number'),
                    'canOcdSign'   => $user->hasPermission('procurement.pr.ocd_sign'),
                    'canBoInitial' => $user->hasPermission('procurement.pr.bo_initial'),
                    'canCreateRfq' => $user->hasAnyPermission(['procurement.rfq.create']) || $user->isSuperAdmin(),
                ],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $this->checkPerm('procurement.pr.create');

        $data = $request->validate([
            'pr_date'             => 'nullable|date',
            'purpose'             => 'required|string|max:500',
            'division_id'         => 'nullable|exists:divisions,id',
            'is_supplemental'     => 'boolean',
            'ppmp_checked'        => 'boolean',
            'ppmp_id'             => 'nullable|exists:ppmp,id',
            'market_study_base64' => 'nullable|string',
            'market_study_name'   => 'nullable|string|max:255',
        ]);

        $procurement = Procurement::create([
            'pr_no'          => '',
            'pr_date'        => $data['pr_date'] ?? today(),
            'purpose'        => $data['purpose'],
            'requested_by'   => $user->id,
            'division_id'    => $data['division_id'] ?? $user->division_id,
            'status'         => 'draft',
            'is_supplemental'=> $data['is_supplemental'] ?? false,
            'ppmp_checked'   => $data['ppmp_checked'] ?? true,
            'ppmp_id'        => $data['ppmp_id'] ?? null,
        ]);

        $year  = now()->format('Y');
        $month = now()->format('m');
        $seq   = str_pad($procurement->id, 4, '0', STR_PAD_LEFT);
        $procurement->update(['pr_no' => "PR-{$year}-{$month}-{$seq}"]);

        if (! empty($data['market_study_base64'])) {
            $this->saveMarketStudy($procurement, $data['market_study_base64'], $data['market_study_name'] ?? 'market-study.pdf');
        }

        return back()->with('success', 'Purchase request created.');
    }

    public function update(Request $request, Procurement $procurement)
    {
        $user = $request->user();
        abort_unless($procurement->requested_by == $user->id || $user->isSuperAdmin(), 403);
        abort_unless($procurement->status === 'draft', 422, 'Only draft PRs can be edited.');

        $data = $request->validate([
            'pr_date'             => 'nullable|date',
            'purpose'             => 'required|string|max:500',
            'is_supplemental'     => 'boolean',
            'ppmp_checked'        => 'boolean',
            'ppmp_id'             => 'nullable|exists:ppmp,id',
            'market_study_base64' => 'nullable|string',
            'market_study_name'   => 'nullable|string|max:255',
        ]);

        $procurement->update($data);

        if (! empty($data['market_study_base64'])) {
            $this->saveMarketStudy($procurement, $data['market_study_base64'], $data['market_study_name'] ?? 'market-study.pdf');
        }

        return back()->with('success', 'Purchase request updated.');
    }

    public function destroy(Procurement $procurement, Request $request)
    {
        abort_unless($procurement->requested_by == $request->user()->id || $request->user()->isSuperAdmin(), 403);
        abort_unless($procurement->status === 'draft', 422, 'Only draft PRs can be deleted.');

        $procurement->items()->delete();
        $procurement->delete();

        return redirect()->route('procurements.index')->with('success', 'Purchase request deleted.');
    }

    // ── Item endpoints (reuse existing logic) ─────────────────────────────────

    public function storeItem(Request $request, Procurement $procurement)
    {
        $user = $request->user();
        abort_unless($procurement->requested_by == $user->id || $user->isSuperAdmin(), 403);
        abort_unless(in_array($procurement->status, ['draft', 'pending_dc']), 422, 'Cannot add items at this stage.');

        $data = $request->validate([
            'ppmp_line_item_no' => 'nullable|string|max:50',
            'unit'              => 'required|string|max:50',
            'description'       => 'required|string|max:500',
            'quantity'          => 'required|integer|min:1',
            'unit_cost'         => 'required|numeric|min:0',
        ]);

        $item = ProcurementItem::create(array_merge($data, [
            'procurement_id' => $procurement->id,
            'pr_no'          => $procurement->pr_no,
        ]));

        return response()->json(['item' => $item], 201);
    }

    public function destroyItem(Procurement $procurement, ProcurementItem $item, Request $request)
    {
        $user = $request->user();
        abort_unless($procurement->requested_by == $user->id || $user->isSuperAdmin(), 403);
        abort_unless($item->procurement_id === $procurement->id, 422);

        $item->delete();

        return response()->json(['deleted' => true]);
    }

    // ── Workflow actions ──────────────────────────────────────────────────────

    public function submitForApproval(Request $request, Procurement $procurement)
    {
        $user = $request->user();
        abort_unless($procurement->requested_by == $user->id || $user->isSuperAdmin(), 403);
        abort_unless(in_array($procurement->status, ['draft', 'returned_bo']), 422, 'Cannot submit at this stage.');
        abort_unless($procurement->items()->count() > 0, 422, 'Add at least one item before submitting.');

        $this->service->submit($procurement, $user);

        return back()->with('success', 'Purchase request submitted for approval.');
    }

    public function dcSign(Request $request, Procurement $procurement)
    {
        $this->checkPerm('procurement.pr.dc_sign');
        abort_unless(in_array($procurement->status, ['pending_dc', 'supplemental_pending_dc']), 422);

        $data = $request->validate(['remarks' => 'nullable|string|max:500']);
        $this->service->dcSign($procurement, $request->user(), $data['remarks'] ?? '');

        return back()->with('success', 'Purchase request signed.');
    }

    public function boInitial(Request $request, Procurement $procurement)
    {
        $this->checkPerm('procurement.pr.bo_initial');
        abort_unless($procurement->status === 'supplemental_pending_bo', 422);

        $this->service->boInitial($procurement, $request->user());

        return back()->with('success', 'Supplemental document initialed.');
    }

    public function supplementalOcdSign(Request $request, Procurement $procurement)
    {
        $this->checkPerm('procurement.pr.ocd_sign');
        abort_unless($procurement->status === 'supplemental_pending_ocd', 422);

        $this->service->supplementalOcdSign($procurement, $request->user());

        return back()->with('success', 'Supplemental documents approved by Campus Director.');
    }

    public function assignNumber(Request $request, Procurement $procurement)
    {
        $this->checkPerm('procurement.pr.number');
        abort_unless($procurement->status === 'pending_procurement', 422);

        $data = $request->validate(['pr_number' => 'required|string|max:50']);
        $this->service->assignNumber($procurement, $request->user(), $data['pr_number']);

        return back()->with('success', 'PR number assigned.');
    }

    public function ocdSign(Request $request, Procurement $procurement)
    {
        $this->checkPerm('procurement.pr.ocd_sign');
        abort_unless($procurement->status === 'pending_ocd', 422);

        $data = $request->validate(['remarks' => 'nullable|string|max:500']);
        $this->service->ocdSign($procurement, $request->user(), $data['remarks'] ?? '');

        return back()->with('success', 'Purchase request approved by Campus Director.');
    }

    public function reject(Request $request, Procurement $procurement)
    {
        $user = $request->user();
        $canReject = $user->hasAnyPermission(['procurement.pr.dc_sign', 'procurement.pr.number', 'procurement.pr.ocd_sign', 'procurement.pr.bo_initial'])
            || $user->isSuperAdmin();
        abort_unless($canReject, 403);
        abort_unless(! in_array($procurement->status, ['approved', 'rejected']), 422);

        $data = $request->validate(['reason' => 'required|string|max:500']);
        $this->service->reject($procurement, $user, $data['reason']);

        return back()->with('success', 'Purchase request rejected.');
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function formatPR(Procurement $p, bool $detailed = false): array
    {
        $base = [
            'id'               => $p->id,
            'pr_no'            => $p->pr_no,
            'assigned_pr_number' => $p->assigned_pr_number,
            'pr_date'          => $p->pr_date?->toDateString(),
            'purpose'          => $p->purpose,
            'status'           => $p->status ?? 'approved',
            'status_label'     => $p->statusLabel(),
            'is_supplemental'  => (bool) $p->is_supplemental,
            'ppmp_checked'     => (bool) $p->ppmp_checked,
            'supplemental_status' => $p->supplemental_status,
            'requester'        => $p->requester?->only('id', 'name'),
            'requested_by'     => $p->requested_by,
            'items_count'      => $p->items->count(),
            'items'            => $detailed ? $p->items->toArray() : [],
            'created_at'       => $p->created_at?->toDateString(),
            'market_study_filename' => $p->market_study_filename,
            'has_market_study'      => (bool) $p->market_study_s3_key,
        ];

        if ($detailed) {
            $base = array_merge($base, [
                'division'            => $p->division?->only('id', 'division_name'),
                'division_chief'      => $p->divisionChief?->only('id', 'name'),
                'division_chief_at'   => $p->division_chief_at?->toISOString(),
                'division_chief_remarks' => $p->division_chief_remarks,
                'procurement_officer' => $p->procurementOfficer?->only('id', 'name'),
                'procurement_officer_at' => $p->procurement_officer_at?->toISOString(),
                'ocd'                 => $p->ocd?->only('id', 'name'),
                'ocd_at'              => $p->ocd_at?->toISOString(),
                'ocd_remarks'         => $p->ocd_remarks,
                'rejected_by_user'    => $p->rejectedByUser?->only('id', 'name'),
                'rejected_at'         => $p->rejected_at?->toISOString(),
                'rejection_reason'    => $p->rejection_reason,
                'supplemental_bo_at'  => $p->supplemental_budget_officer_at?->toISOString(),
                'rfqs'                => $p->rfqs?->map(fn ($r) => [
                    'id'          => $r->id,
                    'rfq_number'  => $r->rfq_number,
                    'status'      => $r->status,
                    'status_label'=> $r->statusLabel(),
                ])->values() ?? [],
            ]);
        }

        return $base;
    }

    public function print(Procurement $procurement, PRPdfService $pdf): StreamedResponse
    {
        $this->checkPerm('procurement.pr.view');
        return $pdf->stream($procurement);
    }

    public function downloadMarketStudy(Procurement $procurement, Request $request)
    {
        $canView = $procurement->requested_by == $request->user()->id
            || $request->user()->isSuperAdmin()
            || $request->user()->hasAnyPermission(['procurement.pr.dc_sign', 'procurement.pr.number', 'procurement.pr.ocd_sign']);

        abort_unless($canView, 403);
        abort_unless($procurement->market_study_s3_key, 404, 'No market study attached.');

        $bytes = Storage::disk('s3')->get($procurement->market_study_s3_key);
        $filename = $procurement->market_study_filename ?? 'market-study.pdf';

        return response($bytes, 200, [
            'Content-Type'        => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function saveMarketStudy(Procurement $procurement, string $base64Data, string $filename): void
    {
        if (preg_match('/^data:[^;]+;base64,/', $base64Data)) {
            $base64Data = preg_replace('/^data:[^;]+;base64,/', '', $base64Data);
        }
        $bytes = base64_decode($base64Data);
        abort_if($bytes === false || strlen($bytes) < 100, 422, 'Invalid file data.');
        abort_if(strlen($bytes) > 15 * 1024 * 1024, 422, 'File too large (max 15 MB).');

        if ($procurement->market_study_s3_key) {
            Storage::disk('s3')->delete($procurement->market_study_s3_key);
        }

        $ext    = strtolower(pathinfo($filename, PATHINFO_EXTENSION)) ?: 'pdf';
        $s3Key  = "procurement-market-study/{$procurement->id}.{$ext}";
        Storage::disk('s3')->put($s3Key, $bytes);

        $procurement->update([
            'market_study_s3_key'  => $s3Key,
            'market_study_filename'=> basename($filename),
        ]);
    }

    private function checkPerm(string $permission): void
    {
        abort_unless(request()->user()->hasPermission($permission) || request()->user()->isSuperAdmin(), 403);
    }
}
