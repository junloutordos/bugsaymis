<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\ResearchGroup;

class ResearchGroupResolver
{
    /**
     * Find or create the ResearchGroup for (term, grade, normalized title).
     * research_type is only set on create — it does not overwrite an
     * existing group's type on a later resolve (a group's canonical type
     * is set by whoever created it first).
     */
    public function resolve(int $academicTermId, int $gradeLevel, string $title, ?string $researchType = null): ResearchGroup
    {
        $normalized = trim($title);

        $existing = ResearchGroup::where('academic_term_id', $academicTermId)
            ->where('grade_level', $gradeLevel)
            ->whereRaw('LOWER(title) = ?', [mb_strtolower($normalized)])
            ->first();

        if ($existing) {
            return $existing;
        }

        return ResearchGroup::create([
            'academic_term_id' => $academicTermId,
            'grade_level'      => $gradeLevel,
            'title'            => $normalized,
            'research_type'    => $researchType,
        ]);
    }
}
