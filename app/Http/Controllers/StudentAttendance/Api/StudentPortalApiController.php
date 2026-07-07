<?php

namespace App\Http\Controllers\StudentAttendance\Api;

use App\Http\Controllers\Controller;
use App\Models\LostFound\LostFoundItem;
use App\Models\Student;
use App\Models\StudentMobileLink;
use App\Services\StudentClearance\StudentClearancePdfService;
use App\Services\StudentClearance\StudentClearanceService;
use App\Services\StudentPortal\GradeLevel;
use App\Services\StudentPortal\GuidanceProfileService;
use App\Services\StudentPortal\MedicalRecordsService;
use App\Services\StudentPortal\LostFoundPortalService;
use App\Services\StudentPortal\PortalDashboardService;
use App\Services\StudentPortal\RhPortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Student-portal features (guidance forms, medical records, RH services,
 * clearance) exposed to the AtlasGo mobile app. Mirrors the web
 * StudentPortal controllers via the shared App\Services\StudentPortal
 * services; the student is resolved from the Sanctum user's mobile link
 * instead of the web portal session.
 */
class StudentPortalApiController extends Controller
{
    /**
     * Resolve the students-table row for the authenticated mobile user.
     */
    private function resolveStudent(Request $request): ?object
    {
        $link = StudentMobileLink::where('user_id', $request->user()->id)->first();

        if (! $link) {
            return null;
        }

        return DB::table('students')->where('id', $link->student_id)->first();
    }

    private function notLinked(): JsonResponse
    {
        return response()->json(['message' => 'Student account not fully set up.'], 404);
    }

    private function basicStudent(object $student): array
    {
        return [
            'id'            => $student->id,
            'pisaysystemID' => $student->pisaysystemID,
            'name'          => trim("{$student->lastname}, {$student->firstname}"),
            'sex'           => $student->sex ?? null,
            'batch'         => $student->batch ?? null,
            'province'      => $student->province ?? null,
        ];
    }

    // ── Dashboard ─────────────────────────────────────────────────────────────

    /**
     * GET /api/mobile/student/portal/dashboard
     */
    public function dashboard(Request $request, PortalDashboardService $service): JsonResponse
    {
        $student = $this->resolveStudent($request);
        if (! $student) {
            return $this->notLinked();
        }

        $overview = $service->overview($student->pisaysystemID, GradeLevel::compute((int) $student->batch));
        $overview['student'] = $this->basicStudent($student);

        return response()->json($overview);
    }

    // ── Guidance profile forms ────────────────────────────────────────────────

    /**
     * GET /api/mobile/student/portal/profile
     */
    public function profile(Request $request, GuidanceProfileService $service): JsonResponse
    {
        $student = $this->resolveStudent($request);
        if (! $student) {
            return $this->notLinked();
        }

        $data = $service->showData($student->pisaysystemID, GradeLevel::compute((int) $student->batch));
        $data['student'] = $this->basicStudent($student);

        return response()->json($data);
    }

    /**
     * POST /api/mobile/student/portal/profile/{section}
     */
    public function saveProfileSection(Request $request, GuidanceProfileService $service, string $section): JsonResponse
    {
        $student = $this->resolveStudent($request);
        if (! $student) {
            return $this->notLinked();
        }

        $service->saveSection($request, $section, $student->pisaysystemID, GradeLevel::compute((int) $student->batch));

        return response()->json(['message' => 'Section saved successfully.']);
    }

    // ── Medical records ───────────────────────────────────────────────────────

    /**
     * GET /api/mobile/student/portal/medical
     */
    public function medical(Request $request, MedicalRecordsService $service): JsonResponse
    {
        $student = $this->resolveStudent($request);
        if (! $student) {
            return $this->notLinked();
        }

        return response()->json($service->showData($student->pisaysystemID));
    }

    /**
     * POST /api/mobile/student/portal/medical/{section}
     */
    public function saveMedicalSection(Request $request, MedicalRecordsService $service, string $section): JsonResponse
    {
        $student = $this->resolveStudent($request);
        if (! $student) {
            return $this->notLinked();
        }

        $service->saveSection($request, $section, $student->pisaysystemID, GradeLevel::compute((int) $student->batch));

        return response()->json(['message' => 'Records saved successfully.']);
    }

    // ── Residence Hall ────────────────────────────────────────────────────────

    /**
     * GET /api/mobile/student/portal/rh-application
     */
    public function rhApplication(Request $request, RhPortalService $service): JsonResponse
    {
        $student = $this->resolveStudent($request);
        if (! $student) {
            return $this->notLinked();
        }

        $data = $service->applicationData($student->pisaysystemID);
        $data['student'] = $this->basicStudent($student);

        return response()->json($data);
    }

