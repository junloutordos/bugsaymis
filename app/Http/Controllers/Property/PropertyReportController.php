<?php

namespace App\Http\Controllers\Property;

use App\Http\Controllers\Controller;
use App\Models\Property\PropertyItem;
use App\Services\Property\DepreciationService;
use App\Services\Property\DepreciationSchedulePdfService;
use App\Services\Property\RpciPdfService;
use App\Services\Property\RpcppePdfService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PropertyReportController extends Controller
{
    public function __construct(
        private DepreciationService $depreciation,
        private RpciPdfService $rpciPdf,
        private RpcppePdfService $rpcppePdf,
        private DepreciationSchedulePdfService $depSchedulePdf,
    ) {}

    public function index()
    {
        $this->authorize('property.reports');

        return Inertia::render('Property/Reports/Index', [
            'rpciSummary'  => $this->depreciation->rpciData(),
            'rpcppeSummary' => $this->depreciation->rpcppeData(),
        ]);
    }

    public function rpciPdf(Request $request)
    {
        $this->authorize('property.reports');
        $asOf = $request->get('as_of', today()->toDateString());
        return $this->rpciPdf->generate($this->depreciation->rpciData(), $asOf);
    }

    public function rpcppePdf(Request $request)
    {
        $this->authorize('property.reports');
        $asOf = $request->get('as_of', today()->toDateString());
        return $this->rpcppePdf->generate($this->depreciation->rpcppeData(), $asOf);
    }

    public function depreciationSchedule(Request $request)
    {
        $this->authorize('property.reports');

        $item = PropertyItem::with('category')->findOrFail($request->get('item_id'));
        $schedule = $this->depreciation->lapsingSchedule($item);

        if ($request->get('format') === 'pdf') {
            return $this->depSchedulePdf->generate($item, $schedule);
        }

        return response()->json(['item' => $item, 'schedule' => $schedule]);
    }
}
