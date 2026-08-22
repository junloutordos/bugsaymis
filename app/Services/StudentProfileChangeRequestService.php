<?php

namespace App\Services;

use App\Models\StudentProfileChangeRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class StudentProfileChangeRequestService
{
    /**
     * Personal/contact-info columns a student may self-request changes to.
     * Deliberately excludes identity, academic, and encoding columns — see
     * the design spec's non-goals and StudentProfileService::WRITABLE_COLUMNS,
     * of which this is a strict subset.
     */
    public const EDITABLE_FIELDS = [
        'studentcontact', 'contactno1', 'contactno2', 'contactperson', 'contactperson2',
        'relation1', 'relation2', 'contact_address1', 'contact_address2',
        'contact_ofc_address1', 'contact_ofc_address2', 'contact_ofc_telno1', 'contact_ofc_telno2',
        'bloodtype', 'religion', 'ethnic', 'nationality', 'student_email',
        'houseno', 'barangay', 'municipal', 'district', 'province', 'zipcode', 'homeaddresstype',
        'mcpno', 'fcpno', 'memailaddress', 'femailaddress', 'moccupation', 'foccupation',
    ];

    public function __construct(private readonly StudentProfileService $profileService) {}

    public function currentValues(int $studentId): array
    {
        $row = DB::table('students')->where('id', $studentId)->first(self::EDITABLE_FIELDS);

        return $row ? (array) $row : [];
    }

    public function pendingRequest(int $studentId): ?StudentProfileChangeRequest
    {
        return StudentProfileChangeRequest::where('student_id', $studentId)
            ->where('status', 'pending')
            ->first();
    }

    /**
     * @return array{ok: bool, message: ?string, request: ?StudentProfileChangeRequest}
     */
    public function submit(int $studentId, array $changes): array
    {
        if ($this->pendingRequest($studentId)) {
            return ['ok' => false, 'message' => 'You already have an update awaiting review. Please wait for a decision before submitting another.', 'request' => null];
        }

        $disallowed = array_diff(array_keys($changes), self::EDITABLE_FIELDS);
        if ($disallowed !== []) {
            return ['ok' => false, 'message' => 'One or more fields cannot be self-updated.', 'request' => null];
        }

        if ($changes === []) {
            return ['ok' => false, 'message' => 'No changes were submitted.', 'request' => null];
        }

        $columns = collect(DB::select('SHOW COLUMNS FROM students'));
        $rules = collect($this->profileService->validationRules($columns))
            ->only(self::EDITABLE_FIELDS)
            ->all();

        $validator = Validator::make($changes, $rules);
        if ($validator->fails()) {
            return ['ok' => false, 'message' => $validator->errors()->first(), 'request' => null];
        }

        $request = StudentProfileChangeRequest::create([
            'student_id' => $studentId,
            'requested_changes' => $changes,
            'status' => 'pending',
        ]);

        return ['ok' => true, 'message' => null, 'request' => $request];
    }

    public function approve(StudentProfileChangeRequest $request, User $reviewer): void
    {
        $columns = collect(DB::select('SHOW COLUMNS FROM students'));
        $changes = $this->profileService->normalizeForStorage($request->requested_changes, $columns);

        if (Schema::hasColumn('students', 'date_updated')) {
            $changes['date_updated'] = now()->format('Y-m-d');
        }

        DB::table('students')->where('id', $request->student_id)->update($changes);

        $request->update([
            'status' => 'approved',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);
    }

    public function reject(StudentProfileChangeRequest $request, User $reviewer, string $notes): void
    {
        $request->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);
    }
}
