<?php

namespace App\Http\Controllers\SALN;

use App\Http\Controllers\Controller;
use App\Models\SALN\SalnRecord;
use App\Models\SALN\SalnRealProperty;
use App\Models\SALN\SalnPersonalProperty;
use App\Models\SALN\SalnLiability;
use App\Models\SALN\SalnBusinessInterest;
use App\Models\SALN\SalnRelative;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SalnController extends Controller
{
    // ══════════════════════════════════════════════════════════════════════════
    // SALN RECORD — Employee self-service
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * getSALNByUser / getSALNByYear
     * List all SALN records for the authenticated employee.
     * HR/Admin can pass ?user_id= to view another employee's history.
     */
    public function index(Request $request): Response
    {
        $this->authorize('saln.create');

        $userId = Auth::id();

        // HR/Admin can view any employee's SALN history
        if (
            $request->filled('user_id') &&
            Auth::user()->hasPermission('saln.view_all')
        ) {
            $userId = (int) $request->user_id;
        }

        $query = SalnRecord::where('user_id', $userId)
            ->withCount(['realProperties', 'personalProperties', 'liabilities', 'businessInterests', 'relatives'])
            ->orderByDesc('year');

        // Filter by year
        if ($request->filled('year')) {
            $query->where('year', (int) $request->year);
        }

        $records = $query->get()->map(fn ($r) => [
            'id'                   => $r->id,
            'year'                 => $r->year,
            'as_of_date'           => $r->as_of_date?->toDateString(),
            'status'               => $r->status,
            'status_label'         => $r->status_label,
            'status_color'         => $r->status_color,
            'total_assets'         => $r->total_assets,
            'total_liabilities'    => $r->total_liabilities,
            'net_worth'            => $r->net_worth,
            'submitted_at'         => $r->submitted_at?->toDateTimeString(),
            'approved_at'          => $r->approved_at?->toDateTimeString(),
            'filed_at'             => $r->filed_at?->toDateTimeString(),
            'is_editable'          => $r->isEditable(),
            'real_properties_count'     => $r->real_properties_count,
            'personal_properties_count' => $r->personal_properties_count,
            'liabilities_count'         => $r->liabilities_count,
            'business_interests_count'  => $r->business_interests_count,
            'relatives_count'           => $r->relatives_count,
        ]);

        $viewedUser = $userId !== Auth::id()
            ? User::find($userId, ['id', 'name', 'position'])
            : null;

        return Inertia::render('SALN/Index', [
            'records'    => $records,
            'viewedUser' => $viewedUser,
            'filters'    => $request->only('year', 'user_id'),
            'canManage'  => Auth::user()->hasPermission('saln.view_all'),
        ]);
    }

    /**
     * Show the "Create new SALN" form.
     * Pre-fills year with current year; prevents duplicate if one exists.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        $this->authorize('saln.create');

        $year = (int) ($request->input('year', now()->year));

        // Prevent duplicate for the same year
        $existing = SalnRecord::where('user_id', Auth::id())
            ->where('year', $year)
            ->first();

        if ($existing) {
            return redirect()
                ->route('saln.show', $existing->id)
                ->with('info', "You already have a SALN record for {$year}. Redirected to existing record.");
        }

        return Inertia::render('SALN/Create', [
            'year'    => $year,
            'asOfDate'=> "{$year}-12-31",
            'user'    => Auth::user()->only('id', 'name', 'position'),
        ]);
    }

    /**
     * createDraft — store a new SALN draft.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('saln.create');

        $data = $request->validate([
            'year'                  => 'required|integer|min:2000|max:2099',
            'as_of_date'            => 'required|date',
            'spouse_name'           => 'nullable|string|max:255',
            'spouse_position'       => 'nullable|string|max:255',
            'spouse_office'         => 'nullable|string|max:255',
            'spouse_government_id'  => 'nullable|string|max:100',
        ]);

        // Prevent duplicate
        $exists = SalnRecord::where('user_id', Auth::id())
            ->where('year', $data['year'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['year' => "A SALN record for {$data['year']} already exists."]);
        }

        $saln = SalnRecord::create([
            ...$data,
            'user_id' => Auth::id(),
            'status'  => 'draft',
        ]);

        $saln->logAction(Auth::id(), 'created', '', 'draft');

        return redirect()
            ->route('saln.show', $saln->id)
            ->with('success', "SALN {$saln->year} draft created. Fill in your assets, liabilities, and other details.");
    }

    /**
     * Show a full SALN record with all sections.
     * Used by the employee for data entry and review before submission.
     */
    public function show(SalnRecord $saln): Response
    {
        // Employee can only view their own; HR/committee can view any
        $this->authorizeView($saln);

        $saln->load([
            'user:id,name,position',
            'realProperties',
            'personalProperties',
            'liabilities',
            'businessInterests',
            'relatives',
            'reviews.actor:id,name',
            'reviewer:id,name',
            'approver:id,name',
            'filer:id,name',
        ]);

        return Inertia::render('SALN/Show', [
            'saln'      => $this->formatRecord($saln),
            'canManage' => Auth::user()->hasPermission('saln.view_all'),
        ]);
    }

    /**
     * updateSALN — update spouse/header info on an editable SALN.
     */
    public function update(Request $request, SalnRecord $saln): RedirectResponse
    {
        $this->authorizeEdit($saln);

        $data = $request->validate([
            'as_of_date'           => 'required|date',
            'spouse_name'          => 'nullable|string|max:255',
            'spouse_position'      => 'nullable|string|max:255',
            'spouse_office'        => 'nullable|string|max:255',
            'spouse_government_id' => 'nullable|string|max:100',
        ]);

        $saln->update($data);
        $saln->logAction(Auth::id(), 'updated', $saln->status, $saln->status);

        return back()->with('success', 'SALN header updated.');
    }

    /**
     * Delete a draft SALN (only drafts can be deleted).
     */
    public function destroy(SalnRecord $saln): RedirectResponse
    {
        $this->authorizeEdit($saln);

        if (! $saln->isDraft()) {
            return back()->with('error', 'Only draft SALN records can be deleted.');
        }

        $year = $saln->year;
        $saln->delete();          // cascades to all child rows via FK

        return redirect()
            ->route('saln.index')
            ->with('success', "SALN {$year} draft deleted.");
    }

    /**
     * submitSALN — transition from draft/returned → submitted.
     */
    public function submit(SalnRecord $saln): RedirectResponse
    {
        $this->authorizeOwnRecord($saln);

        if (! Auth::user()->hasPermission('saln.submit')) {
            abort(403, 'You do not have permission to submit a SALN.');
        }

        if (! $saln->isSubmittable()) {
            return back()->with('error', 'This SALN cannot be submitted in its current status.');
        }

        $oldStatus = $saln->status;

        $saln->update([
            'status'       => 'submitted',
            'submitted_at' => now(),
        ]);

        $saln->logAction(Auth::id(), 'submitted', $oldStatus, 'submitted');

        return back()->with('success', 'SALN submitted successfully. It is now pending review by the SALN Committee.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SECTION: Real Properties
    // ══════════════════════════════════════════════════════════════════════════

    public function storeRealProperty(Request $request, SalnRecord $saln): RedirectResponse
    {
        $this->authorizeEdit($saln);

        $data = $request->validate([
            'kind'                      => 'required|string|max:100',
            'exact_location'            => 'required|string|max:500',
            'assessed_value'            => 'required|numeric|min:0',
            'current_fair_market_value' => 'required|numeric|min:0',
            'year_acquired'             => 'nullable|integer|min:1900|max:' . now()->year,
            'acquisition_mode'          => 'required|in:purchased,inherited,gift,others',
            'acquisition_mode_other'    => 'nullable|string|max:100',
            'acquisition_cost'          => 'required|numeric|min:0',
            'owner'                     => 'required|in:self,spouse,dependent',
        ]);

        $saln->realProperties()->create($data);
        $saln->recomputeTotals();

        return back()->with('success', 'Real property added.');
    }

    public function updateRealProperty(Request $request, SalnRecord $saln, SalnRealProperty $property): RedirectResponse
    {
        $this->authorizeEdit($saln);
        abort_unless($property->saln_record_id === $saln->id, 404);

        $data = $request->validate([
            'kind'                      => 'required|string|max:100',
            'exact_location'            => 'required|string|max:500',
            'assessed_value'            => 'required|numeric|min:0',
            'current_fair_market_value' => 'required|numeric|min:0',
            'year_acquired'             => 'nullable|integer|min:1900|max:' . now()->year,
            'acquisition_mode'          => 'required|in:purchased,inherited,gift,others',
            'acquisition_mode_other'    => 'nullable|string|max:100',
            'acquisition_cost'          => 'required|numeric|min:0',
            'owner'                     => 'required|in:self,spouse,dependent',
        ]);

        $property->update($data);
        $saln->recomputeTotals();

        return back()->with('success', 'Real property updated.');
    }

    public function destroyRealProperty(SalnRecord $saln, SalnRealProperty $property): RedirectResponse
    {
        $this->authorizeEdit($saln);
        abort_unless($property->saln_record_id === $saln->id, 404);

        $property->delete();
        $saln->recomputeTotals();

        return back()->with('success', 'Real property removed.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SECTION: Personal Properties
    // ══════════════════════════════════════════════════════════════════════════

    public function storePersonalProperty(Request $request, SalnRecord $saln): RedirectResponse
    {
        $this->authorizeEdit($saln);

        $data = $request->validate([
            'description'      => 'required|string|max:500',
            'category'         => 'required|in:motor_vehicle,jewelry,artwork,shares_of_stock,cash_on_hand,bank_deposits,receivables,others',
            'year_acquired'    => 'nullable|integer|min:1900|max:' . now()->year,
            'acquisition_cost' => 'required|numeric|min:0',
            'owner'            => 'required|in:self,spouse,dependent',
        ]);

        $saln->personalProperties()->create($data);
        $saln->recomputeTotals();

        return back()->with('success', 'Personal property added.');
    }

    public function updatePersonalProperty(Request $request, SalnRecord $saln, SalnPersonalProperty $property): RedirectResponse
    {
        $this->authorizeEdit($saln);
        abort_unless($property->saln_record_id === $saln->id, 404);

        $data = $request->validate([
            'description'      => 'required|string|max:500',
            'category'         => 'required|in:motor_vehicle,jewelry,artwork,shares_of_stock,cash_on_hand,bank_deposits,receivables,others',
            'year_acquired'    => 'nullable|integer|min:1900|max:' . now()->year,
            'acquisition_cost' => 'required|numeric|min:0',
            'owner'            => 'required|in:self,spouse,dependent',
        ]);

        $property->update($data);
        $saln->recomputeTotals();

        return back()->with('success', 'Personal property updated.');
    }

    public function destroyPersonalProperty(SalnRecord $saln, SalnPersonalProperty $property): RedirectResponse
    {
        $this->authorizeEdit($saln);
        abort_unless($property->saln_record_id === $saln->id, 404);

        $property->delete();
        $saln->recomputeTotals();

        return back()->with('success', 'Personal property removed.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SECTION: Liabilities
    // ══════════════════════════════════════════════════════════════════════════

    public function storeLiability(Request $request, SalnRecord $saln): RedirectResponse
    {
        $this->authorizeEdit($saln);

        $data = $request->validate([
            'nature'              => 'required|in:housing_loan,car_loan,personal_loan,credit_card,gsis_policy_loan,gsis_salary_loan,pagibig_loan,sss_loan,landbank_salary_loan,business_loan,others',
            'nature_other'        => 'nullable|string|max:150',
            'creditor_name'       => 'required|string|max:255',
            'outstanding_balance' => 'required|numeric|min:0',
        ]);

        $saln->liabilities()->create($data);
        $saln->recomputeTotals();

        return back()->with('success', 'Liability added.');
    }

    public function updateLiability(Request $request, SalnRecord $saln, SalnLiability $liability): RedirectResponse
    {
        $this->authorizeEdit($saln);
        abort_unless($liability->saln_record_id === $saln->id, 404);

        $data = $request->validate([
            'nature'              => 'required|in:housing_loan,car_loan,personal_loan,credit_card,gsis_policy_loan,gsis_salary_loan,pagibig_loan,sss_loan,landbank_salary_loan,business_loan,others',
            'nature_other'        => 'nullable|string|max:150',
            'creditor_name'       => 'required|string|max:255',
            'outstanding_balance' => 'required|numeric|min:0',
        ]);

        $liability->update($data);
        $saln->recomputeTotals();

        return back()->with('success', 'Liability updated.');
    }

    public function destroyLiability(SalnRecord $saln, SalnLiability $liability): RedirectResponse
    {
        $this->authorizeEdit($saln);
        abort_unless($liability->saln_record_id === $saln->id, 404);

        $liability->delete();
        $saln->recomputeTotals();

        return back()->with('success', 'Liability removed.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SECTION: Business Interests
    // ══════════════════════════════════════════════════════════════════════════

    public function storeBusinessInterest(Request $request, SalnRecord $saln): RedirectResponse
    {
        $this->authorizeEdit($saln);

        $data = $request->validate([
            'entity_name'               => 'required|string|max:255',
            'business_address'          => 'required|string|max:500',
            'nature_of_business'        => 'required|string|max:255',
            'date_acquired'             => 'nullable|date',
            'acquisition_cost'          => 'required|numeric|min:0',
            'present_fair_market_value' => 'required|numeric|min:0',
            'owner'                     => 'required|in:self,spouse',
        ]);

        $saln->businessInterests()->create($data);

        return back()->with('success', 'Business interest added.');
    }

    public function updateBusinessInterest(Request $request, SalnRecord $saln, SalnBusinessInterest $interest): RedirectResponse
    {
        $this->authorizeEdit($saln);
        abort_unless($interest->saln_record_id === $saln->id, 404);

        $data = $request->validate([
            'entity_name'               => 'required|string|max:255',
            'business_address'          => 'required|string|max:500',
            'nature_of_business'        => 'required|string|max:255',
            'date_acquired'             => 'nullable|date',
            'acquisition_cost'          => 'required|numeric|min:0',
            'present_fair_market_value' => 'required|numeric|min:0',
            'owner'                     => 'required|in:self,spouse',
        ]);

        $interest->update($data);

        return back()->with('success', 'Business interest updated.');
    }

    public function destroyBusinessInterest(SalnRecord $saln, SalnBusinessInterest $interest): RedirectResponse
    {
        $this->authorizeEdit($saln);
        abort_unless($interest->saln_record_id === $saln->id, 404);

        $interest->delete();

        return back()->with('success', 'Business interest removed.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SECTION: Relatives in Government Service
    // ══════════════════════════════════════════════════════════════════════════

    public function storeRelative(Request $request, SalnRecord $saln): RedirectResponse
    {
        $this->authorizeEdit($saln);

        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'relationship'   => 'required|string|max:100',
            'position'       => 'required|string|max:255',
            'agency_office'  => 'required|string|max:255',
            'agency_address' => 'nullable|string|max:500',
        ]);

        $saln->relatives()->create($data);

        return back()->with('success', 'Relative added.');
    }

    public function updateRelative(Request $request, SalnRecord $saln, SalnRelative $relative): RedirectResponse
    {
        $this->authorizeEdit($saln);
        abort_unless($relative->saln_record_id === $saln->id, 404);

        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'relationship'   => 'required|string|max:100',
            'position'       => 'required|string|max:255',
            'agency_office'  => 'required|string|max:255',
            'agency_address' => 'nullable|string|max:500',
        ]);

        $relative->update($data);

        return back()->with('success', 'Relative updated.');
    }

    public function destroyRelative(SalnRecord $saln, SalnRelative $relative): RedirectResponse
    {
        $this->authorizeEdit($saln);
        abort_unless($relative->saln_record_id === $saln->id, 404);

        $relative->delete();

        return back()->with('success', 'Relative removed.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    /** Only the owner or HR/committee may view a SALN record. */
    private function authorizeView(SalnRecord $saln): void
    {
        if (
            $saln->user_id !== Auth::id() &&
            ! Auth::user()->hasAnyPermission(['saln.view_all', 'saln.review'])
        ) {
            abort(403);
        }
    }

    /** Only the owner may edit, and only when editable (draft or returned). */
    private function authorizeEdit(SalnRecord $saln): void
    {
        $this->authorizeOwnRecord($saln);

        if (! $saln->isEditable()) {
            abort(403, 'This SALN record cannot be modified in its current status.');
        }
    }

    /** Ensures the authenticated user owns this record. */
    private function authorizeOwnRecord(SalnRecord $saln): void
    {
        if ($saln->user_id !== Auth::id()) {
            abort(403);
        }
    }

    /** Serialize a full SALN record for Inertia. */
    private function formatRecord(SalnRecord $saln): array
    {
        return [
            'id'                        => $saln->id,
            'year'                      => $saln->year,
            'as_of_date'                => $saln->as_of_date?->toDateString(),
            'status'                    => $saln->status,
            'status_label'              => $saln->status_label,
            'status_color'              => $saln->status_color,
            'total_real_properties'     => $saln->total_real_properties,
            'total_personal_properties' => $saln->total_personal_properties,
            'total_assets'              => $saln->total_assets,
            'total_liabilities'         => $saln->total_liabilities,
            'net_worth'                 => $saln->net_worth,
            'spouse_name'               => $saln->spouse_name,
            'spouse_position'           => $saln->spouse_position,
            'spouse_office'             => $saln->spouse_office,
            'spouse_government_id'      => $saln->spouse_government_id,
            'submitted_at'              => $saln->submitted_at?->toDateTimeString(),
            'reviewed_at'               => $saln->reviewed_at?->toDateTimeString(),
            'approved_at'               => $saln->approved_at?->toDateTimeString(),
            'filed_at'                  => $saln->filed_at?->toDateTimeString(),
            'is_editable'               => $saln->isEditable(),
            'is_submittable'            => $saln->isSubmittable(),
            'user'                      => $saln->user,
            'reviewer'                  => $saln->reviewer?->only('id', 'name'),
            'approver'                  => $saln->approver?->only('id', 'name'),
            'filer'                     => $saln->filer?->only('id', 'name'),
            'real_properties'           => $saln->realProperties,
            'personal_properties'       => $saln->personalProperties,
            'liabilities'               => $saln->liabilities,
            'business_interests'        => $saln->businessInterests,
            'relatives'                 => $saln->relatives,
            'reviews'                   => $saln->reviews->map(fn ($r) => [
                'id'          => $r->id,
                'action'      => $r->action,
                'action_label'=> $r->action_label,
                'action_icon' => $r->action_icon,
                'remarks'     => $r->remarks,
                'status_from' => $r->status_from,
                'status_to'   => $r->status_to,
                'actor'       => $r->actor?->only('id', 'name'),
                'created_at'  => $r->created_at->toDateTimeString(),
            ]),
        ];
    }
}
