<?php

namespace App\Http\Controllers\HumanResource;

use App\Http\Controllers\Controller;
use App\Http\Requests\WFH\TimeInRequest;
use App\Http\Requests\WFH\TimeOutRequest;
use App\Models\FacultyLoading\Designation;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use App\Models\WFHAttendance;
use App\Services\GoogleDriveService;
use App\Services\WFHService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class WFHAttendanceController extends Controller
{
    public function __construct(private readonly WFHService $wfhService) {}

    // ─── Inertia Page ─────────────────────────────────────────────────────────

    public function index()
    {
        $user  = Auth::user();
        $today = Carbon::today()->toDateString();

        $todayAttendance = WFHAttendance::with('accomplishments')
            ->where('user_id', $user->id)
            ->where('date', $today)
            ->first();

        return Inertia::render('HumanResource/WFH/Dashboard', [
            'todayAttendance' => $todayAttendance,
            'today'           => $today,
        ]);
    }

    // ─── API: Time In ─────────────────────────────────────────────────────────

    public function timeIn(TimeInRequest $request)
    {
        $attendance = $this->wfhService->timeIn(
            photoBase64: $request->validated('photo'),
            ip:          $request->ip(),
            lat:         $request->validated('latitude'),
            lng:         $request->validated('longitude'),
        );

        return response()->json([
            'message'    => 'Time in recorded successfully.',
            'attendance' => $attendance,
        ]);
    }

    // ─── API: Time Out ────────────────────────────────────────────────────────

    public function timeOut(TimeOutRequest $request)
    {
        $attendance = $this->wfhService->timeOut(
            photoBase64: $request->validated('photo'),
            lat:         $request->validated('latitude'),
            lng:         $request->validated('longitude'),
        );

        return response()->json([
            'message'    => 'Time out recorded successfully.',
            'attendance' => $attendance,
        ]);
    }

    // ─── API: Break Out ───────────────────────────────────────────────────────

    public function breakOut()
    {
        $attendance = $this->wfhService->breakOut();

        return response()->json([
            'message'    => 'Break started. Enjoy your lunch!',
            'attendance' => $attendance,
        ]);
    }

    // ─── API: Break In ────────────────────────────────────────────────────────

    public function breakIn()
    {
        $attendance = $this->wfhService->breakIn();

        return response()->json([
            'message'    => 'Welcome back! Break ended.',
            'attendance' => $attendance,
        ]);
    }

    // ─── API: My Attendance List ──────────────────────────────────────────────

    public function myAttendance(Request $request)
    {
        $user = Auth::user();

        $query = WFHAttendance::with('accomplishments')
            ->where('user_id', $user->id)
            ->orderByDesc('date');

        if ($request->filled('month')) {
            [$year, $mon] = explode('-', $request->query('month'));
            $query->whereYear('date', $year)->whereMonth('date', $mon);
        }

        return response()->json($query->paginate(20));
    }

    // ─── API: Show Single Record ──────────────────────────────────────────────

    public function show(WFHAttendance $wfhAttendance)
    {
        $this->authorizeViewAttendance($wfhAttendance);

        $wfhAttendance->load(['user:id,name,position', 'accomplishments']);

        return response()->json($wfhAttendance);
    }

    // ─── Inertia: Monitoring Page ─────────────────────────────────────────────

    public function monitorPage()
    {
        $this->authorizeMonitor(Auth::user());

        return Inertia::render('HumanResource/WFH/Monitoring');
    }

    // ─── API: Monitoring (HR / Administrator / Division Chief / OCD) ─────────────

    public function monitor(Request $request)
    {
        $user = Auth::user();
        $user->loadMissing('roles');

        $query = WFHAttendance::with([
                'user:id,name,position,division_id,office_id',
                'accomplishments',
            ])
            ->orderByDesc('date');

        // Administrator (SuperAdmin) and HR see ALL records across the campus.
        // DivisionChief sees only their division; OCD sees only their office.
        if (! ($user->isSuperAdmin() || $user->hasRole('HR'))) {
            if ($user->hasRole('DivisionChief')) {
                $query->whereHas('user', fn ($q) =>
                    $q->where('division_id', $user->division_id)
                );
            } elseif ($user->hasRole('OCD')) {
                $query->whereHas('user', fn ($q) =>
                    $q->where('office_id', $user->office_id)
                );
            }
        }

        if ($request->filled('month')) {
            [$year, $mon] = explode('-', $request->query('month'));
            $query->whereYear('date', $year)->whereMonth('date', $mon);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        }

        if ($request->filled('search')) {
            $query->whereHas('user', fn ($q) =>
                $q->where('name', 'like', '%' . $request->query('search') . '%')
            );
        }

        return response()->json($query->paginate(30));
    }

    // ─── Static Map Proxy ─────────────────────────────────────────────────────

    /**
     * Proxy a static map tile from OpenStreetMap so the browser has no CORS issues.
     * URL: /hr/wfh/map?lat=8.97&lng=125.41&zoom=15
     */
    public function map(Request $request)
    {
        $lat  = (float) $request->query('lat', 0);
        $lng  = (float) $request->query('lng', 0);
        $zoom = min((int) $request->query('zoom', 15), 18);

        $mapUrl = "https://staticmap.openstreetmap.de/staticmap.php?"
            . http_build_query([
                'center'  => "{$lat},{$lng}",
                'zoom'    => $zoom,
                'size'    => '400x200',
                'markers' => "{$lat},{$lng},ol-marker",
            ]);

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(8)->get($mapUrl);

            if (! $response->successful()) {
                abort(502);
            }

            return response($response->body(), 200)
                ->header('Content-Type', $response->header('Content-Type') ?: 'image/png')
                ->header('Cache-Control', 'public, max-age=86400'); // cache 1 day
        } catch (\Throwable) {
            abort(502);
        }
    }

    // ─── Image Proxy ──────────────────────────────────────────────────────────

    /**
     * Serve a WFH photo through the server (authenticated users only).
     *
     * New format:  fileId starts with "s3." → base64url-encoded S3 key → read from S3
     * Legacy format: plain Drive file ID → proxy through Google Drive download
     */
    public function photo(string $fileId, GoogleDriveService $drive)
    {
        // Accept: s3.<base64url> (new) or alphanumeric/dash/underscore (legacy Drive)
        if (! preg_match('/^[a-zA-Z0-9_.=-]+$/', $fileId)) {
            abort(400);
        }

        // ── New: S3-backed photo ──────────────────────────────────────────────
        if (str_starts_with($fileId, 's3.')) {
            $padded  = strtr(substr($fileId, 3), '-_', '+/');
            $pad     = strlen($padded) % 4;
            if ($pad) $padded .= str_repeat('=', 4 - $pad);
            $s3Key   = base64_decode($padded, true);

            if (! $s3Key || ! \Illuminate\Support\Facades\Storage::disk('s3')->exists($s3Key)) {
                abort(404);
            }

            $contents = \Illuminate\Support\Facades\Storage::disk('s3')->get($s3Key);
            if (! $contents) abort(404);

            $ext  = strtolower(pathinfo($s3Key, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'png'  => 'image/png',
                'webp' => 'image/webp',
                'gif'  => 'image/gif',
                default => 'image/jpeg',
            };

            return response($contents, 200)
                ->header('Content-Type', $mime)
                ->header('Cache-Control', 'private, max-age=3600');
        }

        // ── Legacy: Google Drive photo ────────────────────────────────────────
        try {
            $file = $drive->download($fileId);
        } catch (\Throwable) {
            abort(404);
        }

        return response($file['content'], 200)
            ->header('Content-Type', $file['mimeType'])
            ->header('Cache-Control', 'private, max-age=3600');
    }

    // ─── Print: Time Logs ─────────────────────────────────────────────────────

    public function printTimeLogs(Request $request)
    {
        $user = Auth::user();
        $mode = $request->input('mode', 'monthly');

        $query = WFHAttendance::where('user_id', $user->id)->orderBy('date');

        $dateLabel = '';

        if ($mode === 'daily') {
            $date = $request->input('date', Carbon::today()->toDateString());
            $query->whereDate('date', $date);
            $dateLabel = Carbon::parse($date)->toFormattedDateString();
        } elseif ($mode === 'range') {
            $dateFrom = $request->input('date_from', Carbon::today()->toDateString());
            $dateTo   = $request->input('date_to',   Carbon::today()->toDateString());
            $query->whereBetween('date', [$dateFrom, $dateTo]);
            $dateLabel = Carbon::parse($dateFrom)->format('M d, Y')
                       . ' – '
                       . Carbon::parse($dateTo)->format('M d, Y');
        } else {
            $month = $request->input('month', Carbon::now()->format('Y-m'));
            [$year, $mon] = explode('-', $month);
            $query->whereYear('date', $year)->whereMonth('date', $mon);
            $dateLabel = Carbon::createFromDate($year, $mon, 1)->format('F Y');
        }

        $records  = $query->get();
        $division = $user->division_id
            ? \App\Models\Division::with('divisionchief')->find($user->division_id)
            : null;

        $isDivisionChief = $user->hasRole('DivisionChief');
        $ocdUser         = User::havingRole('OCD')->first();

        return Inertia::render('HumanResource/WFH/PrintTimeLogs', [
            'employee'        => $user->only('id', 'name', 'position', 'badge_id'),
            'records'         => $records->map(fn ($r) => array_merge($r->toArray(), [
                'date' => $r->getRawOriginal('date'),
            ])),
            'mode'            => $mode,
            'dateLabel'       => $dateLabel,
            'chiefName'       => $division?->divisionchief?->name,
            'chiefPosition'   => $division?->divisionchief?->position,
            'divisionName'    => $division?->division_name,
            'isDivisionChief' => $isDivisionChief,
            'approvedBy'      => $ocdUser ? [
                'name'     => $ocdUser->name,
                'position' => $ocdUser->position ?? 'Campus Director',
            ] : null,
        ]);
    }

    // ─── Print: Accomplishments ───────────────────────────────────────────────

    public function printAccomplishments(Request $request)
    {
        $user = Auth::user();
        $mode = $request->input('mode', 'monthly'); // daily | monthly | range

        $query = WFHAttendance::with('accomplishments')
            ->where('user_id', $user->id)
            ->orderBy('date');

        $dateLabel = '';

        if ($mode === 'daily') {
            $date = $request->input('date', Carbon::today()->toDateString());
            $query->whereDate('date', $date);
            $dateLabel = Carbon::parse($date)->toFormattedDateString();
        } elseif ($mode === 'range') {
            $dateFrom = $request->input('date_from', Carbon::today()->toDateString());
            $dateTo   = $request->input('date_to',   Carbon::today()->toDateString());
            $query->whereBetween('date', [$dateFrom, $dateTo]);
            $dateLabel = Carbon::parse($dateFrom)->format('M d, Y')
                       . ' – '
                       . Carbon::parse($dateTo)->format('M d, Y');
        } else {
            $month = $request->input('month', Carbon::now()->format('Y-m'));
            [$year, $mon] = explode('-', $month);
            $query->whereYear('date', $year)->whereMonth('date', $mon);
            $dateLabel = Carbon::createFromDate($year, $mon, 1)->format('F Y');
        }

        $records = $query->get()
            ->filter(fn ($r) => $r->accomplishments->isNotEmpty())
            ->values();

        // Resolve office & division directly to avoid conflict between
        // the legacy `office` varchar column and the `office()` relationship.
        $office   = $user->office_id
            ? \App\Models\Office::with('unitHeadUser')->find($user->office_id)
            : null;
        $division = $user->division_id
            ? \App\Models\Division::with('divisionchief')->find($user->division_id)
            : null;

        $ocdUser = User::havingRole('OCD')->first();

        // ── Signatory logic ──────────────────────────────────────────────────
        // OCD division or Division Chief → skip Monitored & Reviewed section
        $isOcdDivision   = $user->hasRole('OCD')
            || strtolower(trim($division?->acronym ?? '')) === 'ocd'
            || str_contains(strtolower($division?->division_name ?? ''), 'campus director');

        $isDivisionChief = $user->hasRole('DivisionChief');

        // CID unit head → immediate head is the Assistant CID Chief for Academic Affairs
        $isCidUnitHead   = strtolower(trim($division?->acronym ?? '')) === 'cid'
            && $user->hasRole('UnitHead');

        $immediateHead = null;
        if ($isCidUnitHead) {
            $sy = SchoolYear::where('is_current', true)->first();
            $desig = Designation::where('name', 'Assistant CID Chief for Academic Affairs')->first();

            if ($desig && $sy) {
                $assignedUser = LoadAssignment::where('designation_id', $desig->id)
                    ->where('school_year_id', $sy->id)
                    ->with('faculty:id,name,position')
                    ->first()
                    ?->faculty;

                if ($assignedUser) {
                    $immediateHead = [
                        'name'     => $assignedUser->name,
                        'position' => $assignedUser->position ?? 'Assistant CID Chief for Academic Affairs',
                    ];
                }
            }
        }

        return Inertia::render('HumanResource/WFH/PrintAccomplishments', [
            'employee'       => $user->only('id', 'name', 'position', 'badge_id'),
            'division'       => $division ? [
                'division_name' => $division->division_name,
                'chief_name'    => $division->divisionchief?->name,
            ] : null,
            'office'         => $office ? [
                'name'           => $office->name,
                'unit_head_name' => $office->unitHeadUser?->name,
            ] : null,
            'isOcdDivision'  => $isOcdDivision,
            'isDivisionChief' => $isDivisionChief,
            'immediateHead'  => $immediateHead,
            'approvedBy'     => $ocdUser ? [
                'name'     => $ocdUser->name,
                'position' => $ocdUser->position ?? 'Campus Director',
            ] : null,
            'records'    => $records->map(fn ($r) => array_merge($r->toArray(), [
                'date' => $r->getRawOriginal('date'),
            ])),
            'mode'       => $mode,
            'dateLabel'  => $dateLabel,
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function authorizeViewAttendance(WFHAttendance $attendance): void
    {
        $user = Auth::user();

        $isOwner        = $attendance->user_id === $user->id;
        $isDivisionChief = $user->hasRole('DivisionChief') &&
                           $attendance->user->division_id === $user->division_id;
        $isUnitHead     = $user->hasRole('UnitHead') &&
                           $attendance->user->office_id === $user->office_id;

        if (! ($isOwner || $isDivisionChief || $isUnitHead)) {
            abort(403);
        }
    }

    private function authorizeMonitor($user): void
    {
        if (! $user->hasAnyRole(['DivisionChief', 'OCD', 'HR', 'Administrator'])) {
            abort(403, 'Unauthorized to monitor WFH attendance.');
        }
    }
}
