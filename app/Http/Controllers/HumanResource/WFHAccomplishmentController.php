<?php

namespace App\Http\Controllers\HumanResource;

use App\Http\Controllers\Controller;
use App\Http\Requests\WFH\StoreAccomplishmentRequest;
use App\Models\WFHAccomplishment;
use App\Services\WFHService;
use Illuminate\Support\Facades\Auth;

class WFHAccomplishmentController extends Controller
{
    public function __construct(private readonly WFHService $wfhService) {}

    // ─── API: List My Accomplishments ─────────────────────────────────────────

    public function index()
    {
        $user = Auth::user();

        $accomplishments = WFHAccomplishment::with('attendance:id,date,time_in,time_out')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($accomplishments);
    }

    // ─── API: Store ───────────────────────────────────────────────────────────

    public function store(StoreAccomplishmentRequest $request)
    {
        $accomplishment = $this->wfhService->storeAccomplishment(
            data:  $request->validated(),
            photo: $request->file('photo'),
        );

        return response()->json([
            'message'        => 'Accomplishment saved.',
            'accomplishment' => $accomplishment,
        ], 201);
    }

    // ─── API: Destroy ─────────────────────────────────────────────────────────

    public function destroy(WFHAccomplishment $wfhAccomplishment)
    {
        $this->authorizeOwner($wfhAccomplishment);

        $this->wfhService->deleteAccomplishment($wfhAccomplishment);

        return response()->json(['message' => 'Accomplishment deleted.']);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function authorizeOwner(WFHAccomplishment $accomplishment): void
    {
        if ($accomplishment->user_id !== Auth::id()) {
            abort(403, 'You can only delete your own accomplishments.');
        }
    }
}
