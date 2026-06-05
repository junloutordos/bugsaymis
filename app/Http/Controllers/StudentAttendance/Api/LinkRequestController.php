<?php

namespace App\Http\Controllers\StudentAttendance\Api;

use App\Http\Controllers\Controller;
use App\Mail\StudentLinkRequestMail;
use App\Models\Student;
use App\Models\StudentAttendance\ParentContact;
use App\Models\StudentLinkRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class LinkRequestController extends Controller
{
    /** POST /api/mobile/link-requests */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id'   => ['required', 'string'],
            'relationship' => ['sometimes', 'string', 'in:mother,father,guardian'],
        ]);

        $parentContact = ParentContact::where('user_id', $request->user()->id)->first();

        if (! $parentContact) {
            return response()->json(['message' => 'Parent account not found.'], 404);
        }

        // Find student internally — NEVER return student info to the app
        $student = Student::where('pisaysystemID', $validated['student_id'])->first();

        // Uniform response regardless of whether student exists (prevent enumeration)
        $genericMessage = 'If that student ID is registered, a confirmation email has been sent. The student must approve before you can track their attendance.';

        if (! $student || empty($student->student_email)) {
            return response()->json(['message' => $genericMessage]);
        }

        // Already confirmed-linked — silently return same message
        if ($parentContact->students()->where('students.id', $student->id)->exists()) {
            return response()->json(['message' => $genericMessage]);
        }

        // Cancel any existing pending request for this pair
        StudentLinkRequest::where('parent_contact_id', $parentContact->id)
            ->where('student_id', $student->id)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        $linkRequest = StudentLinkRequest::create([
            'parent_contact_id' => $parentContact->id,
            'student_id'        => $student->id,
            'relationship'      => $validated['relationship'] ?? 'guardian',
            'token'             => Str::random(64),
            'status'            => 'pending',
            'expires_at'        => now()->addHours(72),
        ]);

        Mail::to($student->student_email)
            ->send(new StudentLinkRequestMail($linkRequest, $request->user()->name));

        return response()->json(['message' => $genericMessage]);
    }

    /** GET /api/mobile/link-requests */
    public function index(Request $request): JsonResponse
    {
        $parentContact = ParentContact::where('user_id', $request->user()->id)->first();

        if (! $parentContact) {
            return response()->json(['data' => []]);
        }

        $requests = StudentLinkRequest::where('parent_contact_id', $parentContact->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->latest()
            ->with('student')
            ->get()
            ->map(fn ($r) => [
                'id'               => $r->id,
                'masked_student_id' => $r->maskedStudentId(),
                'relationship'     => $r->relationship,
                'status'           => $r->status,
                'created_at'       => $r->created_at->toISOString(),
                'expires_at'       => $r->expires_at->toISOString(),
            ]);

        return response()->json(['data' => $requests]);
    }

    /** DELETE /api/mobile/link-requests/{id} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $parentContact = ParentContact::where('user_id', $request->user()->id)->first();

        $linkRequest = StudentLinkRequest::where('id', $id)
            ->where('parent_contact_id', $parentContact?->id)
            ->where('status', 'pending')
            ->first();

        if (! $linkRequest) {
            return response()->json(['message' => 'Request not found.'], 404);
        }

        $linkRequest->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Request cancelled.']);
    }
}
