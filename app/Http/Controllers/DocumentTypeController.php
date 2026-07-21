<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use App\Models\DocumentTypeRoutingStep;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DocumentTypeController extends Controller
{
    public function index()
    {
        $types = DocumentType::with(['routingSteps.office', 'routingSteps.assignedUser'])
            ->orderBy('name')
            ->get()
            ->map(fn($t) => [
                'id'              => $t->id,
                'name'            => $t->name,
                'code'            => $t->code,
                'description'     => $t->description,
                'lead_time_hours' => $t->lead_time_hours,
                'routing_type'    => $t->routing_type,
                'applicable_to'   => $t->applicable_to,
                'is_active'       => $t->is_active,
                'routing_steps'   => $t->routingSteps->map(fn($s) => [
                    'id'               => $s->id,
                    'step_order'       => $s->step_order,
                    'office_id'        => $s->office_id,
                    'office_name'      => $s->office?->name,
                    'assigned_user_id' => $s->assigned_user_id,
                    'assigned_user'    => $s->assignedUser?->only('id', 'name', 'email'),
                    'action_required'  => $s->action_required,
                    'lead_time_hours'  => $s->lead_time_hours,
                    'is_required'      => $s->is_required,
                ]),
            ]);

        return Inertia::render('DocumentTracking/Types/Index', [
            'documentTypes' => $types,
            'offices'       => \App\Models\Office::orderBy('name')->get(['id', 'name']),
            'users'         => \App\Models\User::employees()->where('status', '<>', 'inactive')
                                ->orderBy('name')
                                ->get(['id', 'name', 'email', 'office_id']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'code'           => 'required|string|max:50|unique:document_types,code',
            'description'    => 'nullable|string|max:1000',
            'lead_time_hours'=> 'required|integer|min:1|max:720',
            'routing_type'   => 'required|in:sequential,parallel,manual',
            'applicable_to'  => 'required|in:internal,external,both',
            'is_active'      => 'boolean',
        ]);

        $type = DocumentType::create($data);

        return back()->with('success', "Document type \"{$type->name}\" created.");
    }

    public function update(Request $request, DocumentType $documentType)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'code'           => "required|string|max:50|unique:document_types,code,{$documentType->id}",
            'description'    => 'nullable|string|max:1000',
            'lead_time_hours'=> 'required|integer|min:1|max:720',
            'routing_type'   => 'required|in:sequential,parallel,manual',
            'applicable_to'  => 'required|in:internal,external,both',
            'is_active'      => 'boolean',
        ]);

        $documentType->update($data);

        return back()->with('success', 'Document type updated.');
    }

    public function destroy(DocumentType $documentType)
    {
        if ($documentType->documents()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete a type that has existing documents.']);
        }

        $documentType->delete();

        return back()->with('success', 'Document type deleted.');
    }

    // ── Routing Steps ─────────────────────────────────────────────────────────

    public function storeStep(Request $request, DocumentType $documentType)
    {
        $data = $request->validate([
            'office_id'        => 'nullable|exists:offices,id',
            'assigned_user_id' => 'nullable|exists:users,id',
            'action_required'  => 'required|string|max:500',
            'lead_time_hours'  => 'required|integer|min:1|max:720',
            'is_required'      => 'boolean',
        ]);

        $maxOrder = $documentType->routingSteps()->max('step_order') ?? 0;

        $documentType->routingSteps()->create(array_merge($data, [
            'step_order' => $maxOrder + 1,
        ]));

        return back()->with('success', 'Routing step added.');
    }

    public function updateStep(Request $request, DocumentTypeRoutingStep $step)
    {
        $data = $request->validate([
            'office_id'        => 'nullable|exists:offices,id',
            'assigned_user_id' => 'nullable|exists:users,id',
            'action_required'  => 'required|string|max:500',
            'lead_time_hours'  => 'required|integer|min:1|max:720',
            'is_required'      => 'boolean',
            'step_order'       => 'required|integer|min:1',
        ]);

        $step->update($data);

        return back()->with('success', 'Routing step updated.');
    }

    public function destroyStep(DocumentTypeRoutingStep $step)
    {
        $typeId = $step->document_type_id;
        $order  = $step->step_order;
        $step->delete();

        // Re-sequence remaining steps
        DocumentTypeRoutingStep::where('document_type_id', $typeId)
            ->where('step_order', '>', $order)
            ->decrement('step_order');

        return back()->with('success', 'Routing step removed.');
    }

    public function reorderSteps(Request $request, DocumentType $documentType)
    {
        $request->validate([
            'steps'          => 'required|array',
            'steps.*.id'     => 'required|exists:document_type_routing_steps,id',
            'steps.*.step_order' => 'required|integer|min:1',
        ]);

        foreach ($request->steps as $item) {
            DocumentTypeRoutingStep::where('id', $item['id'])
                ->where('document_type_id', $documentType->id)
                ->update(['step_order' => $item['step_order']]);
        }

        return back()->with('success', 'Steps reordered.');
    }
}
