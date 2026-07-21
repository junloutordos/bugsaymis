<?php

namespace App\Http\Controllers;

use App\Models\FacultyLoading\Classroom;
use App\Models\User;
use App\Services\TeacherAttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class TeacherAttendanceController extends Controller
{
    public function __construct(private TeacherAttendanceService $service) {}

    /**
     * NFC tap endpoint.
     * The NFC tag encodes /class-tap/{nfc_uuid} — tapping opens this URL.
     * On load: auto-record tap, then show result page.
     */
    public function tap(string $uuid)
    {
        /** @var User $teacher */
        $teacher = Auth::user();

        $result = $this->service->tap($uuid, $teacher);

        return Inertia::render('RoomTap', [
            'tapStatus'   => $result['status'],
            'tappedAt'    => $result['tap']?->tapped_at?->format('H:i'),
            'lateMinutes' => $result['tap']?->late_minutes ?? 0,
            'classroom'   => $result['classroom'] ? [
                'name' => $result['classroom']->name,
                'code' => $result['classroom']->code,
            ] : null,
            'schedule'    => $result['schedule'] ? [
                'subject'    => $result['schedule']->subject?->name,
                'subjectCode'=> $result['schedule']->subject?->code,
                'section'    => $result['schedule']->section?->name ?? '—',
                'startTime'  => $result['schedule']->start_time,
                'endTime'    => $result['schedule']->end_time,
            ] : null,
            'teacherName' => $teacher->name,
        ]);
    }

    /**
     * Monitoring dashboard for AUH / CID Chief.
     */
    public function index(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $this->authorizeAccess($user);

        $headedUnit    = $this->service->getHeadedAcademicUnit($user);
        $scopedUnitId  = $headedUnit?->id; // null = full view (CID Chief / Admin)

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
                $q->whereHas('loadAssignments.subject', fn ($sq) =>
                    $sq->where('academic_unit_id', $scopedUnitId)
                );
            })
            ->orderBy('name')
            ->get();

        return Inertia::render('TeacherAttendance/Index', [
            'todaySchedules' => $todaySchedules->values(),
            'history'        => $history,
            'classrooms'     => $classrooms,
            'teachers'       => $teachers,
            'filters'        => $filters,
            'scopedUnit'     => $headedUnit ? ['id' => $headedUnit->id, 'name' => $headedUnit->name] : null,
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

        $headedUnit   = $this->service->getHeadedAcademicUnit($user);
        $scopedUnitId = ($user->isSuperAdmin() || $user->hasPermission('class-attendance.view'))
            ? null
            : $headedUnit?->id;

        $filters = $request->only(['date', 'teacher_id', 'classroom_id', 'status']);
        $rows    = $this->service->history($filters, $scopedUnitId)->items();

        $date     = $filters['date'] ?? today()->toDateString();
        $filename = "teacher-attendance-{$date}.xlsx";

        return Excel::download(new \App\Exports\TeacherAttendanceExport($rows), $filename);
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
