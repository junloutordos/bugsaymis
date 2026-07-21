<?php

namespace App\Services;

use Illuminate\Support\Collection;

class StudentProfileService
{
    /**
     * Legacy students-table fields administrators may edit.
     * System identifiers, media, signatures, and audit fields stay protected.
     */
    public const WRITABLE_COLUMNS = [
        'lastname', 'firstname', 'middlename', 'birthday', 'sex',
        'studentcontact', 'contactperson', 'contactno1', 'remarks', 'entrytype',
        'mother_name', 'father_name', 'province', 'district', 'municipal',
        'houseno', 'barangay', 'status', 'bloodtype', 'batch', 'nickname',
        'birthplace', 'religion', 'ethnic', 'nationality', 'region',
        'dateofgraduation', 'noofgraduates', 'average', 'honors',
        'motheraddress', 'fatheraddress', 'mcitizenship', 'fcitizenship',
        'mcpno', 'fcpno', 'memailaddress', 'femailaddress', 'moccupation',
        'foccupation', 'mcompany', 'fcompany', 'mofcaddress', 'fofcaddress',
        'mofctelno', 'fofctelno', 'dormer', 'studentcitizenship',
        'homeaddresstype', 'schooltype', 'elemschool', 'lrn', 'student_email',
        'math', 'verbal', 'science', 'abtract', 'regiongraduated', 'malumnus',
        'falumnus', 'schocat', 'dormer1', 'mbirthday', 'fbirthday', 'meduc',
        'feduc', 'mschool', 'fschool', 'parentsstatus', 'zipcode',
        'schooladdress', 'relation1', 'contact_address1', 'contact_ofc_address1',
        'contact_ofc_telno1', 'contactperson2', 'relation2', 'contact_address2',
        'contactno2', 'contact_ofc_address2', 'contact_ofc_telno2',
        'socioeconomic', 'schoolType1', 'schoolType2',
    ];

    private const INTEGER_COLUMNS = [
        'noofgraduates', 'math', 'verbal', 'science', 'abtract',
    ];

    private const NUMERIC_COLUMNS = ['average'];

    private const EMAIL_COLUMNS = [
        'student_email', 'memailaddress', 'femailaddress',
    ];

    /** @return array<int, string> */
    public function writableColumns(array $schemaColumns): array
    {
        return array_values(array_intersect(self::WRITABLE_COLUMNS, $schemaColumns));
    }

    /**
     * Build validation rules from the real legacy column definitions so the
     * request cannot exceed production varchar limits.
     */
    public function validationRules(Collection $columns): array
    {
        $writable = array_flip(self::WRITABLE_COLUMNS);
        $rules = [];

        foreach ($columns as $column) {
            $field = $column->Field;
            if (! isset($writable[$field])) {
                continue;
            }

            if (in_array($field, self::INTEGER_COLUMNS, true)) {
                $rules[$field] = ['sometimes', 'nullable', 'integer'];

                continue;
            }

            if (in_array($field, self::NUMERIC_COLUMNS, true)) {
                $rules[$field] = ['sometimes', 'nullable', 'numeric'];

                continue;
            }

            $max = preg_match('/varchar\((\d+)\)/i', $column->Type, $matches)
                ? (int) $matches[1]
                : 1000;

            $rules[$field] = ['sometimes', 'nullable', 'string', "max:{$max}"];
            if (in_array($field, self::EMAIL_COLUMNS, true)) {
                $rules[$field][] = 'email:rfc';
            }
        }

        return $rules;
    }

    /**
     * Laravel converts empty strings to null before validation. Restore an
     * intentional blank for legacy NOT NULL varchar columns.
     */
    public function normalizeForStorage(array $data, Collection $columns): array
    {
        $notNullable = $columns
            ->where('Null', 'NO')
            ->pluck('Field')
            ->flip();

        foreach ($data as $field => $value) {
            if ($value === null && $notNullable->has($field)) {
                $data[$field] = '';
            }
        }

        return $data;
    }

    public function changedValues(object|array $student, array $data): array
    {
        $current = (array) $student;

        return collect($data)->filter(
            fn ($value, $field) => (string) ($current[$field] ?? '') !== (string) ($value ?? '')
        )->all();
    }
}
