<?php

namespace App\Http\Controllers\StudentAttendance;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentAttendance\GuardActivityLogger;
use App\Services\StudentAttendance\GuardDirectoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GuardDirectoryController extends Controller
{
    public function __construct(
        private GuardDirectoryService $directory,
        private GuardActivityLogger $activityLogger,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:student,employee'],
            'q' => ['required', 'string', 'min:3', 'max:80'],
        ]);

        $results = $validated['type'] === 'student'
            ? $this->directory->searchStudents($validated['q'])
            : $this->directory->searchEmployees($validated['q']);

        $this->activityLogger->log($request, 'guard.directory.search', metadata: [
            'resource_type' => $validated['type'],
            'query_hash' => hash('sha256', mb_strtolower(trim($validated['q']))),
            'query_length' => mb_strlen(trim($validated['q'])),
            'result_count' => $results->count(),
        ]);

        return $this->privateJson(['data' => $results]);
    }

    public function student(Request $request, int $student): JsonResponse
    {
        $detail = $this->directory->studentDetail($student);
        abort_if(! $detail, 404);

        $this->activityLogger->log($request, 'guard.student.view', Student::class, $student);

        return $this->privateJson(['data' => $detail]);
    }

    public function revealContacts(Request $request, int $student): JsonResponse
    {
        $contacts = $this->directory->revealedContacts($student);
        abort_if($contacts === null, 404);

        $this->activityLogger->log($request, 'guard.student.contacts_revealed', Student::class, $student, [
            'contact_count' => $contacts->count(),
        ]);

        return $this->privateJson(['data' => $contacts]);
    }

    public function employee(Request $request, int $employee): JsonResponse
    {
        $detail = $this->directory->employeeDetail($employee);
        abort_if(! $detail, 404);

        $this->activityLogger->log($request, 'guard.employee.view', User::class, $employee);

        return $this->privateJson(['data' => $detail]);
    }

    public function employeePhoto(int $employee)
    {
        $path = User::query()
            ->employees()
            ->where(fn ($query) => $query->whereNull('status')->orWhere('status', '<>', 'inactive'))
            ->whereKey($employee)
            ->value('profile_picture');

        abort_if(! $path || ! Storage::disk('s3')->exists($path), 404);

        return response(Storage::disk('s3')->get($path), 200, [
            'Content-Type' => Storage::disk('s3')->mimeType($path) ?: 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function privateJson(array $payload): JsonResponse
    {
        return response()->json($payload)->withHeaders([
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
