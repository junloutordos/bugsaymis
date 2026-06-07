<?php

namespace App\Http\Controllers\Property;

use App\Http\Controllers\Controller;
use App\Models\Property\BoardOfSurveyReport;
use App\Models\Property\BsrItem;
use App\Models\Property\DisposalItem;
use App\Models\Property\DisposalRecord;
use App\Models\Property\PropertyItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DisposalController extends Controller
{
    public function index()
    {
        $this->authorize('property.dispose');

        $bsrs = BoardOfSurveyReport::with(['creator:id,name'])->latest()
            ->get()->map(fn ($b) => [
                'id'              => $b->id,
                'bsr_number'      => $b->bsr_number,
                'inspection_date' => $b->inspection_date?->format('Y-m-d'),
                'status'          => $b->status,
                'status_label'    => $b->statusLabel(),
                'item_count'      => $b->items()->count(),
                'created_by'      => $b->creator?->name,
            ]);

        $disposals = DisposalRecord::with(['bsr:id,bsr_number', 'approver:id,name'])->latest()
            ->get()->map(fn ($d) => [
                'id'              => $d->id,
                'disposal_number' => $d->disposal_number,
                'bsr_number'      => $d->bsr?->bsr_number,
                'disposal_date'   => $d->disposal_date?->format('Y-m-d'),
                'disposal_method' => $d->disposal_method,
                'method_label'    => $d->methodLabel(),
                'proceeds_amount' => (float) $d->proceeds_amount,
                'status'          => $d->status,
                'status_label'    => $d->statusLabel(),
            ]);

        $unserviceable = PropertyItem::where('status', 'unserviceable')
            ->with('category:id,name')
            ->orderBy('property_number')
            ->get(['id', 'property_number', 'description', 'unit_cost', 'category_id', 'acquisition_date']);

        return Inertia::render('Property/Disposal/Index', [
            'bsrs'          => $bsrs,
            'disposals'     => $disposals,
            'unserviceable' => $unserviceable,
            'officers'      => User::where('status', '<>', 'inactive')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeBsr(Request $request)
    {
        $this->authorize('property.dispose');

        $data = $request->validate([
            'inspection_date' => 'required|date',
            'board_members'   => 'nullable|array',
            'findings'        => 'nullable|string',
            'recommendation'  => 'nullable|string',
            'remarks'         => 'nullable|string',
            'items'           => 'required|array|min:1',
            'items.*.property_item_id' => 'required|exists:property_items,id',
            'items.*.condition'        => 'required|in:poor,damaged,obsolete,beyond_repair,lost',
            'items.*.recommendation'   => 'required|in:repair,dispose,write_off',
            'items.*.appraised_value'  => 'nullable|numeric|min:0',
            'items.*.remarks'          => 'nullable|string',
        ]);

        DB::transaction(function () use ($data, $request) {
            $bsr = BoardOfSurveyReport::create([
                'bsr_number'      => BoardOfSurveyReport::generateNumber(),
                'inspection_date' => $data['inspection_date'],
                'board_members'   => $data['board_members'] ?? null,
                'findings'        => $data['findings'] ?? null,
                'recommendation'  => $data['recommendation'] ?? null,
                'status'          => 'draft',
                'remarks'         => $data['remarks'] ?? null,
                'created_by'      => $request->user()->id,
            ]);

            foreach ($data['items'] as $item) {
                BsrItem::create([
                    'bsr_id'           => $bsr->id,
                    'property_item_id' => $item['property_item_id'],
                    'condition'        => $item['condition'],
                    'recommendation'   => $item['recommendation'],
                    'appraised_value'  => $item['appraised_value'] ?? 0,
                    'remarks'          => $item['remarks'] ?? null,
                ]);

                // Mark property as unserviceable when surveyed
                PropertyItem::where('id', $item['property_item_id'])
                    ->update(['status' => 'unserviceable']);
            }
        });

        return back()->with('success', 'Board of Survey Report created.');
    }

    public function approveBsr(Request $request, BoardOfSurveyReport $bsr)
    {
        $this->authorize('property.manage');
        abort_unless($bsr->status === 'draft', 422, 'BSR must be in draft status to approve.');

        $bsr->update([
            'status'      => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('success', 'BSR approved.');
    }

    public function storeDisposal(Request $request)
    {
        $this->authorize('property.dispose');

        $data = $request->validate([
            'bsr_id'          => 'nullable|exists:board_of_survey_reports,id',
            'disposal_date'   => 'required|date',
            'disposal_method' => 'required|in:auction,donation,destruction,condemnation,trade_in',
            'proceeds_amount' => 'nullable|numeric|min:0',
            'recipient'       => 'nullable|string|max:255',
            'remarks'         => 'nullable|string',
            'items'           => 'required|array|min:1',
            'items.*.property_item_id' => 'required|exists:property_items,id',
            'items.*.quantity'         => 'required|numeric|min:0.001',
            'items.*.appraised_value'  => 'nullable|numeric|min:0',
            'items.*.remarks'          => 'nullable|string',
        ]);

        DB::transaction(function () use ($data, $request) {
            $disposal = DisposalRecord::create([
                'disposal_number' => DisposalRecord::generateNumber(),
                'bsr_id'          => $data['bsr_id'] ?? null,
                'disposal_date'   => $data['disposal_date'],
                'disposal_method' => $data['disposal_method'],
                'proceeds_amount' => $data['proceeds_amount'] ?? 0,
                'recipient'       => $data['recipient'] ?? null,
                'status'          => 'draft',
                'remarks'         => $data['remarks'] ?? null,
                'created_by'      => $request->user()->id,
            ]);

            foreach ($data['items'] as $item) {
                DisposalItem::create([
                    'disposal_id'      => $disposal->id,
                    'property_item_id' => $item['property_item_id'],
                    'quantity'         => $item['quantity'],
                    'appraised_value'  => $item['appraised_value'] ?? 0,
                    'remarks'          => $item['remarks'] ?? null,
                ]);
            }
        });

        return back()->with('success', 'Disposal record created.');
    }

    public function approveDisposal(Request $request, DisposalRecord $disposal)
    {
        $this->authorize('property.manage');
        abort_unless($disposal->status === 'draft', 422, 'Disposal must be in draft status.');

        $disposal->update([
            'status'      => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Disposal approved.');
    }

    public function completeDisposal(Request $request, DisposalRecord $disposal)
    {
        $this->authorize('property.dispose');
        abort_unless($disposal->status === 'approved', 422, 'Disposal must be approved before completion.');

        DB::transaction(function () use ($disposal) {
            $disposal->load('items');
            foreach ($disposal->items as $item) {
                PropertyItem::where('id', $item->property_item_id)
                    ->update(['status' => 'disposed']);
            }
            $disposal->update(['status' => 'completed']);
        });

        return back()->with('success', 'Disposal completed. Property items marked as disposed.');
    }
}
