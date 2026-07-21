<?php

namespace Tests\Unit;

use App\Services\StudentProfileService;
use Tests\TestCase;

class StudentProfileServiceTest extends TestCase
{
    private StudentProfileService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StudentProfileService;
    }

    public function test_real_legacy_student_fields_are_writable(): void
    {
        $available = [
            'id', 'firstname', 'studentcontact', 'contactperson', 'municipal',
            'student_email', 'mother_name', 'contactperson2', 'img', 'pkey',
        ];

        $this->assertSame([
            'firstname', 'studentcontact', 'contactperson', 'mother_name',
            'municipal', 'student_email', 'contactperson2',
        ], $this->service->writableColumns($available));
    }

    public function test_system_media_and_identifier_fields_are_not_writable(): void
    {
        $available = [
            'id', 'img', 'signature', 'fathersign', 'mothersign', 'pkey',
            'pisaysystemID', 'pisaysystemID2', 'encodedby', 'date_encoded',
            'date_updated', 'date_updated1',
        ];

        $this->assertSame([], $this->service->writableColumns($available));
    }

    public function test_validation_rules_follow_legacy_column_types_and_lengths(): void
    {
        $columns = collect([
            (object) ['Field' => 'student_email', 'Type' => 'varchar(255)', 'Null' => 'YES'],
            (object) ['Field' => 'contactno2', 'Type' => 'varchar(15)', 'Null' => 'NO'],
            (object) ['Field' => 'math', 'Type' => 'int', 'Null' => 'YES'],
            (object) ['Field' => 'img', 'Type' => 'varchar(200)', 'Null' => 'YES'],
        ]);

        $rules = $this->service->validationRules($columns);

        $this->assertSame(['sometimes', 'nullable', 'string', 'max:255', 'email:rfc'], $rules['student_email']);
        $this->assertSame(['sometimes', 'nullable', 'string', 'max:15'], $rules['contactno2']);
        $this->assertSame(['sometimes', 'nullable', 'integer'], $rules['math']);
        $this->assertArrayNotHasKey('img', $rules);
    }

    public function test_blank_values_are_safe_for_legacy_not_null_columns(): void
    {
        $columns = collect([
            (object) ['Field' => 'ethnic', 'Null' => 'NO'],
            (object) ['Field' => 'student_email', 'Null' => 'YES'],
        ]);

        $result = $this->service->normalizeForStorage([
            'ethnic' => null,
            'student_email' => null,
        ], $columns);

        $this->assertSame('', $result['ethnic']);
        $this->assertNull($result['student_email']);
    }

    public function test_only_actual_changes_are_returned_for_update(): void
    {
        $student = (object) [
            'firstname' => 'Juan',
            'contactperson' => 'Maria Dela Cruz',
            'student_email' => null,
        ];

        $changes = $this->service->changedValues($student, [
            'firstname' => 'Juan',
            'contactperson' => 'Maria Santos',
            'student_email' => null,
        ]);

        $this->assertSame(['contactperson' => 'Maria Santos'], $changes);
    }
}
