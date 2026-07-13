<?php

namespace App\Exports\AMS;

use App\Exports\AMS\Sheets\MonitorSummarySheet;
use App\Exports\AMS\Sheets\RawFeedbackSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Bulk/filtered Monitor-dashboard export: one Summary sheet across all
 * activities in the (already-filtered) set, plus a raw-feedback sheet per
 * activity type actually present in that set.
 */
class MonitorEvaluationExport implements WithMultipleSheets
{
    public function __construct(private array $data) {}

    public function sheets(): array
    {
        $sheets = [new MonitorSummarySheet($this->data['summary'])];

        if (!empty($this->data['in_house_labels'])) {
            $sheets[] = new RawFeedbackSheet(
                $this->data['in_house_raw'],
                $this->data['in_house_labels'],
                isTws: false,
                withActivityColumn: true,
                sheetTitle: 'Raw Feedback - In-house',
            );
        }

        if (!empty($this->data['tws_labels'])) {
            $sheets[] = new RawFeedbackSheet(
                $this->data['tws_raw'],
                $this->data['tws_labels'],
                isTws: true,
                withActivityColumn: true,
                sheetTitle: 'Raw Feedback - TWS',
            );
        }

        return $sheets;
    }
}
