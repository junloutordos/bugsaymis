<?php

namespace App\Http\Controllers\StudentAttendance\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentAttendance\ParentContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentApiController extends Controller
{
    /**
     * GET /api/mobile/students/{barcode}
     * Look up a student by their pisaysystemID barcode.
     * Used by the parent app before linking a child.
     */
    public function findByBarcode(string $barcode): JsonResponse
    {
        $student = DB::table('students')
            ->where('pisaysystemID', $barcode)
            ->first();

        if (! $student) {
            return response()->json(['message' => 'Student not found.'], 404);
        }

        return response()->json([
            'student' => $this->formatStudent($student),
        ]);
    }

    /**
     * POST /api/mobile/students/{barcode}/link
     * Link the authenticated parent contact to a student.
     */
    public function link(Request $request, string $barcode): JsonResponse
    {
        $student = DB::table('students')->where('pisaysystemID', $barcode)->first();

        if (! $student) {
            return response()->json(['message' => 'Student not found.'], 404);
        }

        $validated = $request->validate([
            'relationship' => ['nullable', 'string', 'max:50'],
        ]);

        $parentContact = ParentContact::where('user_id', $request->user()->id)->firstOrFail();

        // Upsert the pivot row
        DB::table('student_parent_contact')->upsert(
            [
                'student_id'        => $student->id,
                'parent_contact_id' => $parentContact->id,
                'relationship'      => $validated['relationship'] ?? 'guardian',
            ],
            ['student_id', 'parent_contact_id'],
            ['relationship'],
        );

        return response()->json([
            'message' => 'Student linked successfully.',
            'student' => $this->formatStudent($student),
        ]);
    }

    /**
     * DELETE /api/mobile/students/{barcode}/link
     * Unlink a student from the parent contact.
     */
    public function unlink(Request $request, string $barcode): JsonResponse
    {
        $student = DB::table('students')->where('pisaysystemID', $barcode)->first();

        if (! $student) {
            return response()->json(['message' => 'Student not found.'], 404);
        }

        $parentContact = ParentContact::where('user_id', $request->user()->id)->firstOrFail();

        DB::table('student_parent_contact')
            ->where('student_id', $student->id)
            ->where('parent_contact_id', $parentContact->id)
            ->delete();

        return response()->json(['message' => 'Student unlinked.']);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function formatStudent(object $student): array
    {
        return [
            'id'         => $student->id,
            'barcode'    => $student->pisaysystemID,
            'last_name'  => $student->lastname  ?? '',
            'first_name' => $student->firstname ?? '',
            'full_name'  => trim(($student->lastname ?? '') . ', ' . ($student->firstname ?? ''), ', '),
            'sex'        => $student->sex       ?? null,
            'email'      => $student->student_email ?? null,
        ];
    }
}
