<?php

namespace App\Exports\AMS\Sheets;

use App\Exports\AMS\Concerns\FormatsLikertOptions;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * One row per respondent. $fieldLabels maps a raw column name (e.g. "obj_1")
 * to its question text, in display order. Set $withActivityColumns to prepend
 * "Activity" / "Activity Type" columns for a bulk, multi-activity export.
 */
class RawFeedbackSheet implements FromArray, WithHeadings, WithStyles, WithTitle
{
    use FormatsLikertOptions;

    public function __construct(
        private Collection $rows,
        private array $fieldLabels,
        private bool $isTws = false,
        private bool $withActivityColumn = false,
        private string $sheetTitle = 'Raw Feedback',
    ) {}

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function headings(): array
    {
        $headings = [];
        if ($this->withActivityColumn) {
            $headings[] = 'Activity';
        }
        $headings[] = 'Respondent Type';
        $headings[] = 'Respondent Name';
        $headings[] = 'Sex';
        $headings[] = 'Submitted At';
        if ($this->isTws) {
            $headings[] = 'Position/Function';
        }
        foreach ($this->fieldLabels as $label) {
            $headings[] = $label;
        }
        $headings[] = 'Suggestions';
        $headings[] = 'Other Comments';

        return $headings;
    }

    public function array(): array
    {
        $userSexById    = $this->lookupSex(User::class, 'employee');
        $studentSexById = $this->lookupSex(Student::class, 'student');

        return $this->rows->map(function ($r) use ($userSexById, $studentSexById) {
            $row = [];
            if ($this->withActivityColumn) {
                $row[] = $r->activity?->title ?? "Activity #{$r->activity_id}";
            }
            $row[] = ucfirst($r->participant_type);
            $row[] = $r->evaluator_name ?? 'Anonymous';
            $row[] = $this->resolveSex($r, $userSexById, $studentSexById);
            $row[] = $r->created_at?->format('Y-m-d H:i');
            if ($this->isTws) {
                $row[] = $r->position_function;
            }
            foreach (array_keys($this->fieldLabels) as $field) {
                $row[] = $this->formatOption($r->$field);
            }
            $row[] = $r->suggestions;
            $row[] = $r->other_comments;

            return $row;
        })->values()->all();
    }

    /**
     * Batch-loads sex values for every registered participant of the given
     * type present in $this->rows, keyed by participant_id, to avoid N+1
     * queries when resolving each row.
     */
    private function lookupSex(string $modelClass, string $participantType): Collection
    {
        $ids = $this->rows
            ->where('participant_type', $participantType)
            ->pluck('participant_id')
            ->filter()
            ->unique();

        if ($ids->isEmpty()) {
            return collect();
        }

        return $modelClass::whereIn('id', $ids)->pluck('sex', 'id');
    }

    /**
     * Resolves the respondent's sex: registered employees/students pull from
     * their linked User/Student record; walk-ins use the sex captured
     * directly on the evaluation row (no linked identity to resolve from).
     */
    private function resolveSex($r, Collection $userSexById, Collection $studentSexById): ?string
    {
        $sex = match ($r->participant_type) {
            'employee' => $userSexById->get($r->participant_id),
            'student'  => $studentSexById->get($r->participant_id),
            default    => $r->sex,
        };

        return $sex ? ucfirst($sex) : null;
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}

