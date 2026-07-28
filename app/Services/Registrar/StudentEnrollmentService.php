<?php

namespace App\Services\Registrar;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\Registrar\StudentEnrollment;
use Illuminate\Support\Facades\DB;

/**
 * Creates a brand-new `students` row (legacy MyISAM table) plus its
 * `student_enrollments` stub (section_id = null — assigned later via
 * "Assign by Grade List"). Shared by both admission paths: the public
 * self-service application approval (EnrollmentApplicationController) and
 * the Registrar/Administrator walk-in "Add Student" form
 * (EnrollmentController) — same student-record shape regardless of how the
 * student was admitted.
 */
class StudentEnrollmentService
{
    /**
     * @param  array<string, mixed>  $data  Same field shape as
     *   EnrollmentApplication (lastname, firstname, middlename, birthday,
     *   sex, birth_place, lrn, address_*, contact_no, email, father_*,
     *   mother_*, guardian_*, previous_school, previous_school_address).
     * @return array{student_id: int, enrollment: StudentEnrollment}
     */
    public function createStudentAndEnroll(
        array $data,
        string $pisaysId,
        int $gradeLevel,
        int $schoolYearId,
        string $enrollmentType,
        int $encodedBy,
    ): array {
        $exists = DB::table('students')->where('pisaysystemID', $pisaysId)->exists();
        abort_if($exists, 422, 'PISAY ID ' . $pisaysId . ' is already assigned to another student.');

        $newStudentId = null;
        $enrollment = null;

        DB::transaction(function () use ($data, $pisaysId, $gradeLevel, $schoolYearId, $enrollmentType, $encodedBy, &$newStudentId, &$enrollment) {
            // Batch year: grade 7 in SY 2026-2027 (start=2026) → graduates 2032
            $syStart = (int) substr(SchoolYear::find($schoolYearId)?->name ?? '2026-2027', 0, 4);
            $batch = $syStart + (12 - $gradeLevel) + 1;

            $studentId = DB::table('students')->insertGetId([
                'lastname'        => $data['lastname'],
                'firstname'       => $data['firstname'],
                'middlename'      => $data['middlename'] ?? '',
                'birthday'        => $data['birthday'] ?? '',
                'sex'             => $data['sex'] ?? '',
                'birthplace'      => $data['birth_place'] ?? '',
                'lrn'             => $data['lrn'] ?? '',
                'student_email'   => $data['email'] ?? '',
                'studentcontact'  => $data['contact_no'] ?? '',
                'province'        => $data['address_province'] ?? '',
                'municipal'       => $data['address_city'] ?? '',
                'barangay'        => $data['address_barangay'] ?? '',
                'region'          => $data['address_region'] ?? '',
                'zipcode'         => $data['address_zip'] ?? '',
                'houseno'         => implode(', ', array_filter([
                                         $data['address_house'] ?? '',
                                         $data['address_street'] ?? '',
                                         $data['address_subdivision'] ?? '',
                                     ])),
                'father_name'     => $data['father_name'] ?? '',
                'mother_name'     => $data['mother_name'] ?? '',
                'foccupation'     => $data['father_occupation'] ?? '',
                'moccupation'     => $data['mother_occupation'] ?? '',
                'contactno1'      => $data['father_contact'] ?? $data['guardian_contact'] ?? '',
                'contactperson'   => $data['guardian_name'] ?? '',
                'elemschool'      => $data['previous_school'] ?? '',
                'pisaysystemID'   => $pisaysId,
                'batch'           => (string) $batch,
                'status'          => 'Enrolled',
                'date_encoded'    => now()->format('Y-m-d'),
                'ethnic'          => '',
                'regiongraduated' => '',
                'malumnus'        => '',
                'falumnus'        => '',
                'schocat'         => '',
                'dormer1'         => '',
                'pkey'            => '',
                'mbirthday'       => '',
                'fbirthday'       => '',
                'meduc'           => '',
                'feduc'           => '',
                'mschool'         => '',
                'fschool'         => '',
                'parentsstatus'   => '',
                'schooladdress'   => '',
                'relation1'       => '',
                'contact_address1'      => '',
                'contact_ofc_address1'  => '',
                'contact_ofc_telno1'    => '',
                'contactperson2'        => '',
                'relation2'             => '',
                'contact_address2'      => '',
                'contactno2'            => '',
                'contact_ofc_address2'  => '',
                'contact_ofc_telno2'    => '',
                'socioeconomic'   => '',
                'schoolType1'     => '',
                'schoolType2'     => '',
            ]);

            $enrollment = StudentEnrollment::create([
                'student_id'      => $studentId,
                'school_year_id'  => $schoolYearId,
                'section_id'      => null,
                'grade_level'     => $gradeLevel,
                'enrollment_type' => $enrollmentType,
                'status'          => 'enrolled',
                'enrollment_date' => now()->toDateString(),
                'encoded_by'      => $encodedBy,
            ]);

            $newStudentId = $studentId;
        });

        return ['student_id' => $newStudentId, 'enrollment' => $enrollment];
    }
}
