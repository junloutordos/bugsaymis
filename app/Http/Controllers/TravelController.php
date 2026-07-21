<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\Issuance;
use App\Models\Procurement\DisbursementVoucher;
use App\Models\Procurement\OblRequest;
use App\Models\TravelAttachment;
use App\Models\TravelFlightAuthority;
use App\Models\TravelItineraryItem;
use App\Models\TravelPolicyRule;
use App\Models\TravelRequest;
use App\Models\User;
use App\Models\VehicleRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TravelController extends Controller
{
    public function dashboard(Request $request)
    {
        $this->authorizeView($request);

        $base = $this->scopedQuery($request)->with(['traveler:id,name,position', 'division:id,division_name']);
        $records = (clone $base)->latest()->limit(12)->get();
        $pending = (clone $base)->whereIn('status', $this->pendingStatusesFor($request->user()))->latest()->get();

        return Inertia::render('Travel/Dashboard', [
            'stats' => [
                'total' => (clone $base)->count(),
                'upcoming' => (clone $base)->whereDate('start_date', '>=', now()->toDateString())->count(),
                'pending_action' => $pending->count(),
                'unliquidated' => (clone $base)->where('requires_cash_advance', true)
                    ->whereNotIn('liquidation_status', ['liquidated'])
                    ->whereIn('status', ['released', 'completed'])
                    ->count(),
            ],
            'recentTravels' => $records->map(fn (TravelRequest $travel) => $this->formatTravel($travel)),
            'pendingAction' => $pending->map(fn (TravelRequest $travel) => $this->formatTravel($travel)),
            'policyRules' => TravelPolicyRule::where('is_active', true)->orderBy('source')->get(),
        ]);
    }

    public function index(Request $request)
    {
        $this->authorizeView($request);

        $travels = $this->scopedQuery($request)
            ->with(['traveler:id,name,position', 'division:id,division_name', 'vehicleRequest:id,status', 'ors:id,ors_number,status', 'dv:id,dv_number,status'])
            ->latest()
            ->paginate(min(max((int) $request->input('per_page', 15), 10), 100))
            ->withQueryString();

        $travels->getCollection()->transform(fn (TravelRequest $travel) => $this->formatTravel($travel));

        return Inertia::render('Travel/Index', [
            'travels' => $travels,
            'filters' => $request->only(['search', 'per_page']),
            'travelers' => User::employees()->where('status', '<>', 'inactive')->orderBy('name')->get(['id', 'name', 'position', 'division_id']),
            'divisionChiefs' => User::havingRole('DivisionChief')->where('status', '<>', 'inactive')->orderBy('name')->get(['id', 'name']),
            'currentUser' => $this->formatUser($request->user()),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeCreate($request);

        $data = $this->validatedTravel($request);
        $user = $request->user();
        $traveler = User::findOrFail($data['traveler_id']);

        $travel = DB::transaction(function () use ($data, $user, $traveler) {
            $travel = TravelRequest::create(array_merge($data, [
                'created_by' => $user->id,
                'division_id' => $traveler->division_id,
                'status' => 'draft',
                'liquidation_status' => $data['requires_cash_advance'] ? 'pending' : null,
                'cash_advance_status' => $data['requires_cash_advance'] ? 'pending' : null,
            ]));

            $travel->update(['control_no' => 'TRV-' . now()->format('Y') . '-' . str_pad((string) $travel->id, 5, '0', STR_PAD_LEFT)]);

            return $travel;
        });

        return redirect()->route('travel.show', $travel)->with('success', 'Travel request created.');
    }

    public function show(Request $request, TravelRequest $travel)
    {
        $this->authorizeViewTravel($request, $travel);

        $travel->load([
            'traveler:id,name,position,division_id',
            'creator:id,name',
            'division:id,division_name',
            'divisionChief:id,name',
            'travelOrderIssuance:id,control_number,title,type,status',
            'specialOrderIssuance:id,control_number,title,type,status',
            'vehicleRequest:id,purpose,destination,status,date_needed',
            'ors:id,ors_number,status,activity_title,amount',
            'dv:id,dv_number,status,activity_title,net_amount',
            'itineraryItems',
            'attachments.uploader:id,name',
            'flightAuthority',
        ]);

        return Inertia::render('Travel/Show', [
            'travel' => $this->formatTravel($travel, true),
            'lookups' => [
                'travelOrders' => Issuance::where('type', 'TO')->latest()->limit(100)->get(['id', 'control_number', 'title', 'status']),
                'specialOrders' => Issuance::where('type', 'SO')->latest()->limit(100)->get(['id', 'control_number', 'title', 'status']),
                'vehicleRequests' => VehicleRequest::latest()->limit(100)->get(['id', 'purpose', 'destination', 'status', 'date_needed']),
                'orsRecords' => OblRequest::latest()->limit(100)->get(['id', 'ors_number', 'activity_title', 'status', 'amount']),
                'dvRecords' => DisbursementVoucher::latest()->limit(100)->get(['id', 'dv_number', 'activity_title', 'status', 'net_amount']),
                'divisionChiefs' => User::havingRole('DivisionChief')->where('status', '<>', 'inactive')->orderBy('name')->get(['id', 'name']),
            ],
            'currentUser' => $this->formatUser($request->user()),
        ]);
    }

    public function update(Request $request, TravelRequest $travel)
    {
        $this->authorizeEdit($request, $travel);

        $travel->update($this->validatedTravel($request, $travel));

        return back()->with('success', 'Travel request updated.');
    }

    public function submit(Request $request, TravelRequest $travel)
    {
        $this->authorizeEdit($request, $travel);

        if ($travel->itineraryItems()->count() === 0) {
            return back()->withErrors(['itinerary' => 'Add at least one proposed itinerary row before submission.']);
        }

        $travel->update([
            'status' => 'submitted',
            'submitted_by' => $request->user()->id,
            'submitted_at' => now(),
            'return_reason' => null,
        ]);

        return back()->with('success', 'Travel request submitted.');
    }

    public function divisionApprove(Request $request, TravelRequest $travel)
    {
        $this->authorizeDivisionApproval($request, $travel);

        $travel->update([
            'status' => 'division_approved',
            'division_approved_by' => $request->user()->id,
            'division_approved_at' => now(),
            'return_reason' => null,
        ]);

        return back()->with('success', 'Travel request approved by Division Chief.');
    }

    public function fadReview(Request $request, TravelRequest $travel)
    {
        $this->authorizeFad($request);

        $travel->update([
            'status' => 'fad_reviewed',
            'fad_reviewed_by' => $request->user()->id,
            'fad_reviewed_at' => now(),
            'return_reason' => null,
        ]);

        return back()->with('success', 'FAD review recorded.');
    }

    public function ocdApprove(Request $request, TravelRequest $travel)
    {
        $this->authorizeOcd($request);

        $travel->update([
            'status' => 'ocd_approved',
            'ocd_approved_by' => $request->user()->id,
            'ocd_approved_at' => now(),
            'return_reason' => null,
        ]);

        return back()->with('success', 'Travel request approved by OCD.');
    }

    public function returnForRevision(Request $request, TravelRequest $travel)
    {
        $this->authorizeApprover($request, $travel);

        $data = $request->validate(['return_reason' => 'required|string|max:1000']);
        $travel->update([
            'status' => 'returned',
            'return_reason' => $data['return_reason'],
        ]);

        return back()->with('success', 'Travel request returned for revision.');
    }

    public function complete(Request $request, TravelRequest $travel)
    {
        $this->authorizeFad($request);

        $travel->update(['status' => 'completed', 'completed_at' => now()]);

        return back()->with('success', 'Travel marked completed.');
    }

    public function liquidate(Request $request, TravelRequest $travel)
    {
        $this->authorizeFad($request);

        $data = $request->validate([
            'liquidation_remarks' => 'nullable|string|max:2000',
        ]);

        $travel->update([
            'status' => 'liquidated',
            'liquidation_status' => 'liquidated',
            'liquidation_remarks' => $data['liquidation_remarks'] ?? $travel->liquidation_remarks,
            'liquidated_at' => now(),
        ]);

        return back()->with('success', 'Travel liquidation recorded.');
    }

    public function saveItinerary(Request $request, TravelRequest $travel)
    {
        $this->authorizeEdit($request, $travel);

        $data = $request->validate([
            'items' => 'array',
            'items.*.travel_date' => 'nullable|date',
            'items.*.places_to_visit' => 'nullable|string|max:255',
            'items.*.departure_time' => 'nullable|date_format:H:i',
            'items.*.arrival_time' => 'nullable|date_format:H:i',
            'items.*.means_of_transportation' => 'nullable|string|max:255',
            'items.*.transportation_cost' => 'nullable|numeric|min:0',
            'items.*.per_diem' => 'nullable|numeric|min:0',
            'items.*.other_cost' => 'nullable|numeric|min:0',
            'items.*.remarks' => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($travel, $data) {
            $travel->itineraryItems()->delete();
            foreach (array_values($data['items'] ?? []) as $index => $item) {
                if (! filled($item['places_to_visit'] ?? null) && ! filled($item['travel_date'] ?? null)) {
                    continue;
                }

                TravelItineraryItem::create([
                    'travel_request_id' => $travel->id,
                    'sort_order' => $index + 1,
                    'travel_date' => $item['travel_date'] ?? null,
                    'places_to_visit' => $item['places_to_visit'] ?? null,
                    'departure_time' => $item['departure_time'] ?? null,
                    'arrival_time' => $item['arrival_time'] ?? null,
                    'means_of_transportation' => $item['means_of_transportation'] ?? null,
                    'transportation_cost' => $item['transportation_cost'] ?? 0,
                    'per_diem' => $item['per_diem'] ?? 0,
                    'other_cost' => $item['other_cost'] ?? 0,
                    'remarks' => $item['remarks'] ?? null,
                ]);
            }
        });

        return back()->with('success', 'Proposed itinerary saved.');
    }

    public function saveLinks(Request $request, TravelRequest $travel)
    {
        $this->authorizeEdit($request, $travel);

        $data = $request->validate([
            'travel_order_issuance_id' => 'nullable|exists:issuances,id',
            'special_order_issuance_id' => 'nullable|exists:issuances,id',
            'vehicle_request_id' => 'nullable|exists:vehicle_requests,id',
            'ors_id' => 'nullable|exists:obligation_requests,id',
            'dv_id' => 'nullable|exists:disbursement_vouchers,id',
        ]);

        $travel->update($data);

        $status = $travel->status;
        if ($travel->vehicle_request_id || $travel->travel_mode === 'air') {
            $status = in_array($status, ['ocd_approved', 'transport_arranged'], true) ? 'transport_arranged' : $status;
        }
        if ($travel->dv_id) {
            $status = 'dv_created';
        } elseif ($travel->ors_id && $travel->requires_cash_advance) {
            $status = 'cash_advance_processing';
        }
        $travel->update(['status' => $status]);

        return back()->with('success', 'Travel document and finance links saved.');
    }

    public function saveFlightAuthority(Request $request, TravelRequest $travel)
    {
        $this->authorizeEdit($request, $travel);

        $data = $request->validate([
            'passenger_name' => 'nullable|string|max:255',
            'route' => 'nullable|string|max:255',
            'preferred_departure_date' => 'nullable|date',
            'preferred_return_date' => 'nullable|date',
            'estimated_airfare' => 'nullable|numeric|min:0',
            'booking_notes' => 'nullable|string|max:2000',
            'submit' => 'boolean',
        ]);

        $status = ($data['submit'] ?? false) ? 'requested' : ($travel->flightAuthority?->status ?? 'draft');
        $payload = collect($data)->except('submit')->all();
        $payload['estimated_airfare'] = $payload['estimated_airfare'] ?? 0;
        $payload['status'] = $status;
        if ($status === 'requested') {
            $payload['requested_by'] = $request->user()->id;
            $payload['requested_at'] = now();
        }

        TravelFlightAuthority::updateOrCreate(['travel_request_id' => $travel->id], $payload);

        return back()->with('success', 'Authority to book flights saved.');
    }

    public function approveFlightAuthority(Request $request, TravelRequest $travel)
    {
        $this->authorizeFad($request);

        $authority = $travel->flightAuthority;
        if (! $authority) {
            return back()->withErrors(['flight' => 'Create the authority to book flights first.']);
        }

        $authority->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        $travel->update(['status' => 'transport_arranged']);

        return back()->with('success', 'Authority to book flights approved.');
    }

    public function uploadAttachment(Request $request, TravelRequest $travel)
    {
        $this->authorizeEdit($request, $travel);

        $data = $request->validate([
            'type' => 'required|string|in:oed_travel_order,special_order,ticket,boarding_pass,certificate_of_appearance,receipt,liquidation,other',
            'file_base64' => 'required|string',
            'file_name' => 'required|string|max:255',
            'mime_type' => 'nullable|string|max:120',
        ]);

        if (! preg_match('/^data:([^;]+);base64,(.+)$/s', $data['file_base64'], $matches)) {
            return back()->withErrors(['file_base64' => 'Invalid file data.']);
        }

        $bytes = base64_decode($matches[2], true);
        if ($bytes === false) {
            return back()->withErrors(['file_base64' => 'Invalid file data.']);
        }

        $mime = $data['mime_type'] ?: $matches[1];
        $ext = pathinfo($data['file_name'], PATHINFO_EXTENSION) ?: 'bin';
        $key = 'travel/' . $travel->id . '/' . $data['type'] . '-' . uniqid() . '.' . $ext;

        Storage::disk('s3')->put($key, $bytes);

        TravelAttachment::create([
            'travel_request_id' => $travel->id,
            'type' => $data['type'],
            'file_name' => $data['file_name'],
            'mime_type' => $mime,
            's3_key' => $key,
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Travel attachment uploaded.');
    }

    public function downloadAttachment(Request $request, TravelRequest $travel, TravelAttachment $attachment)
    {
        $this->authorizeViewTravel($request, $travel);
        abort_unless((int) $attachment->travel_request_id === (int) $travel->id, 404);
        abort_unless(Storage::disk('s3')->exists($attachment->s3_key), 404);

        return response(Storage::disk('s3')->get($attachment->s3_key), 200, [
            'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . $attachment->file_name . '"',
        ]);
    }

    public function deleteAttachment(Request $request, TravelRequest $travel, TravelAttachment $attachment)
    {
        $this->authorizeEdit($request, $travel);
        abort_unless((int) $attachment->travel_request_id === (int) $travel->id, 404);

        if (Storage::disk('s3')->exists($attachment->s3_key)) {
            Storage::disk('s3')->delete($attachment->s3_key);
        }
        $attachment->delete();

        return back()->with('success', 'Travel attachment deleted.');
    }

    public function printIot(Request $request, TravelRequest $travel)
    {
        $this->authorizeViewTravel($request, $travel);

        $travel->load(['traveler', 'division', 'divisionChief', 'itineraryItems']);

        return view('travel.iot_print', [
            'travel' => $travel,
            'totals' => $this->itineraryTotals($travel),
        ]);
    }

    private function validatedTravel(Request $request, ?TravelRequest $travel = null): array
    {
        return $request->validate([
            'traveler_id' => ['required', 'exists:users,id'],
            'division_chief_id' => ['nullable', 'exists:users,id'],
            'travel_type' => ['required', Rule::in(['oed_initiated', 'campus_initiated'])],
            'funding_source' => ['required', Rule::in(['campus_funds', 'oed_funds', 'external_funds', 'personal_no_cost'])],
            'fund_cluster' => 'nullable|string|max:50',
            'travel_mode' => ['required', Rule::in(['land', 'air', 'sea', 'mixed'])],
            'origin' => 'nullable|string|max:255',
            'destination' => 'required|string|max:255',
            'purpose' => 'required|string|max:2000',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'passenger_count' => 'required|integer|min:1|max:200',
            'requires_cash_advance' => 'boolean',
            'cash_advance_amount' => 'nullable|numeric|min:0',
        ]);
    }

    private function scopedQuery(Request $request)
    {
        $query = TravelRequest::query();
        $user = $request->user();

        if (! $this->canViewAll($user)) {
            $query->where(function ($q) use ($user) {
                $q->where('traveler_id', $user->id)
                    ->orWhere('created_by', $user->id)
                    ->orWhere('division_chief_id', $user->id);
            });
        }

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('control_no', 'like', "%{$search}%")
                    ->orWhere('destination', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhereHas('traveler', fn ($t) => $t->where('name', 'like', "%{$search}%"));
            });
        }

        return $query;
    }

    private function formatTravel(TravelRequest $travel, bool $detailed = false): array
    {
        $data = [
            'id' => $travel->id,
            'control_no' => $travel->control_no,
            'traveler_id' => $travel->traveler_id,
            'traveler' => $travel->traveler ? ['id' => $travel->traveler->id, 'name' => $travel->traveler->name, 'position' => $travel->traveler->position] : null,
            'division' => $travel->division ? ['id' => $travel->division->id, 'name' => $travel->division->division_name] : null,
            'division_chief_id' => $travel->division_chief_id,
            'division_chief' => $travel->divisionChief ? ['id' => $travel->divisionChief->id, 'name' => $travel->divisionChief->name] : null,
            'travel_type' => $travel->travel_type,
            'funding_source' => $travel->funding_source,
            'fund_cluster' => $travel->fund_cluster,
            'travel_mode' => $travel->travel_mode,
            'origin' => $travel->origin,
            'destination' => $travel->destination,
            'purpose' => $travel->purpose,
            'start_date' => $travel->start_date?->format('Y-m-d'),
            'end_date' => $travel->end_date?->format('Y-m-d'),
            'passenger_count' => $travel->passenger_count,
            'requires_cash_advance' => (bool) $travel->requires_cash_advance,
            'cash_advance_amount' => (float) $travel->cash_advance_amount,
            'cash_advance_status' => $travel->cash_advance_status,
            'liquidation_status' => $travel->liquidation_status,
            'liquidation_remarks' => $travel->liquidation_remarks,
            'status' => $travel->status,
            'status_label' => $travel->statusLabel(),
            'return_reason' => $travel->return_reason,
            'created_at' => $travel->created_at?->toISOString(),
            'submitted_at' => $travel->submitted_at?->toISOString(),
            'division_approved_at' => $travel->division_approved_at?->toISOString(),
            'fad_reviewed_at' => $travel->fad_reviewed_at?->toISOString(),
            'ocd_approved_at' => $travel->ocd_approved_at?->toISOString(),
            'travel_order_issuance_id' => $travel->travel_order_issuance_id,
            'special_order_issuance_id' => $travel->special_order_issuance_id,
            'vehicle_request_id' => $travel->vehicle_request_id,
            'ors_id' => $travel->ors_id,
            'dv_id' => $travel->dv_id,
            'vehicle_request' => $travel->vehicleRequest,
            'ors' => $travel->ors,
            'dv' => $travel->dv,
            'travel_order_issuance' => $travel->travelOrderIssuance,
            'special_order_issuance' => $travel->specialOrderIssuance,
        ];

        if ($detailed) {
            $data['itinerary_items'] = $travel->itineraryItems->map(fn (TravelItineraryItem $item) => [
                'id' => $item->id,
                'travel_date' => $item->travel_date?->format('Y-m-d'),
                'places_to_visit' => $item->places_to_visit,
                'departure_time' => $item->departure_time ? substr($item->departure_time, 0, 5) : null,
                'arrival_time' => $item->arrival_time ? substr($item->arrival_time, 0, 5) : null,
                'means_of_transportation' => $item->means_of_transportation,
                'transportation_cost' => (float) $item->transportation_cost,
                'per_diem' => (float) $item->per_diem,
                'other_cost' => (float) $item->other_cost,
                'remarks' => $item->remarks,
                'total' => $item->total(),
            ]);
            $data['itinerary_totals'] = $this->itineraryTotals($travel);
            $data['attachments'] = $travel->attachments->map(fn (TravelAttachment $attachment) => [
                'id' => $attachment->id,
                'type' => $attachment->type,
                'file_name' => $attachment->file_name,
                'mime_type' => $attachment->mime_type,
                'uploaded_by' => $attachment->uploader?->name,
                'created_at' => $attachment->created_at?->toISOString(),
                'url' => route('travel.attachments.download', [$travel, $attachment]),
            ]);
            $data['flight_authority'] = $travel->flightAuthority;
        }

        return $data;
    }

    private function itineraryTotals(TravelRequest $travel): array
    {
        $items = $travel->itineraryItems;

        return [
            'transportation' => (float) $items->sum('transportation_cost'),
            'per_diem' => (float) $items->sum('per_diem'),
            'others' => (float) $items->sum('other_cost'),
            'total' => (float) $items->sum(fn (TravelItineraryItem $item) => $item->total()),
        ];
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'division_id' => $user->division_id,
            'permissions' => [
                'canCreate' => $user->hasPermission('travel.create'),
                'canManage' => $user->hasPermission('travel.manage'),
                'canFadReview' => $user->hasPermission('travel.review.fad'),
                'canOcdApprove' => $user->hasPermission('travel.approve.ocd'),
                'canFinance' => $user->hasPermission('travel.finance'),
                'canDivisionApprove' => $user->hasPermission('travel.approve.division'),
            ],
        ];
    }

    private function pendingStatusesFor(User $user): array
    {
        $statuses = [];
        if ($user->hasPermission('travel.approve.division')) {
            $statuses[] = 'submitted';
        }
        if ($user->hasPermission('travel.review.fad')) {
            $statuses[] = 'division_approved';
        }
        if ($user->hasPermission('travel.approve.ocd')) {
            $statuses[] = 'fad_reviewed';
        }
        if ($user->hasPermission('travel.finance')) {
            array_push($statuses, 'ocd_approved', 'transport_arranged', 'cash_advance_processing', 'released', 'completed');
        }

        return array_unique($statuses);
    }

    private function canViewAll(User $user): bool
    {
        return $user->hasPermission('travel.manage')
            || $user->hasPermission('travel.review.fad')
            || $user->hasPermission('travel.approve.ocd')
            || $user->hasPermission('travel.finance');
    }

    private function authorizeView(Request $request): void
    {
        abort_unless($request->user()?->hasPermission('travel.view'), 403);
    }

    private function authorizeCreate(Request $request): void
    {
        abort_unless($request->user()?->hasPermission('travel.create'), 403);
    }

    private function authorizeViewTravel(Request $request, TravelRequest $travel): void
    {
        $this->authorizeView($request);
        $user = $request->user();

        if ($this->canViewAll($user)
            || (int) $travel->traveler_id === (int) $user->id
            || (int) $travel->created_by === (int) $user->id
            || (int) $travel->division_chief_id === (int) $user->id
        ) {
            return;
        }

        abort(403);
    }

    private function authorizeEdit(Request $request, TravelRequest $travel): void
    {
        $this->authorizeViewTravel($request, $travel);
        $user = $request->user();
        abort_unless(
            $user->hasPermission('travel.manage')
            || ((int) $travel->traveler_id === (int) $user->id && in_array($travel->status, ['draft', 'returned'], true))
            || ((int) $travel->created_by === (int) $user->id && in_array($travel->status, ['draft', 'returned'], true))
            || $user->hasPermission('travel.finance'),
            403
        );
    }

    private function authorizeDivisionApproval(Request $request, TravelRequest $travel): void
    {
        $user = $request->user();
        abort_unless($user?->hasPermission('travel.approve.division'), 403);
        abort_unless(! $travel->division_chief_id || (int) $travel->division_chief_id === (int) $user->id || $user->hasPermission('travel.manage'), 403);
    }

    private function authorizeFad(Request $request): void
    {
        abort_unless($request->user()?->hasPermission('travel.review.fad') || $request->user()?->hasPermission('travel.finance'), 403);
    }

    private function authorizeOcd(Request $request): void
    {
        abort_unless($request->user()?->hasPermission('travel.approve.ocd'), 403);
    }

    private function authorizeApprover(Request $request, TravelRequest $travel): void
    {
        $user = $request->user();
        abort_unless(
            $user?->hasPermission('travel.manage')
            || $user?->hasPermission('travel.review.fad')
            || $user?->hasPermission('travel.approve.ocd')
            || $user?->hasPermission('travel.approve.division'),
            403
        );
    }
}
