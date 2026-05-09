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
            data: $request->validated(),
        );

        return response()->json([
            'message'        => 'Accomplishment saved.',
            'accomplishment' => $accomplishment,
        ], 201);
    }

    // ─── API: Update ──────────────────────────────────────────────────────────

    public function update(\Illuminate\Http\Request $request, WFHAccomplishment $wfhAccomplishment)
    {
        $this->authorizeOwner($wfhAccomplishment);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:2000'],
            'time_from'   => ['nullable', 'date_format:H:i'],
            'time_to'     => ['nullable', 'date_format:H:i'],
        ]);

        if (! empty($data['time_from']) && ! empty($data['time_to'])
            && strtotime($data['time_to']) <= strtotime($data['time_from'])) {
            return response()->json([
                'errors' => ['time_to' => 'Time To must be after Time From.'],
            ], 422);
        }

        $wfhAccomplishment->update([
            'description' => $data['description'],
            'title'       => mb_strimwidth($data['description'], 0, 100, '…'),
            'time_from'   => $data['time_from'] ?? null,
            'time_to'     => $data['time_to'] ?? null,
        ]);

        return response()->json([
            'message'        => 'Accomplishment updated.',
            'accomplishment' => $wfhAccomplishment->fresh(),
        ]);
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
