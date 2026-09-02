<?php

namespace App\Http\Controllers\PM2;

use App\Http\Controllers\Controller;
use App\Models\PM2\IpcrRatingPeriodV2;
use App\Models\PM2\OpcrTemplate;
use App\Models\PM2\OpcrTemplateItem;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OpcrTemplateController extends Controller
{
    /**
     * Find-or-create the current period's OPCR template. Campus-wide, one
     * per period — every V2 IPCR in that period inherits it read-only.
     */
    private function currentTemplate(): OpcrTemplate
    {
        $period = IpcrRatingPeriodV2::current()->first() ?? IpcrRatingPeriodV2::create([
            'label' => 'Current Period', 'year' => (int) now()->format('Y'), 'is_current' => true,
        ]);

        return OpcrTemplate::firstOrCreate(
            ['ipcr_rating_period_v2_id' => $period->id],
            ['is_current' => true]
        );
    }

    public function index()
    {
        $template = $this->currentTemplate()->load('items', 'period');

        return Inertia::render('PerformanceManagementV2/OpcrTemplates', [
            'template' => $template,
        ]);
    }

    public function storeItem(Request $request)
    {
        $data = $request->validate([
            'strategy_label'          => 'required|string|max:255',
            'output_outcome'          => 'required|string',
            'success_indicator'       => 'nullable|string',
            'target'                  => 'nullable|string',
            'rating_scale_quality'    => 'nullable|string',
            'rating_scale_efficiency' => 'nullable|string',
            'rating_scale_timeliness' => 'nullable|string',
            'weight_percent'          => 'nullable|numeric|min:0|max:100',
        ]);

        $template = $this->currentTemplate();
        $data['opcr_template_id'] = $template->id;
        $data['sort_order'] = $template->items()->max('sort_order') + 1;

        OpcrTemplateItem::create($data);

        return back()->with('success', 'Strategic Function item added.');
    }

    public function updateItem(Request $request, OpcrTemplateItem $item)
    {
        $data = $request->validate([
            'strategy_label'          => 'required|string|max:255',
            'output_outcome'          => 'required|string',
            'success_indicator'       => 'nullable|string',
            'target'                  => 'nullable|string',
            'rating_scale_quality'    => 'nullable|string',
            'rating_scale_efficiency' => 'nullable|string',
            'rating_scale_timeliness' => 'nullable|string',
            'weight_percent'          => 'nullable|numeric|min:0|max:100',
        ]);

        $item->update($data);

        return back()->with('success', 'Strategic Function item updated.');
    }

    public function destroyItem(OpcrTemplateItem $item)
    {
        $item->delete();

        return back()->with('success', 'Strategic Function item removed.');
    }
}
