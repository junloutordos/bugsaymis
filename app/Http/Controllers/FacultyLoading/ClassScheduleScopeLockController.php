<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassScheduleScopeLock;
use App\Models\FacultyLoading\Section;
use App\Services\AuditLogger;
use App\Services\FacultyLoading\ClassScheduleApprovalService;
use App\Services\FacultyLoading\ClassScheduleScopeLockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassScheduleScopeLockController extends Controller
{
    public function __construct(private readonly ClassScheduleScopeLockService $locks) {}

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManager($request);
        $data = $request->validate([
            'academic_term_id' => 'required|integer|exists:academic_terms,id',
            'scope_type' => 'required|in:section,grade,whole',
            'section_id' => 'nullable|integer|exists:sections,id',
            'grade_level' => 'nullable|integer|between:7,12',
        ]);

        $term = AcademicTerm::findOrFail($data['academic_term_id']);
        if (ClassScheduleApprovalService::termIsLocked((int) $term->id)) {
            return response()->json(['message' => 'This term is already locked for OCD approval.'], 423);
        }
        $section = isset($data['section_id']) ? Section::findOrFail($data['section_id']) : null;
        if ($section && (int) $section->school_year_id !== (int) $term->school_year_id) {
            return response()->json(['message' => 'The section does not belong to the selected term school year.'], 422);
        }

        $lock = $this->locks->create(
            $term,
            $data['scope_type'],
            isset($data['grade_level']) ? (int) $data['grade_level'] : null,
            $section,
            $request->user(),
        );

        AuditLogger::log([
            'action' => 'lock_schedule_scope',
            'auditable_type' => ClassScheduleScopeLock::class,
            'auditable_id' => $lock->id,
            'new_values' => $lock->only(['academic_term_id', 'scope_type', 'scope_key', 'grade_level', 'section_id', 'locked_by', 'locked_at']),
        ]);

        return response()->json(['message' => 'Schedule scope locked.', 'lock' => $lock]);
    }

    public function destroy(Request $request, ClassScheduleScopeLock $scheduleScopeLock): JsonResponse
    {
        $this->authorizeManager($request);
        $snapshot = $scheduleScopeLock->only(['academic_term_id', 'scope_type', 'scope_key', 'grade_level', 'section_id', 'locked_by', 'locked_at']);
        $id = $scheduleScopeLock->id;
        $this->locks->delete($scheduleScopeLock, $request->user());

        AuditLogger::log([
            'action' => 'unlock_schedule_scope',
            'auditable_type' => ClassScheduleScopeLock::class,
            'auditable_id' => $id,
            'old_values' => $snapshot,
        ]);

        return response()->json(['message' => 'Schedule scope unlocked.']);
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user()->isSuperAdmin() || $request->user()->hasPermission('faculty_loading.manage'), 403);
    }
}
