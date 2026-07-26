<?php

namespace App\Http\Controllers;

use App\Exports\TeacherAttendanceExport;
use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use App\Services\CampusPresenceService;
use App\Services\TeacherAttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class TeacherAttendanceController extends Controller
{
    public function __construct(
        private TeacherAttendanceService $service,
        private CampusPresenceService $campusPresence,
    ) {}

    /**
     * NFC tap endpoint.
     * The NFC tag encodes /class-tap/{nfc_uuid} — tapping opens this URL and
     * auto-records the tap synchronously (physical proximity already proves
     * presence). nfc_uuid is never printed on the card and never reachable
     * via any other route, so there is no way to reach this ungated path
     * except by physically tapping the tag.
     */
    public function tap(string $uuid)
    {
        /** @var User $teacher */
        $teacher = Auth::user();

        $result = $this->service->tap($uuid, $teacher);

        return Inertia::render('RoomTap', $this->tapResponseData($result, $teacher));
    }

    /**
     * QR tap endpoint. The printed QR encodes /class-tap-qr/{qr_token} — a
     * completely separate secret from nfc_uuid, so there is no client-
     * controlled way to convert a QR request into the ungated NFC path.
     * Renders a confirmation page that resolves geolocation before anything
     * is recorded.
     */
    public function tapViaQr(string $qrToken)
    {
        $currentSyId = SchoolYear::where('is_current', true)->value('id');
        $classroom = Classroom::where('qr_token', $qrToken)->where('school_year_id', $currentSyId)->first();

        return Inertia::render('RoomTapConfirm', [
            'qrToken' => $qrToken,
            'classroom' => $classroom ? [
                'name' => $classroom->name,
                'code' => $classroom->code,
            ] : null,
        ]);
    }

    /**
     * Confirms a QR-channel tap after the browser resolves geolocation.
     * Requires the location/network gate to pass before any attendance is
     * recorded — a rejected gate writes no TeacherTapLog row at all.
     */
    public function confirmQrTap(Request $request, string $qrToken)
    {
        /** @var User $teacher */
        $teacher = Auth::user();

        $currentSyId = SchoolYear::where('is_current', true)->value('id');
        $classroom = Classroom::where('qr_token', $qrToken)->where('school_year_id', $currentSyId)->first();

        if (! $classroom) {
            return response()->json(['error' => true, 'gateStatus' => 'room_not_found', 'message' => 'Unknown room.'], 404);
        }

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
        ]);

        $ip = $this->clientIp($request);
        $gate = $this->campusPresence->resolveLocationGate(
            lat: (float) $data['latitude'],
            lng: (float) $data['longitude'],
            accuracy: isset($data['accuracy']) ? (float) $data['accuracy'] : null,
            ip: $ip,
        );

        if ($gate['status'] !== 'ok') {
            return response()->json([
                'error' => true,
                'gateStatus' => $gate['status'],
                'message' => match ($gate['status']) {
                    'outside' => "We couldn't confirm you're on campus. Try again, or tap your phone directly on the NFC card in the room.",
                    'coarse' => "Your device's location isn't precise enough. Move outdoors, or tap the physical NFC card instead.",
                    'no_permission' => 'Location access is required to confirm attendance this way — or tap the physical NFC card instead.',
                    default => "We couldn't confirm your location — tap the physical NFC card instead.",
                },
            ], 422);
        }

        $result = $this->service->tap(
            $classroom->nfc_uuid,
            $teacher,
            channel: 'qr',
            ip: $ip,
            lat: (float) $data['latitude'],
            lng: (float) $data['longitude'],
            locationStatus: $gate['status'],
            networkStatus: $gate['networkStatus'],
        );

        return response()->json($this->tapResponseData($result, $teacher));
    }

    /** Shared response shape for both the synchronous NFC tap and the QR confirm endpoint. */
    private function tapResponseData(array $result, User $teacher): array
    {
        return [
            'tapStatus' => $result['status'],
            'tappedAt' => $result['tap']?->tapped_at?->format('H:i'),
            'lateMinutes' => $result['tap']?->late_minutes ?? 0,
            'classroom' => $result['classroom'] ? [
                'name' => $result['classroom']->name,
                'code' => $result['classroom']->code,
            ] : null,
            'schedule' => $result['schedule'] ? [
                'subject' => $result['schedule']->subject?->name,
                'subjectCode' => $result['schedule']->subject?->code,
                'section' => $result['schedule']->section?->sectionname ?? '—',
                'startTime' => $result['schedule']->start_time,
                'endTime' => $result['schedule']->end_time,
            ] : null,
            'teacherName' => $teacher->name,
        ];
    }

    /**
     * Real client IP. Laravel's trustProxies('*') only trusts the immediate
     * peer (the ALB), so $request->ip() resolves to the Cloudflare edge IP —
     * useless for campus-network matching. CF-Connecting-IP carries the real
     * client and is trustworthy here because the ALB only accepts traffic
     * from Cloudflare.
     */
    private function clientIp(Request $request): ?string
    {
        return $request->header('CF-Connecting-IP') ?: $request->ip();
    }

    /**
     * Monitoring dashboard for AUH / CID Chief.
     */
    public function index(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $this->authorizeAccess($user);

        $headedUnit = $this->service->getHeadedAcademicUnit($user);
        $scopedUnitId = $headedUnit?->id; // null = full view (CID Chief / Admin)

        // Supervisors with class-attendance.view may override their unit scope
        if ($user->isSuperAdmin() || $user->hasPermission('class-attendance.view')) {
            $scopedUnitId = null;
        }

        $todaySchedules = $this->service->todaySchedules($scopedUnitId);

        $filters = $request->only(['date', 'teacher_id', 'classroom_id', 'status']);
        $history = $this->service->history($filters, $scopedUnitId);

        $classrooms = Classroom::select('id', 'name', 'code')
            ->where('is_available', true)
            ->orderBy('name')
            ->get();

        $teachers = User::employees()->select('id', 'name')
            ->where('status', '<>', 'inactive')
            ->when($scopedUnitId, function ($q) use ($scopedUnitId) {
                $q->whereHas('loadAssignments.subject', fn ($sq) => $sq->where('academic_unit_id', $scopedUnitId)
                );
            })
            ->orderBy('name')
            ->get();

        return Inertia::render('TeacherAttendance/Index', [
            'todaySchedules' => $todaySchedules->values(),
            'history' => $history,
            'classrooms' => $classrooms,
            'teachers' => $teachers,
            'filters' => $filters,
            'scopedUnit' => $headedUnit ? ['id' => $headedUnit->id, 'name' => $headedUnit->name] : null,
        ]);
    }

    /**
     * Export tap log history to Excel.
     */
    public function export(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $this->authorizeAccess($user);

        $headedUnit = $this->service->getHeadedAcademicUnit($user);
        $scopedUnitId = ($user->isSuperAdmin() || $user->hasPermission('class-attendance.view'))
            ? null
            : $headedUnit?->id;

        $filters = $request->only(['date', 'teacher_id', 'classroom_id', 'status']);
        $rows = $this->service->history($filters, $scopedUnitId)->items();

        $date = $filters['date'] ?? today()->toDateString();
        $filename = "teacher-attendance-{$date}.xlsx";

        return Excel::download(new TeacherAttendanceExport($rows), $filename);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function authorizeAccess(User $user): void
    {
        if ($user->isSuperAdmin() || $user->hasPermission('class-attendance.view')) {
            return;
        }

        // AUH: head of any active academic unit
        if ($this->service->getHeadedAcademicUnit($user)) {
            return;
        }

        abort(403, 'Unauthorized');
    }
}
