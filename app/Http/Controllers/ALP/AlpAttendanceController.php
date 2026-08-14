<?php

namespace App\Http\Controllers\ALP;

use App\Http\Controllers\Controller;
use App\Models\ALP\AlpAttendance;
use App\Models\ALP\AlpMembership;
use App\Models\ALP\AlpProgramCycle;
use App\Models\ALP\AlpSession;
use App\Services\ALP\AlpAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Mirrors ClassRecordAttendanceController's contract exactly: the roster
 * (active ALP members) is a given — there is no "create roster" step. The
 * only user action is adding/removing DATE columns; every active member
 * always has a row for every date, defaulting to Present, with a single
 * grid-wide upsert on Save. Replaces the old session-card UI/AlpController
 * storeSession()/saveAttendance() flow, which required creating a session
 * (with its own embedded roster) before attendance could be taken at all.
 */
class AlpAttendanceController extends Controller
{
    public function __construct(private AlpAccessService $access) {}

    private function activeMembers(AlpProgramCycle $cycle)
    {
        return $cycle->memberships()
            ->where('status', 'active')
            ->with('student:id,firstname,lastname,middlename')
            ->orderBy('id')
            ->get()
            ->each(fn ($membership) => $membership->student?->append('full_name'));
    }

    // ── GET /cid/alp/cycles/{cycle}/attendance ────────────────────────────────

    public function index(Request $request, AlpProgramCycle $cycle): JsonResponse
    {
        $this->access->authorizeView($request->user(), $cycle);

        $members = $this->activeMembers($cycle);
        $dates = AlpSession::where('alp_program_cycle_id', $cycle->id)
            ->orderBy('session_date')
            ->get(['id', 'session_date']);

        $records = AlpAttendance::whereIn('alp_session_id', $dates->pluck('id'))
            ->whereIn('alp_membership_id', $members->pluck('id'))
            ->get(['alp_session_id', 'alp_membership_id', 'status', 'remarks'])
            ->mapWithKeys(fn ($r) => ["{$r->alp_membership_id}_{$r->alp_session_id}" => [
                'status' => $r->status,
                'remarks' => $r->remarks,
            ]]);

        return response()->json([
            'members' => $members->map(fn ($m) => [
                'membership_id' => $m->id,
                'name' => $m->student?->full_name,
            ])->values(),
            'dates' => $dates->map(fn ($d) => ['id' => $d->id, 'date' => $d->session_date->format('Y-m-d')])->values(),
            'records' => $records,
        ]);
    }

    // ── POST /cid/alp/cycles/{cycle}/attendance/dates ─────────────────────────

    public function storeDate(Request $request, AlpProgramCycle $cycle): JsonResponse
    {
        $this->access->authorizeManage($request->user(), $cycle);
        $validated = $request->validate(['date' => 'required|date']);

        $session = AlpSession::firstOrCreate(
            ['alp_program_cycle_id' => $cycle->id, 'session_date' => $validated['date']],
            ['created_by' => $request->user()->id]
        );

        // Every active member gets an explicit "present" row the moment a
        // date is added — not just an implied UI default. Only inserts rows
        // that are missing (never overwrites), so re-adding a date that
        // already has records, or a member added moments earlier, is safe.
        $membershipIds = $cycle->memberships()->where('status', 'active')->pluck('id');
        if ($membershipIds->isNotEmpty()) {
            $existing = AlpAttendance::where('alp_session_id', $session->id)
                ->whereIn('alp_membership_id', $membershipIds)
                ->pluck('alp_membership_id');
            $missing = $membershipIds->diff($existing);

            if ($missing->isNotEmpty()) {
                $now = now();
                $rows = $missing->map(fn ($membershipId) => [
                    'alp_session_id' => $session->id, 'alp_membership_id' => $membershipId,
                    'status' => 'present', 'recorded_by' => $request->user()->id,
                    'created_at' => $now, 'updated_at' => $now,
                ])->values()->all();
                AlpAttendance::insert($rows);
            }
        }

        return response()->json(['id' => $session->id, 'date' => $session->session_date->format('Y-m-d')]);
    }

    // ── DELETE /cid/alp/cycles/{cycle}/attendance/dates/{session} ────────────

    public function destroyDate(Request $request, AlpProgramCycle $cycle, AlpSession $session): JsonResponse
    {
        $this->access->authorizeManage($request->user(), $cycle);
        abort_unless($session->alp_program_cycle_id === $cycle->id, 404);

        $session->delete();

        return response()->json(['message' => 'Date removed.']);
    }

    // ── POST /cid/alp/cycles/{cycle}/attendance/records ───────────────────────

    public function upsert(Request $request, AlpProgramCycle $cycle): JsonResponse
    {
        $this->access->authorizeManage($request->user(), $cycle);
        $validated = $request->validate([
            'records' => 'required|array|min:1',
            'records.*.session_id' => 'required|integer|exists:alp_sessions,id',
            'records.*.membership_id' => 'required|integer|exists:alp_memberships,id',
            'records.*.status' => 'required|in:present,absent,tardy,cutting,excused',
            'records.*.remarks' => 'nullable|string|max:1000',
        ]);

        // "exists" only proves the IDs are valid rows *somewhere* — confirm
        // they actually belong to THIS cycle before writing, so a crafted
        // request can't touch another cycle's attendance.
        $sessionIds = collect($validated['records'])->pluck('session_id')->unique();
        $validSessionIds = AlpSession::where('alp_program_cycle_id', $cycle->id)->whereIn('id', $sessionIds)->pluck('id');
        abort_if($validSessionIds->count() !== $sessionIds->count(), 422, 'One or more dates do not belong to this ALP.');

        $membershipIds = collect($validated['records'])->pluck('membership_id')->unique();
        $validMembershipIds = AlpMembership::where('alp_program_cycle_id', $cycle->id)->whereIn('id', $membershipIds)->pluck('id');
        abort_if($validMembershipIds->count() !== $membershipIds->count(), 422, 'One or more members do not belong to this ALP.');

        $now = now();
        $rows = collect($validated['records'])->map(fn ($r) => [
            'alp_session_id' => $r['session_id'],
            'alp_membership_id' => $r['membership_id'],
            'status' => $r['status'],
            'remarks' => $r['remarks'] ?? null,
            'recorded_by' => $request->user()->id,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        DB::table('alp_attendance')->upsert(
            $rows,
            ['alp_session_id', 'alp_membership_id'],
            ['status', 'remarks', 'recorded_by', 'updated_at']
        );

        return response()->json(['message' => count($rows).' record(s) saved.']);
    }
}
