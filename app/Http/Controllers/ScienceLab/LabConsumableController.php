<?php

namespace App\Http\Controllers\ScienceLab;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\ScienceLab\LabConsumable;
use App\Models\ScienceLab\LabConsumableLot;
use App\Models\ScienceLab\ScienceLabProfile;
use App\Models\User;
use App\Services\ScienceLab\LabInventoryService;
use App\Traits\HandlesLabUploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class LabConsumableController extends Controller
{
    use HandlesLabUploads;

    public const TYPES = ['consumable', 'reagent', 'chemical'];

    public function __construct(private LabInventoryService $inventory) {}

    public function index(Request $request)
    {
        $consumables = LabConsumable::with(['room:id,name', 'endUser:id,name'])
            ->when($request->string('type')->value(), fn ($q, $t) => $q->where('type', $t))
            ->when($request->string('search')->value(), fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('name')->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'description' => $c->description,
                'type' => $c->type,
                'unit_of_measure' => $c->unit_of_measure,
                'room' => $c->room?->name,
                'room_id' => $c->room_id,
                'storage' => $c->storage,
                'end_user_id' => $c->end_user_id,
                'end_user' => $c->endUser?->name,
                'unit' => $c->unit,
                'cabinet' => $c->cabinet,
                'storage_code' => $c->storage_code,
                'code_description' => $c->code_description,
                'brand' => $c->brand,
                'cas_number' => $c->cas_number,
                'container_size' => $c->container_size,
                'hazards' => $c->hazards,
                'is_chemical' => $c->is_chemical,
                'has_sds' => (bool) $c->sds_path,
                'sds_path' => $c->sds_path,
                'reorder_level' => (float) $c->reorder_level,
                'tracks_expiry' => $c->tracks_expiry,
                'status' => $c->status,
                'remarks' => $c->remarks,
                'balance' => $this->inventory->balance($c->id),
            ]);

        return Inertia::render('ScienceLab/Inventory', [
            'consumables' => $consumables,
            'labRooms'    => $this->labRooms(),
            'types'       => self::TYPES,
            'endUsers'    => User::where('status', '<>', 'inactive')->orderBy('name')->get(['id', 'name']),
            'filters'     => $request->only('type', 'search'),
        ]);
    }

    public function show(LabConsumable $consumable)
    {
        $lots = $consumable->lots()->orderByRaw('expiry_date IS NULL')->orderBy('expiry_date')->get();
        $movements = $consumable->movements()->with('creator:id,name')
            ->orderByDesc('transaction_date')->orderByDesc('id')->limit(200)->get();

        return Inertia::render('ScienceLab/InventoryShow', [
            'consumable' => array_merge($consumable->toArray(), [
                'balance' => $this->inventory->balance($consumable->id),
            ]),
            'lots'       => $lots,
            'movements'  => $movements,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = Auth::id();
        $data['is_chemical'] = ($data['type'] ?? 'consumable') === 'chemical' || ($request->boolean('is_chemical'));
        if ($sds = $this->storeLabUpload($request->input('sds_base64'), 'lab/sds')) {
            $data['sds_path'] = $sds;
        }
        unset($data['sds_base64']);
        LabConsumable::create($data);

        return back()->with('success', 'Item added to inventory.');
    }

    public function update(Request $request, LabConsumable $consumable)
    {
        $data = $this->validated($request);
        $data['is_chemical'] = ($data['type'] ?? $consumable->type) === 'chemical' || $request->boolean('is_chemical');
        if ($sds = $this->storeLabUpload($request->input('sds_base64'), 'lab/sds')) {
            $data['sds_path'] = $sds;
        }
        unset($data['sds_base64']);
        $consumable->update($data);

        return back()->with('success', 'Item updated.');
    }

    public function receive(Request $request, LabConsumable $consumable)
    {
        $data = $request->validate([
            'quantity'         => ['required', 'numeric', 'gt:0'],
            'lot_no'           => ['nullable', 'string', 'max:100'],
            'received_date'    => ['nullable', 'date'],
            'expiry_date'      => ['nullable', 'date'],
            'manufactured_date'=> ['nullable', 'date'],
            'unit_cost'        => ['nullable', 'numeric', 'min:0'],
            'reference_number' => ['nullable', 'string', 'max:60'],
            'remarks'          => ['nullable', 'string', 'max:500'],
        ]);

        $this->inventory->receive($consumable, $data, Auth::id());

        return back()->with('success', 'Stock received.');
    }

    public function adjust(Request $request, LabConsumable $consumable)
    {
        $data = $request->validate([
            'delta'  => ['required', 'numeric'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->inventory->adjust($consumable, (float) $data['delta'], $data['reason'], Auth::id());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['delta' => $e->getMessage()]);
        }

        return back()->with('success', 'Stock adjusted.');
    }

    public function disposeLot(Request $request, LabConsumableLot $lot)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->inventory->disposeLot($lot, $data['reason'], Auth::id());

        return back()->with('success', 'Lot disposed and segregated.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'description'     => ['nullable', 'string', 'max:2000'],
            'type'            => ['required', Rule::in(self::TYPES)],
            'unit_of_measure' => ['nullable', 'string', 'max:30'],
            'room_id'         => ['nullable', 'integer', 'exists:rooms,id'],
            'storage'         => ['nullable', 'string', 'max:150'],
            'end_user_id'     => ['nullable', 'integer', 'exists:users,id'],
            'unit'            => ['nullable', 'string', 'max:100'],
            'cabinet'         => ['nullable', 'string', 'max:100'],
            'storage_code'    => ['nullable', 'string', 'max:100'],
            'code_description'=> ['nullable', 'string', 'max:255'],
            'brand'           => ['nullable', 'string', 'max:150'],
            'cas_number'      => ['nullable', 'string', 'max:100'],
            'container_size'  => ['nullable', 'string', 'max:100'],
            'hazards'         => ['nullable', 'string', 'max:2000'],
            'is_chemical'     => ['boolean'],
            'reorder_level'   => ['nullable', 'numeric', 'min:0'],
            'tracks_expiry'   => ['boolean'],
            'status'          => ['nullable', Rule::in(['active', 'inactive'])],
            'remarks'         => ['nullable', 'string', 'max:1000'],
            'sds_base64'      => ['nullable', 'string'],
        ]);
    }

    private function labRooms()
    {
        $ids = ScienceLabProfile::pluck('room_id');

        return Room::whereIn('id', $ids)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name]);
    }
}
