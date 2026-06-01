<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Procurement;
use App\Models\ProcurementItem;
use App\Models\Unit;
use App\Models\User;
use App\Mail\ProcurementApprovalMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class ProcurementController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $role = $user->getRoleName();

        $query = Procurement::with(['items', 'requester:id,name'])->latest();

        // keep access similar to other modules: only admins and GSU Head see all
        $canViewAll = in_array($role, ['Administrator', 'GSU Head']);

        if (! $canViewAll) {
            // non-admin users only see their own requests (requested_by stores user id)
            $query->where('requested_by', $user->id ?? null);
        }

        $procurements = $query->get();

        // load units for dropdowns
        $units = Unit::orderBy('name')->get();

        return Inertia::render('Procurements/Index', [
            'procurements' => $procurements,
            'units' => $units,
            'currentUser' => [
                'id' => $user->id ?? null,
                'name' => $user->name ?? null,
                'role' => $role,
            ],
        ]);
    }

    /**
     * Store a new procurement (purchase request).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'pr_date' => 'nullable|date',
            'purpose' => 'nullable|string',
        ]);

        // set requestor to logged-in user's id
        $data['requested_by'] = $request->user()->id ?? null;

        // Ensure pr_no has a value for the insert (column is not nullable in DB)
        $data['pr_no'] = '';

        // Create without the final pr_no first so we have an ID to base the sequence on
        $procurement = Procurement::create($data);

        // Generate PR number: YYYY-MM-XXXX where XXXX is zero-padded ID
        $year = now()->format('Y');
        $month = now()->format('m');
        $seq = str_pad($procurement->id, 4, '0', STR_PAD_LEFT);
        $procurement->pr_no = "{$year}-{$month}-{$seq}";
        $procurement->save();

        return redirect()->route('procurements.index')->with('success', 'Purchase request created');
    }

    /**
     * Update the specified procurement.
     */
    public function update(Request $request, Procurement $procurement)
    {
        $data = $request->validate([
            'pr_date' => 'nullable|date',
            'purpose' => 'nullable|string',
        ]);

        // Do not change pr_no or requested_by here; just update editable fields
        $procurement->pr_date = $data['pr_date'] ?? $procurement->pr_date;
        $procurement->purpose = $data['purpose'] ?? $procurement->purpose;
        $procurement->save();

        return redirect()->route('procurements.index')->with('success', 'Purchase request updated');
    }

    /**
     * Store items for a procurement.
     */
    public function storeItem(Request $request, Procurement $procurement)
    {
        $user = $request->user();
        // simple authorization: only owner or admin can add items
        $role = $user->getRoleName();
        if ($procurement->requested_by && $procurement->requested_by !== $user->id && $role !== 'Administrator') {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
            return back()->with('error', 'Unauthorized');
        }
        // Support either an array of items (batch) or a single item fields
        if ($request->has('items') && is_array($request->input('items'))) {
            $data = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.ppmp_line_item_no' => 'nullable|string',
                'items.*.unit' => 'nullable|string',
                'items.*.description' => 'required|string',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.unit_cost' => 'nullable|numeric|min:0',
            ]);

            $created = [];
            foreach ($data['items'] as $it) {
                $it['procurement_id'] = $procurement->id;
                $it['pr_no'] = $procurement->pr_no;
                $created[] = ProcurementItem::create($it);
            }

            if ($request->wantsJson()) {
                return response()->json(['created' => $created], 201);
            }

            return redirect()->route('procurements.index')->with('success', 'Items added');
        }

        // Single-item case
        $data = $request->validate([
            'ppmp_line_item_no' => 'nullable|string',
            'unit' => 'required|string',
            'description' => 'required|string',
            'quantity' => 'required|integer|min:1',
            'unit_cost' => 'required|numeric|min:0',
        ]);

        $data['procurement_id'] = $procurement->id;
        $data['pr_no'] = $procurement->pr_no;

        $item = ProcurementItem::create($data);

        if ($request->wantsJson()) {
            return response()->json(['item' => $item], 201);
        }

        return redirect()->route('procurements.index')->with('success', 'Item added');
    }

    /**
     * Remove a procurement item.
     */
    public function destroyItem(Request $request, Procurement $procurement, ProcurementItem $item)
    {
        $user = $request->user();
        $role = $user->getRoleName();
        if ($procurement->requested_by && $procurement->requested_by !== $user->id && $role !== 'Administrator') {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
            return back()->with('error', 'Unauthorized');
        }

        if ($item->procurement_id !== $procurement->id) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Item does not belong to procurement'], 400);
            }
            return back()->with('error', 'Item does not belong to procurement');
        }

        try {
            $item->delete();
            if ($request->wantsJson()) {
                return response()->json(['deleted' => true]);
            }
            return redirect()->route('procurements.index')->with('success', 'Item deleted');
        } catch (\Throwable $e) {
            logger()->error('Failed to delete procurement item', ['id' => $item->id, 'error' => $e->getMessage()]);
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Failed to delete item'], 500);
            }
            return back()->with('error', 'Failed to delete item');
        }
    }

    /**
     * Remove the specified procurement.
     */
    public function destroy(Request $request, Procurement $procurement)
    {
        // simple authorization: only admins can delete (follow project patterns if needed)
        $user = $request->user();
        if (! $user->isSuperAdmin()) {
            return back()->with('error', 'Unauthorized');
        }

        try {
            $procurement->delete();
            return redirect()->route('procurements.index')->with('success', 'Purchase request deleted');
        } catch (\Throwable $e) {
            logger()->error('Failed to delete procurement', ['id' => $procurement->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'Failed to delete purchase request');
        }
    }

    /**
     * Send procurement for approval to Budget Officers.
     */
    public function sendForApproval(Request $request, Procurement $procurement)
    {
        $user = $request->user();
        $role = $user->getRoleName();

        // Only owner or admin can send for approval
        if ($procurement->requested_by && $procurement->requested_by !== $user->id && $role !== 'Administrator') {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
            return back()->with('error', 'Unauthorized');
        }

        // Find users whose position contains 'Budget Officer'
        $budgetUsers = User::where('position', 'like', '%Budget Officer%')->get();

        if ($budgetUsers->isEmpty()) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'No Budget Officers found'], 404);
            }
            return back()->with('error', 'No Budget Officers found');
        }

        foreach ($budgetUsers as $bu) {
            $approveUrl = URL::signedRoute('procurements.approve', ['procurement' => $procurement->id, 'approver' => $bu->id], now()->addDays(7));
            $declineUrl = URL::signedRoute('procurements.decline', ['procurement' => $procurement->id, 'approver' => $bu->id], now()->addDays(7));

            try {
                Mail::to($bu->email)->send(new ProcurementApprovalMail($procurement, $approveUrl, $declineUrl));
            } catch (\Throwable $e) {
                logger()->error('Failed to send procurement approval email', ['to' => $bu->email, 'error' => $e->getMessage()]);
            }
        }

        if ($request->wantsJson()) {
            return response()->json(['sent' => true]);
        }

        return back()->with('success', 'Purchase request sent for approval');
    }

    /**
     * Approve by Budget Officer (signed link).
     */
    public function approveByBudgetOfficer(Request $request, Procurement $procurement, $approver)
    {
        if ($procurement->date_approved_budget_officer) {
            return view('procurement_approved', ['procurement' => $procurement, 'already' => true]);
        }

        $procurement->date_approved_budget_officer = now();
        $procurement->save();

        return view('procurement_approved', ['procurement' => $procurement, 'already' => false]);
    }

    public function showBudgetOfficerDeclineForm(Request $request, Procurement $procurement, $approver)
    {
        if ($procurement->date_approved_budget_officer) {
            return view('procurement_approved', ['procurement' => $procurement, 'already' => true]);
        }

        $postAction = route('procurements.decline.submit', ['procurement' => $procurement->id, 'approver' => $approver]);
        return view('procurement_decline', ['procurement' => $procurement, 'postAction' => $postAction]);
    }

    public function submitBudgetOfficerDecline(Request $request, Procurement $procurement, $approver)
    {
        $data = $request->validate(['reason' => 'nullable|string']);

        if ($procurement->date_approved_budget_officer) {
            return view('procurement_declined', ['procurement' => $procurement, 'reason' => 'Already approved']);
        }

        $procurement->date_decline_budget_officer = now();
        $procurement->save();

        return view('procurement_declined', ['procurement' => $procurement, 'reason' => $data['reason'] ?? null]);
    }
}
