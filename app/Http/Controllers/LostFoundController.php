<?php

namespace App\Http\Controllers;

use App\Models\LostFound\LostFoundItem;
use App\Services\LostFoundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class LostFoundController extends Controller
{
    public function __construct(private readonly LostFoundService $svc) {}

    private function canManage(): bool
    {
        $user = Auth::user();

        return $user->isSuperAdmin() || $user->hasPermission('lostfound.manage');
    }

    public function index(): Response
    {
        $manage = $this->canManage();

        $board = LostFoundItem::inCustody()
            ->orderByDesc('turned_over_at')
            ->limit(200)
            ->get()
            ->map(fn ($i) => $this->svc->mapItem($i, $manage));

        $myReports = LostFoundItem::where('reporter_user_id', Auth::id())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($i) => $this->svc->mapItem($i));

        $manageData = [];
        if ($manage) {
            $manageData = [
                'pendingTurnover' => LostFoundItem::foundItems()->where('status', 'pending_turnover')
                    ->with('movements')->orderBy('created_at')
                    ->get()->map(fn ($i) => $this->svc->mapItem($i, true)),
                'openLostReports' => LostFoundItem::lostReports()->whereIn('status', ['open', 'matched'])
                    ->with('movements')->orderByDesc('created_at')
                    ->get()->map(fn ($i) => $this->svc->mapItem($i, true)),
                'resolved' => LostFoundItem::whereIn('status', ['claimed', 'disposed', 'closed'])
                    ->orderByDesc('updated_at')->limit(100)
                    ->get()->map(fn ($i) => $this->svc->mapItem($i, true)),
            ];
        }

        return Inertia::render('LostFound/Index', array_merge([
            'board'       => $board,
            'myReports'   => $myReports,
            'myPoints'    => $this->svc->pointsFor(userId: Auth::id()),
            'categories'  => LostFoundItem::CATEGORIES,
            'leaderboard' => $this->svc->leaderboard(),
            'canManage'   => $manage,
        ], $manageData));
    }

    /** Any employee reports a lost or found item. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type'            => 'required|in:lost,found',
            'item_name'       => 'required|string|max:150',
            'description'     => 'nullable|string|max:1000',
            'category'        => 'nullable|string|max:40',
            'location'        => 'nullable|string|max:150',
            'date_lost_found' => 'required|date|before_or_equal:today',
            'photo_base64'    => 'nullable|string|starts_with:data:image',
        ]);

        $this->svc->report($data, ['user_id' => Auth::id()]);

        return back()->with('success', $data['type'] === 'found'
            ? 'Found item reported — please turn it over to the GSU office.'
            : 'Lost item reported. Check back for matches with items turned over to GSU.');
    }

    /** Reporter closes their own lost report (GSU can close any). */
    public function close(Request $request, LostFoundItem $item): RedirectResponse
    {
        abort_unless($this->canManage() || (int) $item->reporter_user_id === (int) Auth::id(), 403);

        $this->svc->closeLost($item, ['user_id' => Auth::id()], $request->input('notes'));

        return back()->with('success', 'Report closed.');
    }

    /** Item photo — any authenticated employee (board + own reports). */
    public function photo(LostFoundItem $item): SymfonyResponse
    {
        $visible = $this->canManage()
            || ($item->type === 'found' && in_array($item->status, ['in_custody', 'claimed'], true))
            || (int) $item->reporter_user_id === (int) Auth::id();

        abort_unless($visible, 403);

        return $this->svc->photoResponse($item);
    }

    // ── GSU management (lostfound.manage) ────────────────────────────────────

    /** GSU logs a walk-in turnover (no prior report). */
    public function walkIn(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'item_name'           => 'required|string|max:150',
            'description'         => 'nullable|string|max:1000',
            'category'            => 'nullable|string|max:40',
            'location'            => 'nullable|string|max:150',
            'date_lost_found'     => 'required|date|before_or_equal:today',
            'photo_base64'        => 'nullable|string|starts_with:data:image',
            'storage_location'    => 'nullable|string|max:100',
            'finder_user_id'      => 'nullable|exists:users,id',
            'finder_student_id'   => 'nullable|integer',
            'finder_name'         => 'nullable|string|max:150',
        ]);

        $this->svc->logWalkIn($data, [
            'user_id'    => $data['finder_user_id'] ?? null,
            'student_id' => $data['finder_student_id'] ?? null,
            'name'       => $data['finder_name'] ?? null,
        ], $request->user());

        return back()->with('success', 'Item logged into GSU custody.');
    }

    public function receive(Request $request, LostFoundItem $item): RedirectResponse
    {
        $this->svc->receive($item, $request->user(), $request->input('storage_location'));

        return back()->with('success', 'Turnover confirmed — item in GSU custody.'
            . (($item->reporter_user_id || $item->reporter_student_id)
                ? ' Honesty points awarded to the finder.' : ''));
    }

    public function storeAt(Request $request, LostFoundItem $item): RedirectResponse
    {
        $request->validate(['storage_location' => 'required|string|max:100']);

        $this->svc->storeAt($item, $request->user(), $request->input('storage_location'));

        return back()->with('success', 'Storage location updated.');
    }

    public function match(Request $request, LostFoundItem $item): RedirectResponse
    {
        $request->validate(['found_item_id' => 'required|exists:lost_found_items,id']);

        $found = LostFoundItem::findOrFail($request->input('found_item_id'));
        $this->svc->match($item, $found, $request->user());

        return back()->with('success', 'Lost report matched with the found item.');
    }

    public function release(Request $request, LostFoundItem $item): RedirectResponse
    {
        $request->validate(['claimed_by_name' => 'required|string|max:150']);

        $this->svc->release($item, $request->user(), $request->input('claimed_by_name'));

        return back()->with('success', 'Item released to the owner.');
    }

    public function dispose(Request $request, LostFoundItem $item): RedirectResponse
    {
        $this->svc->dispose($item, $request->user(), $request->input('notes'));

        return back()->with('success', 'Item marked as disposed.');
    }
}
