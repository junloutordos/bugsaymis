<?php

namespace App\Http\Controllers\SALN;

use App\Http\Controllers\Controller;
use App\Models\SALN\SalnRecord;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SalnReviewController extends Controller
{
    // ══════════════════════════════════════════════════════════════════════════
    // SALN COMMITTEE — Review workflow
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * getPendingSALN — list all records awaiting committee action.
     */
    public function index(Request $request): Response
    {
        $this->authorize('saln.review');

        $query = SalnRecord::with('user:id,name,position')
            ->whereIn('status', ['submitted', 'under_review'])
            ->orderByDesc('submitted_at');

        if ($request->filled('year')) {
            $query->where('year', (int) $request->year);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }

        return Inertia::render('SALN/Review/Index', [
            'records' => $query->paginate(20)->withQueryString()->through(fn ($r) => [
                'id'           => $r->id,
                'year'         => $r->year,
                'as_of_date'   => $r->as_of_date?->toDateString(),
                'status'       => $r->status,
                'status_label' => $r->status_label,
                'status_color' => $r->status_color,
                'net_worth'    => $r->net_worth,
                'submitted_at' => $r->submitted_at?->toDateTimeString(),
                'user'         => $r->user,
            ]),
            'filters' => $request->only('year', 'search'),
        ]);
    }

    /**
     * reviewSALN — mark as under_review and show the detail for committee.
     */
    public function show(SalnRecord $saln): Response
    {
        $this->authorize('saln.review');

        // Automatically move submitted → under_review on first committee open
        if ($saln->isSubmitted()) {
            $oldStatus = $saln->status;
            $saln->update([
                'status'      => 'under_review',
                'reviewed_at' => now(),
                'reviewed_by' => Auth::id(),
            ]);
            $saln->logAction(Auth::id(), 'reviewed', $oldStatus, 'under_review');
        }

        $saln->load([
            'user:id,name,position',
            'realProperties',
            'personalProperties',
            'liabilities',
            'businessInterests',
            'relatives',
            'reviews.actor:id,name',
        ]);

        return Inertia::render('SALN/Review/Show', [
            'saln' => $this->formatForReview($saln),
        ]);
    }

    /**
     * approveSALN — approve and optionally add remarks.
     */
    public function approve(Request $request, SalnRecord $saln): RedirectResponse
    {
        $this->authorize('saln.approve');

        if (! in_array($saln->status, ['submitted', 'under_review'])) {
            return back()->with('error', 'Only submitted or under-review SALNs can be approved.');
        }

        $data = $request->validate([
            'remarks' => 'nullable|string|max:1000',
        ]);

        $oldStatus = $saln->status;

        $saln->update([
            'status'      => 'approved',
            'approved_at' => now(),
            'approved_by' => Auth::id(),
        ]);

        $saln->logAction(Auth::id(), 'approved', $oldStatus, 'approved', $data['remarks'] ?? null);

        return redirect()
            ->route('saln.review.index')
            ->with('success', "SALN of {$saln->user->name} ({$saln->year}) approved.");
    }

    /**
     * returnSALN — return for revision with required remarks.
     */
    public function return(Request $request, SalnRecord $saln): RedirectResponse
    {
        $this->authorize('saln.review');

        if (! in_array($saln->status, ['submitted', 'under_review'])) {
            return back()->with('error', 'Only submitted or under-review SALNs can be returned.');
        }

        $data = $request->validate([
            'remarks' => 'required|string|max:1000',
        ]);

        $oldStatus = $saln->status;

        $saln->update([
            'status'      => 'returned',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        $saln->logAction(Auth::id(), 'returned', $oldStatus, 'returned', $data['remarks']);

        return redirect()
            ->route('saln.review.index')
            ->with('success', "SALN returned to {$saln->user->name} for revision.");
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function formatForReview(SalnRecord $saln): array
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
            'user'                      => $saln->user,
            'real_properties'           => $saln->realProperties,
            'personal_properties'       => $saln->personalProperties,
            'liabilities'               => $saln->liabilities,
            'business_interests'        => $saln->businessInterests,
            'relatives'                 => $saln->relatives,
            'reviews'                   => $saln->reviews->map(fn ($r) => [
                'id'           => $r->id,
                'action'       => $r->action,
                'action_label' => $r->action_label,
                'action_icon'  => $r->action_icon,
                'remarks'      => $r->remarks,
                'status_from'  => $r->status_from,
                'status_to'    => $r->status_to,
                'actor'        => $r->actor?->only('id', 'name'),
                'created_at'   => $r->created_at->toDateTimeString(),
            ]),
        ];
    }
}
