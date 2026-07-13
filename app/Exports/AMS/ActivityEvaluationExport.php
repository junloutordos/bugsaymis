<?php

namespace App\Exports\AMS;

use App\Exports\AMS\Sheets\RawFeedbackSheet;
use App\Exports\AMS\Sheets\ResultsSheet;
use App\Exports\AMS\Sheets\SpeakerResultsSheet;
use App\Models\AMS\Activity;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Per-activity evaluation export: Results + Raw Feedback, plus a Speaker
 * Evaluations sheet for Training/Workshop/Seminar activities that have at
 * least one speaker with at least one submitted response.
 */
class ActivityEvaluationExport implements WithMultipleSheets
{
    public function __construct(private Activity $activity, private array $data) {}

    public function sheets(): array
    {
        $summary = $this->data['summary'];

        $sheets = [
            new ResultsSheet($summary['sections']),
            new RawFeedbackSheet($this->data['raw'], $this->data['field_labels'], $this->data['is_tws']),
        ];

        $speakers = collect($summary['speakers'] ?? [])->filter(fn ($s) => $s['count'] > 0)->values()->all();
        if (!empty($speakers)) {
            $sheets[] = new SpeakerResultsSheet($speakers);
        }

        return $sheets;
    }
}