    /**
     * POST /api/mobile/student/portal/rh-application
     */
    public function storeRhApplication(Request $request, RhPortalService $service): JsonResponse
    {
        $student = $this->resolveStudent($request);
        if (! $student) {
            return $this->notLinked();
        }

        $error = $service->storeApplication($request, $student->pisaysystemID);

        if ($error) {
            return response()->json(['message' => $error], 422);
        }

        return response()->json(['message' => 'Application submitted. The Residence Hall Committee will review your application.'], 201);
    }

    /**
     * GET /api/mobile/student/portal/leave-passes
     */
    public function leavePasses(Request $request, RhPortalService $service): JsonResponse
    {
        $student = $this->resolveStudent($request);
        if (! $student) {
            return $this->notLinked();
        }

        return response()->json($service->leavePassData($student->pisaysystemID));
    }

    /**
     * POST /api/mobile/student/portal/leave-passes
     */
    public function storeLeavePass(Request $request, RhPortalService $service): JsonResponse
    {
        $student = $this->resolveStudent($request);
        if (! $student) {
            return $this->notLinked();
        }

        $error = $service->storeLeavePass($request, $student->pisaysystemID);

        if ($error) {
            return response()->json(['message' => $error], 422);
        }

        return response()->json(['message' => 'Leave pass filed. Await approval from your Dorm Manager.'], 201);
    }

    // ── Clearance ─────────────────────────────────────────────────────────────

    /**
     * GET /api/mobile/student/portal/clearance
     */
    public function clearance(Request $request, StudentClearanceService $service): JsonResponse
    {
        $studentRow = $this->resolveStudent($request);
        if (! $studentRow) {
            return $this->notLinked();
        }

        $student = Student::find($studentRow->id);
        $period = $service->activeOrLatestPeriod();

        $clearance = $period ? $service->clearanceForStudent($period, $student->id) : null;

        return response()->json([
            'student' => [
                'id'        => $student->id,
                'full_name' => $student->full_name,
                'pisays_id' => $student->pisaysystemID,
            ],
            'period' => $period ? [
                'id'       => $period->id,
                'title'    => $period->title,
                'status'   => $period->status,
                'sy_name'  => $period->schoolYear?->name,
                'opens_at' => $period->opens_at?->format('Y-m-d'),
                'closes_at'=> $period->closes_at?->format('Y-m-d'),
            ] : null,
            'clearance' => $clearance ? $service->serializeForStudent($clearance) : null,
        ]);
    }

    /**
     * GET /api/mobile/student/portal/clearance/pdf
     */
    public function clearancePdf(Request $request, StudentClearanceService $service, StudentClearancePdfService $pdfService): SymfonyResponse
    {
        $studentRow = $this->resolveStudent($request);
        if (! $studentRow) {
            return $this->notLinked();
        }

        $student = Student::findOrFail($studentRow->id);
        $period = $service->activeOrLatestPeriod();

        abort_unless($period, 404);

        $clearance = \App\Models\StudentClearance\StudentClearance::where('student_clearance_period_id', $period->id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        $pdf = $pdfService->generate($clearance);
        $filename = 'Year_End_Clearance_'.str_replace([',', ' '], ['', '_'], $student->full_name).'.pdf';

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
            'Cache-Control'       => 'private, no-cache',
        ]);
    }

    // ── Lost & Found ──────────────────────────────────────────────────────────

    /**
     * GET /api/mobile/student/portal/lost-found
     * Found-items board + the student's reports + honesty points.
     */
    public function lostFound(Request $request, LostFoundPortalService $service): JsonResponse
    {
        $student = $this->resolveStudent($request);
        if (! $student) {
            return $this->notLinked();
        }

        $data = $service->screenData($student);
        $data['student'] = $this->basicStudent($student);

        return response()->json($data);
    }

    /**
     * POST /api/mobile/student/portal/lost-found
     * Report a lost or found item (photo as base64 JSON — WAF-safe).
     */
    public function storeLostFoundReport(Request $request, LostFoundPortalService $service): JsonResponse
    {
        $student = $this->resolveStudent($request);
        if (! $student) {
            return $this->notLinked();
        }

        $item = $service->storeReport($request, $student);

        return response()->json([
            'message' => $item['type'] === 'found'
                ? 'Found item reported — please turn it over to the GSU office to earn honesty points.'
                : 'Lost item reported. Check back for matches with items turned over to GSU.',
            'item' => $item,
        ], 201);
    }

    /**
     * GET /api/mobile/student/portal/lost-found/photo/{item}
     */
    public function lostFoundPhoto(Request $request, LostFoundPortalService $service, LostFoundItem $item): SymfonyResponse
    {
        $student = $this->resolveStudent($request);
        abort_unless($student, 404);

        return $service->photo($student, $item);
    }
}
