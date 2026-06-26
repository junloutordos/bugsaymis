<?php

namespace App\Services\Discipline;

use Illuminate\Support\Facades\DB;

/**
 * Resolves student display data (name, grade/section, sex, barcode) from the
 * legacy MyISAM `students` table, and provides an autocomplete search.
 *
 * Mirrors the column-detection approach used by GuidanceConsultationController
 * so it tolerates the legacy schema variations.
 */
class StudentResolver
{
    /** Search students by name or barcode for autocomplete. */
    public function search(string $term, int $limit = 10): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }
        $limit = max(1, min($limit, 20));

        $rows = DB::table('students')
            ->where(function ($q) use ($term) {
                $q->where('lastname', 'like', "%{$term}%")
                  ->orWhere('firstname', 'like', "%{$term}%")
                  ->orWhere('pisaysystemID', 'like', "%{$term}%")
                  ->orWhereRaw("CONCAT(lastname, ', ', firstname) LIKE ?", ["%{$term}%"]);
            })
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->limit($limit)
            ->get(['id', 'lastname', 'firstname', 'middlename', 'pisaysystemID', 'sex']);

        return $rows->map(fn ($s) => [
            'id'    => $s->id,
            'name'  => $this->formatName($s),
            'pisay' => $s->pisaysystemID ?: null,
            'sex'   => $s->sex ?: null,
        ])->all();
    }

    /** Full resolved profile for a single student id. */
    public function resolve(?int $studentId): array
    {
        $blank = [
            'id' => $studentId, 'name' => null, 'grade_section' => null,
            'sex' => null, 'barcode' => null,
        ];

        if (! $studentId) {
            return $blank;
        }

        $student = DB::table('students')->where('id', $studentId)->first();
        if (! $student) {
            return $blank;
        }

        return [
            'id'            => $student->id,
            'name'          => $this->formatName($student),
            'grade_section' => $this->resolveGradeSection($student),
            'sex'           => $student->sex ?? null,
            'barcode'       => $student->pisaysystemID ?? null,
        ];
    }

    public function formatName(object $student): string
    {
        $last  = $student->lastname  ?? null;
        $first = $student->firstname ?? null;
        $name  = trim(($last ? $last . ', ' : '') . ($first ?? ''));
        return $name !== '' ? $name : ('Student #' . ($student->id ?? '?'));
    }

    /** Resolve "Grade X SECTION" from the legacy section_students/sections tables. */
    public function resolveGradeSection(object $student): string
    {
        $grade = '';
        $sectionName = '';

        $sectionStudent = DB::table('section_students')
            ->where('studentid', $student->id)
            ->orderByDesc('id')
            ->first();

        if ($sectionStudent) {
            if (! empty($sectionStudent->levelid)) {
                $grade = 'Grade ' . $sectionStudent->levelid;
            }
            if (! empty($sectionStudent->sectionid)) {
                $section = DB::table('sections')->where('id', $sectionStudent->sectionid)->first();
                if ($section) {
                    foreach (['sectionname', 'section_name', 'name', 'section'] as $col) {
                        if (! empty($section->$col)) {
                            $sectionName = $section->$col;
                            break;
                        }
                    }
                }
            }
        }

        return trim($grade . ($sectionName ? ' ' . $sectionName : ''));
    }
}
