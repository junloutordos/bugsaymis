<?php

namespace App\Http\Controllers\HumanResource;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class GatePassController extends Controller
{
    public function index(Request $request)
    {
        $rows = DB::table('gatepass')->orderByDesc('id')->get();

        $divisionChief = null;
        if ($request->user() && $request->user()->division_id) {
            $division = DB::table('divisions')->where('id', $request->user()->division_id)->first();
            if ($division && !empty($division->division_chief_id)) {
                $chief = DB::table('users')->where('id', $division->division_chief_id)->first();
                if ($chief) {
                    $divisionChief = [
                        'id' => $chief->id,
                        'name' => $chief->name,
                        'position' => $chief->position ?? 'Division Chief',
                    ];
                }
            }
        }

        // also resolve the campus director (division named 'Office of the Campus Director')
        $director = null;
        $officeDivision = DB::table('divisions')->where('division_name', 'Office of the Campus Director')->first();
        if ($officeDivision && !empty($officeDivision->division_chief_id)) {
            $d = DB::table('users')->where('id', $officeDivision->division_chief_id)->first();
            if ($d) {
                $director = [
                    'id' => $d->id,
                    'name' => $d->name,
                    'position' => $d->position ?? 'Director',
                ];
            }
        }

        return Inertia::render('HumanResource/GatePass/Index', [
            'rows' => $rows,
            'divisionChief' => $divisionChief,
            'director' => $director,
        ]);
    }

    public function store(Request $request)
    {
        $input = $request->only([
            'controlno','badgeID','gatepass_type','gatepass_timeout','gatepass_timein','gatepass_date','gatepass_datefiled','destination','purpose','date_time_approved','actual_timeout','actual_timein','time_consumed','status'
        ]);

        $validator = \Illuminate\Support\Facades\Validator::make($input, [
            'controlno' => 'nullable|string|max:50',
            'badgeID' => 'nullable|integer',
            'gatepass_type' => 'nullable|string|max:100',
            'gatepass_timeout' => 'nullable|string|max:50',
            'gatepass_timein' => 'nullable|string|max:50',
            'gatepass_date' => 'nullable|string|max:50',
            'gatepass_datefiled' => 'nullable|string|max:50',
            'destination' => 'nullable|string|max:255',
            'purpose' => 'nullable|string|max:1000',
            'date_time_approved' => 'nullable|string|max:100',
            'actual_timeout' => 'nullable|string|max:100',
            'actual_timein' => 'nullable|string|max:100',
            'time_consumed' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Auto-generate controlno if not provided, following legacy pattern YEAR-MON-####
        if (empty($data['controlno'])) {
            $last = DB::table('gatepass')->select('controlno')->orderByDesc('id')->value('controlno');
            $year = now()->format('Y');
            $mon = now()->format('m');

            if (!$last) {
                $seq = 1;
            } else {
                $parts = explode('-', $last);
                $prevYear = $parts[0] ?? null;
                $prevSeq = isset($parts[2]) ? intval($parts[2]) : 0;
                if ($year != $prevYear) {
                    $seq = 1;
                } else {
                    $seq = $prevSeq + 1;
                }
            }

            // ensure uniqueness in case of race
            do {
                $rowid = sprintf('%04d', $seq);
                $candidate = $year . '-' . $mon . '-' . $rowid;
                $exists = DB::table('gatepass')->where('controlno', $candidate)->exists();
                if ($exists) $seq++;
            } while ($exists);

            $data['controlno'] = $candidate;
        }

        // Normalize and prepare insert data to match migration columns.
        $insert = [];

        // controlno guaranteed to exist from earlier generation
        $insert['controlno'] = $data['controlno'] ?? '';

        // badgeNumber: use current authenticated user's badge_id from users table when available
        if ($request->user()) {
            $insert['badgeNumber'] = $request->user()->badge_id ?? $request->user()->badgeNumber ?? $request->user()->badgeID ?? null;
        } else {
            // fallback to provided badgeID if user not authenticated (unlikely)
            $insert['badgeNumber'] = $data['badgeID'] ?? null;
        }

        // other fields - ensure keys exist (DB columns are non-nullable in migration)
        $insert['gatepass_type'] = $data['gatepass_type'] ?? '';
        $insert['gatepass_timeout'] = $data['gatepass_timeout'] ?? '';
        $insert['gatepass_timein'] = $data['gatepass_timein'] ?? '';
        $insert['gatepass_date'] = $data['gatepass_date'] ?? '';
        $insert['destination'] = $data['destination'] ?? '';
        $insert['purpose'] = $data['purpose'] ?? '';
        $insert['date_time_approved'] = $data['date_time_approved'] ?? '';
        $insert['actual_timeout'] = $data['actual_timeout'] ?? '';
        $insert['actual_timein'] = $data['actual_timein'] ?? '';
        $insert['time_consumed'] = $data['time_consumed'] ?? '';
        // default status to 'Pending' if not provided (first creation)
        $insert['status'] = !empty($data['status']) ? $data['status'] : 'Pending';

        $id = DB::table('gatepass')->insertGetId(array_merge($insert, ['created_at' => now(), 'updated_at' => now()]));
        $row = DB::table('gatepass')->where('id', $id)->first();
        return response()->json(['row' => $row], 201);
    }

    public function update(Request $request, $id)
    {
        $input = $request->only([
            'controlno','badgeID','gatepass_type','gatepass_timeout','gatepass_timein','gatepass_date','gatepass_datefiled','destination','purpose','date_time_approved','actual_timeout','actual_timein','time_consumed','status'
        ]);

        $validator = \Illuminate\Support\Facades\Validator::make($input, [
            'controlno' => 'nullable|string|max:50',
            'badgeID' => 'nullable|integer',
            'gatepass_type' => 'nullable|string|max:100',
            'gatepass_timeout' => 'nullable|string|max:50',
            'gatepass_timein' => 'nullable|string|max:50',
            'gatepass_date' => 'nullable|string|max:50',
            'gatepass_datefiled' => 'nullable|string|max:50',
            'destination' => 'nullable|string|max:255',
            'purpose' => 'nullable|string|max:1000',
            'date_time_approved' => 'nullable|string|max:100',
            'actual_timeout' => 'nullable|string|max:100',
            'actual_timein' => 'nullable|string|max:100',
            'time_consumed' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Force badgeNumber to authenticated user's badge_id (do not allow changing badge via request)
        if ($request->user()) {
            $data['badgeNumber'] = $request->user()->badge_id ?? $request->user()->badgeNumber ?? $request->user()->badgeID ?? null;
        }
        // remove any incoming badgeID to avoid attempting to update a non-existent column
        if (isset($data['badgeID'])) unset($data['badgeID']);

        DB::table('gatepass')->where('id', $id)->update(array_merge($data, ['updated_at' => now()]));
        $row = DB::table('gatepass')->where('id', $id)->first();
        return response()->json(['row' => $row]);
    }

    public function destroy($id)
    {
        DB::table('gatepass')->where('id', $id)->delete();
        return response()->json(['deleted' => true]);
    }
}
