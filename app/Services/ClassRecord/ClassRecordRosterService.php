<?php

namespace App\Services\ClassRecord;

use App\Models\ClassRecord\ClassRecord;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClassRecordRosterService
{
    public function isCrossSection(ClassRecord $classRecord): bool
    {
        $classRecord->loadMissing(['subject', 'section']);

        return in_array($classRecord->subject?->subject_type, ['elective', 'science_core'], true)
            || str_starts_with((string) ($classRecord->section?->sectionname ?? ''), 'ELEC-')
            || str_starts_with((string) ($classRecord->section?->sectionname ?? ''), 'SCI-');
    }

    /**
     * @return int[]
     */
    public function gradeLevels(ClassRecord $classRecord): array
    {
        $classRecord->loadMissing(['subject', 'section']);

        $subjectType = $classRecord->subject?->subject_type;
        $subjectGrade = (int) ($classRecord->subject?->grade_level ?? 0);
        $sectionGrade = (int) ($classRecord->section?->levelid ?? 0);

        if ($subjectType === 'science_core') {
            $grade = in_array($subjectGrade, [11, 12], true) ? $subjectGrade : $sectionGrade;

            return in_array($grade, [11, 12], true) ? [$grade] : [];
        }

        if ($subjectType === 'elective') {
            return $subjectGrade === 0 ? [11, 12] : [$subjectGrade];
        }

        if ($this->isCrossSection($classRecord)) {
            return $sectionGrade > 0 ? [$sectionGrade] : [];
        }

        return [];
    }

    public function candidatesQuery(ClassRecord $classRecord): Builder
    {
        $query = DB::table('student_enrollments as se')
            ->join('students as s', 's.id', '=', 'se.student_id')
            ->leftJoin('sections as hs', 'hs.id', '=', 'se.section_id')
            ->where('se.school_year_id', $classRecord->school_year_id)
            ->where('se.status', 'enrolled');

        if ($this->isCrossSection($classRecord)) {
            $grades = $this->gradeLevels($classRecord);
            abort_if(empty($grades), 422, 'The cross-section subject does not have a valid Grade 11 or Grade 12 scope.');
            $query->whereIn('se.grade_level', $grades);
        } else {
            abort_unless($classRecord->section_id, 422, 'This regular class record is not linked to a section.');
            $query->where('se.section_id', $classRecord->section_id);
        }

        return $query;
    }

    public function candidates(ClassRecord $classRecord, ?string $search = null, int $limit = 30): Collection
    {
        return $this->candidatesQuery($classRecord)
            ->when(trim((string) $search) !== '', function (Builder $query) use ($search) {
                $term = '%'.trim((string) $search).'%';
                $query->where(function (Builder $studentQuery) use ($term) {
                    $studentQuery
                        ->where('s.lastname', 'like', $term)
                        ->orWhere('s.firstname', 'like', $term)
                        ->orWhere('s.lrn', 'like', $term)
                        ->orWhere('s.pisaysystemID', 'like', $term)
                        ->orWhereRaw("CONCAT_WS(' ', s.firstname, s.middlename, s.lastname) LIKE ?", [$term])
                        ->orWhereRaw("CONCAT_WS(', ', s.lastname, s.firstname) LIKE ?", [$term]);
                });
            })
            ->orderBy('s.lastname')
            ->orderBy('s.firstname')
            ->limit($limit)
            ->get([
                's.id as student_id',
                's.lastname',
                's.firstname',
                's.middlename',
                's.sex',
                's.lrn',
                's.pisaysystemID as pisay_system_id',
                'se.grade_level',
                'se.section_id as homeroom_section_id',
                'hs.sectionname as homeroom_section',
            ]);
    }

    /**
     * @param  int[]  $studentIds
     * @return int[]
     */
    public function eligibleStudentIds(ClassRecord $classRecord, array $studentIds): array
    {
        if (empty($studentIds)) {
            return [];
        }

        return $this->candidatesQuery($classRecord)
            ->whereIn('s.id', $studentIds)
            ->pluck('s.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
