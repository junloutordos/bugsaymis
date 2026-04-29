<?php

namespace App\Http\Controllers\PPMP;

use App\Http\Controllers\Controller;
use App\Models\PPMP\Ppmp;
use App\Models\PPMP\PpmpItem;
use App\Services\PPMP\CostComputationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PPMPAppController extends Controller
{
    public function __construct(private CostComputationService $costService)
    {
    }

    /**
     * View consolidated APP for a fiscal year.
     */
    public function index(Request $request)
    {
        $this->authorize('consolidate', Ppmp::class);

        $fiscalYear = $request->input('fiscal_year', (int) date('Y'));

        // Get all items from approved/consolidated PPMPs
        $items = PpmpItem::query()
            ->join('ppmp', 'ppmp_items.ppmp_id', '=', 'ppmp.id')
            ->join('divisions', 'ppmp.division_id', '=', 'divisions.id')
            ->where('ppmp.fiscal_year', $fiscalYear)
            ->whereIn('ppmp.status', [Ppmp::STATUS_APPROVED, Ppmp::STATUS_CONSOLIDATED])
            ->select(
                'ppmp_items.*',
                'divisions.division_name',
                'divisions.acronym as division_acronym',
                'ppmp.ppmp_number'
            )
            ->orderByRaw("FIELD(ppmp_items.category, 'goods', 'infrastructure', 'consulting_services')")
            ->orderBy('ppmp_items.description')
            ->get();

        $totals = $this->costService->computeAPPTotals($fiscalYear);

        $approvedCount = Ppmp::forYear($fiscalYear)
            ->whereIn('status', [Ppmp::STATUS_APPROVED, Ppmp::STATUS_CONSOLIDATED])
            ->count();

        return Inertia::render('PPMP/App/Index', [
            'items'         => $items,
            'totals'        => $totals,
            'fiscalYear'    => $fiscalYear,
            'approvedCount' => $approvedCount,
            'categories'    => PpmpItem::CATEGORY_LABELS,
            'methods'       => PpmpItem::PROCUREMENT_METHODS,
        ]);
    }

    /**
     * Consolidate all approved PPMPs into APP.
     */
    public function consolidate(Request $request)
    {
        $this->authorize('consolidate', Ppmp::class);

        $data = $request->validate([
            'fiscal_year' => 'required|integer',
        ]);

        $ppmps = Ppmp::forYear($data['fiscal_year'])
            ->byStatus(Ppmp::STATUS_APPROVED)
            ->get();

        if ($ppmps->isEmpty()) {
            return back()->with('error', 'No approved PPMPs to consolidate.');
        }

        DB::transaction(function () use ($ppmps) {
            $user = auth()->user();
            foreach ($ppmps as $ppmp) {
                $ppmp->consolidate($user);
            }
        });

        return back()->with('success', $ppmps->count() . ' PPMP(s) consolidated into APP.');
    }
}
