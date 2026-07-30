<?php

namespace App\Services\ClassRecord;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\ClassRecordScore;
use App\Models\ClassRecord\GradingOption;
use App\Models\ClassRecord\StanineLookup;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ClassRecordExcelService
{
    private const QUARTER_LABELS = ['First', 'Second', 'Third', 'Fourth'];

    public function __construct(private readonly GradeComputationService $grader) {}

    // ── Public entry points ───────────────────────────────────────────────────

    /**
     * Export a single quarter to a temp file. Returns the temp path.
     */
    public function exportQuarter(ClassRecord $classRecord, int $quarter): string
    {
        $classRecord->loadMissing([
            'gradingOption.categories',
            'quarters.assessments.gradingCategory',
            'quarters.students',
        ]);

        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0); // remove default sheet

        $stanine = StanineLookup::orderByDesc('percentage')->get()->toArray();

        $this->addQuarterSheet($spreadsheet, $classRecord, $quarter, $stanine);
        $this->addStanineSheet($spreadsheet, $stanine);

        return $this->saveTempFile($spreadsheet);
    }

    /**
     * Export all 4 quarters + STANINE into one workbook. Returns the temp path.
     */
    public function exportAll(ClassRecord $classRecord): string
    {
        $classRecord->loadMissing([
            'gradingOption.categories',
            'quarters.assessments.gradingCategory',
            'quarters.students',
        ]);

        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        $stanine = StanineLookup::orderByDesc('percentage')->get()->toArray();

        foreach ([1, 2, 3, 4] as $q) {
            $this->addQuarterSheet($spreadsheet, $classRecord, $q, $stanine);
        }
        $this->addStanineSheet($spreadsheet, $stanine);

        return $this->saveTempFile($spreadsheet);
    }

    // ── Quarter sheet builder ─────────────────────────────────────────────────

    private function addQuarterSheet(Spreadsheet $spreadsheet, ClassRecord $classRecord, int $quarter, array $stanine): void
    {
        $quarterData = $classRecord->quarters->firstWhere('quarter', $quarter);
        $optionId = $quarterData?->grading_option_id ?? $classRecord->grading_option_id;
        $option = $optionId ? GradingOption::find($optionId) : null;

        if ($option?->isComplianceMode()) {
            $this->addComplianceQuarterSheet($spreadsheet, $classRecord, $quarter, $option);

            return;
        }

        $subjectSlug = substr(preg_replace('/[^A-Za-z0-9 ]/', '', $classRecord->subject_name), 0, 20);
        $sheetTitle = trim("{$subjectSlug} Q{$quarter}");

        $ws = new Worksheet($spreadsheet, $sheetTitle);
        $spreadsheet->addSheet($ws);

        $qLabel = self::QUARTER_LABELS[$quarter - 1];

        // Load scores into a map { studentId_assessmentId => score }
        $scoresMap = $this->loadScoresMap($quarterData?->id);

        // Compute grades
        $computed = $quarterData
            ? $this->computeGrades($classRecord, $quarter, $stanine, $scoresMap)
            : [];

        // Sorted categories and students
        $categories = $this->effectiveLeafCategories($classRecord, $quarterData);
        $students = $quarterData?->students?->sortBy('sequence_number')->values() ?? collect();
        $assessByCategory = [];
        foreach ($categories as $cat) {
            $assessByCategory[$cat->id] = ($quarterData?->assessments ?? collect())
                ->where('grading_category_id', $cat->id)
                ->sortBy('sort_order')
                ->values();
        }

        // Compute previous running grades for Q2-Q4
        $previousGrades = [];
        if ($quarter > 1) {
            $previousByMaster = $this->getPreviousRunningGrades($classRecord, $quarter - 1, $stanine);
            $previousGrades = $students
                ->filter(fn ($student) => $student->student_id !== null)
                ->mapWithKeys(fn ($student) => [
                    $student->id => $previousByMaster[$student->student_id] ?? null,
                ])
                ->filter(fn ($grade) => $grade !== null)
                ->all();
        }

        // ── Rows 1-8: School info header ──────────────────────────────────────
        $ws->setCellValue('B1', '                      Republic of the Philippines');
        $ws->setCellValue('B2', '                      Department of Science and Technology');
        $ws->setCellValue('B3', '                      PHILIPPINE SCIENCE HIGH SCHOOL - CARAGA REGION CAMPUS IN BUTUAN CITY');
        $ws->setCellValue('B5', "SUBJECT:  {$classRecord->display_name}");
        $ws->setCellValue('B6', "YR. LEVEL & SECTION:  {$classRecord->year_level_section}");
        $ws->setCellValue('B7', "SCHOOL YEAR:  {$classRecord->school_year}");
        $ws->setCellValue('B8', "QUARTER:  {$qLabel} Quarter");

        foreach ([1, 2, 3, 5, 6, 7, 8] as $r) {
            $ws->getStyle("B{$r}")->getFont()->setBold(true)->setSize(10);
        }
        $ws->getStyle('B3')->getFont()->setBold(true)->setSize(11);

        // ── Build column layout ───────────────────────────────────────────────
        // Fixed: # (col1=A), FamilyName (B), GivenName (C), MI (D), Sex (E)
        // Then per category: assessments + T, %, W%
        // Then: TW%, Score%, GE, AE
        // For Q2+: Quarter GE (2/3), Prev Grade (1/3), Final Grade, Final GE

        $FIXED = 5;   // A–E
        $colCursor = $FIXED + 1;  // next column index (1-based)

        // Category column ranges
        $catCols = []; // { catId => { start, end, assessCols: [colIdx] } }
        foreach ($categories as $cat) {
            $assmnts = $assessByCategory[$cat->id] ?? collect();
            $start = $colCursor;
            $assCols = [];
            foreach ($assmnts as $a) {
                $assCols[] = $colCursor++;
            }
            $tCol = $colCursor++;
            $pctCol = $colCursor++;
            $wPctCol = $colCursor++;
            $catCols[$cat->id] = [
                'start' => $start,
                'end' => $colCursor - 1,
                'assCols' => $assCols,
                'tCol' => $tCol,
                'pctCol' => $pctCol,
                'wPctCol' => $wPctCol,
                'assmnts' => $assmnts,
            ];
        }

        $twCol = $colCursor++;
        $scoreCol = $colCursor++;
        $geCol = $colCursor++;
        $aeCol = $colCursor++;

        $runCols = [];
        if ($quarter > 1) {
            $runCols['qGe2_3'] = $colCursor++;  // Quarter GE × 2/3
            $runCols['prevGe1_3'] = $colCursor++;  // Prev × 1/3
            $runCols['finalGe'] = $colCursor++;
            $runCols['finalAe'] = $colCursor++;
        }

        // ── Row 10: Category group headers ────────────────────────────────────
        // Fixed columns (merged rows 10-12)
        foreach ([1, 2, 3, 4, 5] as $c) {
            $cellAddr = Coordinate::stringFromColumnIndex($c);
            $ws->mergeCells("{$cellAddr}10:{$cellAddr}12");
        }
        $ws->setCellValue('A10', '#');
        $ws->setCellValue('B10', 'Family Name');
        $ws->setCellValue('C10', 'Given Name');
        $ws->setCellValue('D10', 'MI');
        $ws->setCellValue('E10', 'Sex');

        foreach ($categories as $cat) {
            $cc = $catCols[$cat->id];
            $startL = Coordinate::stringFromColumnIndex($cc['start']);
            $endL = Coordinate::stringFromColumnIndex($cc['end']);
            $ws->mergeCells("{$startL}10:{$endL}10");
            $ws->setCellValue("{$startL}10", "{$cat->name} (".round($cat->weight * 100).'%)');
        }

        // Summary group header (TW%, Score%, GE, AE)
        $sumStart = Coordinate::stringFromColumnIndex($twCol);
        $sumEnd = Coordinate::stringFromColumnIndex($quarter > 1 ? max($runCols) : $aeCol);
        $ws->mergeCells("{$sumStart}10:{$sumEnd}10");
        $ws->setCellValue("{$sumStart}10", "{$qLabel} Quarter Summary");

        // ── Row 11: Sub-column headers ────────────────────────────────────────
        foreach ($categories as $cat) {
            $cc = $catCols[$cat->id];
            foreach ($cc['assmnts'] as $i => $a) {
                $ws->setCellValue(Coordinate::stringFromColumnIndex($cc['assCols'][$i]).'11',
                    $cat->code.$a->assessment_number);
            }
            $ws->setCellValue(Coordinate::stringFromColumnIndex($cc['tCol']).'11', 'T');
            $ws->setCellValue(Coordinate::stringFromColumnIndex($cc['pctCol']).'11', '%');
            $ws->setCellValue(Coordinate::stringFromColumnIndex($cc['wPctCol']).'11', 'W%');
        }
        $ws->setCellValue(Coordinate::stringFromColumnIndex($twCol).'11', 'TW%');
        $ws->setCellValue(Coordinate::stringFromColumnIndex($scoreCol).'11', 'Score%');
        $ws->setCellValue(Coordinate::stringFromColumnIndex($geCol).'11', 'GE');
        $ws->setCellValue(Coordinate::stringFromColumnIndex($aeCol).'11', 'Adjectival Eq.');
        if ($quarter > 1) {
            $ws->setCellValue(Coordinate::stringFromColumnIndex($runCols['qGe2_3']).'11', "{$qLabel} Qtr × 2/3");
            $ws->setCellValue(Coordinate::stringFromColumnIndex($runCols['prevGe1_3']).'11', 'Prev × 1/3');
            $ws->setCellValue(Coordinate::stringFromColumnIndex($runCols['finalGe']).'11', 'Final GE');
            $ws->setCellValue(Coordinate::stringFromColumnIndex($runCols['finalAe']).'11', 'Final Adjectival');
        }

        // ── Row 12: Max scores ────────────────────────────────────────────────
        foreach ($categories as $cat) {
            $cc = $catCols[$cat->id];
            foreach ($cc['assmnts'] as $i => $a) {
                $ws->setCellValue(Coordinate::stringFromColumnIndex($cc['assCols'][$i]).'12',
                    number_format($a->max_score, 0));
            }
        }

        // ── Style header rows ─────────────────────────────────────────────────
        foreach ([10, 11, 12] as $row) {
            $lastCol = Coordinate::stringFromColumnIndex($colCursor - 1);
            $ws->getStyle("A{$row}:{$lastCol}{$row}")
                ->getFont()->setBold(true);
            $ws->getStyle("A{$row}:{$lastCol}{$row}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);
            $ws->getStyle("A{$row}:{$lastCol}{$row}")
                ->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($row === 10 ? 'BDD7EE' : ($row === 11 ? 'D9E1F2' : 'EBF3FB'));
            $ws->getStyle("A{$row}:{$lastCol}{$row}")
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
        $ws->getRowDimension(10)->setRowHeight(22);
        $ws->getRowDimension(11)->setRowHeight(20);
        $ws->getRowDimension(12)->setRowHeight(18);

        // ── Rows 13+: Student data ────────────────────────────────────────────
        foreach ($students as $sIdx => $student) {
            $row = 13 + $sIdx;
            $sid = $student->id;
            $comp = $computed[$sid] ?? null;

            $ws->setCellValue("A{$row}", $student->sequence_number);
            $ws->setCellValue("B{$row}", $student->family_name);
            $ws->setCellValue("C{$row}", $student->given_name);
            $ws->setCellValue("D{$row}", $student->middle_initial ?? '');
            $ws->setCellValue("E{$row}", $student->sex);

            foreach ($categories as $cat) {
                $cc = $catCols[$cat->id];
                foreach ($cc['assmnts'] as $i => $a) {
                    $score = $scoresMap["{$sid}_{$a->id}"] ?? null;
                    if ($score !== null) {
                        $ws->setCellValue(Coordinate::stringFromColumnIndex($cc['assCols'][$i]).$row, $score);
                        $ws->getStyle(Coordinate::stringFromColumnIndex($cc['assCols'][$i]).$row)
                            ->getNumberFormat()->setFormatCode('0.00');
                    }
                }

                // T, %, W% from computed
                if ($comp) {
                    // PHP service returns key 'categories' with sub-key 'categoryId'
                    $catResult = collect($comp['categories'] ?? [])->firstWhere('categoryId', $cat->id);
                    if ($catResult) {
                        $ws->setCellValue(Coordinate::stringFromColumnIndex($cc['tCol']).$row, $catResult['total']);
                        $ws->setCellValue(Coordinate::stringFromColumnIndex($cc['pctCol']).$row, $catResult['percentage']);
                        $ws->setCellValue(Coordinate::stringFromColumnIndex($cc['wPctCol']).$row, $catResult['weightedPercentage']);
                        $ws->getStyle(Coordinate::stringFromColumnIndex($cc['pctCol']).$row)->getNumberFormat()->setFormatCode('0.00%');
                        $ws->getStyle(Coordinate::stringFromColumnIndex($cc['wPctCol']).$row)->getNumberFormat()->setFormatCode('0.00%');
                    }
                }
            }

            if ($comp) {
                // PHP service uses 'totalWeightedPercentage' and 'percentageScore'
                $ws->setCellValue(Coordinate::stringFromColumnIndex($twCol).$row, $comp['totalWeightedPercentage']);
                $ws->setCellValue(Coordinate::stringFromColumnIndex($scoreCol).$row, $comp['percentageScore'] / 100);
                $ws->setCellValue(Coordinate::stringFromColumnIndex($geCol).$row, $comp['gradeEquivalent']);
                $ws->setCellValue(Coordinate::stringFromColumnIndex($aeCol).$row, $comp['adjectivalEquivalent']);

                $ws->getStyle(Coordinate::stringFromColumnIndex($twCol).$row)->getNumberFormat()->setFormatCode('0.00%');
                $ws->getStyle(Coordinate::stringFromColumnIndex($scoreCol).$row)->getNumberFormat()->setFormatCode('0.00%');
                $ws->getStyle(Coordinate::stringFromColumnIndex($geCol).$row)->getNumberFormat()->setFormatCode('0.000');

                if ($quarter > 1) {
                    $prevGrade = $previousGrades[$sid] ?? null;
                    $qWeight = $comp['gradeEquivalent'] * (2 / 3);
                    $pWeight = $prevGrade !== null ? $prevGrade * (1 / 3) : 0;
                    $finalGe = $comp['runningGrade'];
                    $finalLookup = $this->stanineAdjectival($finalGe, $stanine);

                    $ws->setCellValue(Coordinate::stringFromColumnIndex($runCols['qGe2_3']).$row, $qWeight);
                    $ws->setCellValue(Coordinate::stringFromColumnIndex($runCols['prevGe1_3']).$row, $pWeight);
                    $ws->setCellValue(Coordinate::stringFromColumnIndex($runCols['finalGe']).$row, $finalGe);
                    $ws->setCellValue(Coordinate::stringFromColumnIndex($runCols['finalAe']).$row, $finalLookup);

                    $ws->getStyle(Coordinate::stringFromColumnIndex($runCols['finalGe']).$row)->getNumberFormat()->setFormatCode('0.000');
                }

                // Color-code the Adjectival cell
                $this->applyAdjectivalColor($ws, Coordinate::stringFromColumnIndex($aeCol).$row, $comp['adjectivalEquivalent']);
                if ($quarter > 1 && isset($runCols['finalAe'])) {
                    $this->applyAdjectivalColor($ws, Coordinate::stringFromColumnIndex($runCols['finalAe']).$row, $finalLookup ?? '');
                }
            }

            // Row borders
            $lastColL = Coordinate::stringFromColumnIndex($colCursor - 1);
            $ws->getStyle("A{$row}:{$lastColL}{$row}")
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            // Alternate row shading
            if ($sIdx % 2 === 1) {
                $ws->getStyle("A{$row}:{$lastColL}{$row}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F2F2F2');
            }
        }

        // ── Signature block (footer) ──────────────────────────────────────────
        $footerRow = 13 + $students->count() + 3;
        $ws->setCellValue("A{$footerRow}", 'Prepared by:');
        $ws->setCellValue("D{$footerRow}", 'Checked by:');
        $ws->setCellValue("G{$footerRow}", 'Approved by:');
        $ws->setCellValue('A'.($footerRow + 2), $classRecord->teacher?->name ?? '');
        $ws->setCellValue('A'.($footerRow + 3), $classRecord->teacher?->position ?? 'Subject Teacher');
        $ws->getStyle("A{$footerRow}")->getFont()->setBold(true);
        $ws->getStyle("D{$footerRow}")->getFont()->setBold(true);
        $ws->getStyle("G{$footerRow}")->getFont()->setBold(true);

        // Activity log — assessment titles and dates
        $logRow = $footerRow + 6;
        $ws->setCellValue("A{$logRow}", 'Assessment Log:');
        $ws->getStyle("A{$logRow}")->getFont()->setBold(true);
        $logRow++;
        foreach ($categories as $cat) {
            $cc = $catCols[$cat->id];
            foreach ($cc['assmnts'] as $i => $a) {
                $label = $cat->code.$a->assessment_number.': '.$a->title;
                $date = $a->activity_date ? ' ('.$a->activity_date.')' : '';
                $ws->setCellValue("A{$logRow}", $label.$date);
                $logRow++;
            }
        }

        // ── Column widths ─────────────────────────────────────────────────────
        $ws->getColumnDimension('A')->setWidth(6);
        $ws->getColumnDimension('B')->setWidth(20);
        $ws->getColumnDimension('C')->setWidth(18);
        $ws->getColumnDimension('D')->setWidth(5);
        $ws->getColumnDimension('E')->setWidth(5);
        for ($c = 6; $c < $colCursor; $c++) {
            $colL = Coordinate::stringFromColumnIndex($c);
            if ($c === $aeCol || (isset($runCols['finalAe']) && $c === $runCols['finalAe'])) {
                $ws->getColumnDimension($colL)->setWidth(18);
            } else {
                $ws->getColumnDimension($colL)->setWidth(9);
            }
        }

        // ── Freeze panes: after row 12, after col E ───────────────────────────
        $ws->freezePane('F13');
    }

    // ── Compliance-mode quarter sheet ─────────────────────────────────────────

    /**
     * A simpler sheet for compliance-mode subjects (e.g. Values Education):
     * a checkbox per activity (TRUE/blank, exported as ✓/blank), category
     * %/W% same as numeric, then Compliance % + Remark instead of
     * TW%/Score%/GE/Adjectival and any running-grade columns — compliance
     * quarters don't carry forward, each stands alone.
     */
    private function addComplianceQuarterSheet(Spreadsheet $spreadsheet, ClassRecord $classRecord, int $quarter, GradingOption $option): void
    {
        $subjectSlug = substr(preg_replace('/[^A-Za-z0-9 ]/', '', $classRecord->subject_name), 0, 20);
        $sheetTitle = trim("{$subjectSlug} Q{$quarter}");

        $ws = new Worksheet($spreadsheet, $sheetTitle);
        $spreadsheet->addSheet($ws);

        $qLabel = self::QUARTER_LABELS[$quarter - 1];
        $quarterData = $classRecord->quarters->firstWhere('quarter', $quarter);
        $passLabel = $option->compliance_pass_label ?? 'Completed';
        $failLabel = $option->compliance_fail_label ?? 'Not Completed';
        $passThreshold = (float) ($option->compliance_pass_threshold ?? 0.75);

        $scoresMap = $this->loadScoresMap($quarterData?->id);
        $categories = $this->effectiveLeafCategories($classRecord, $quarterData);
        $students = $quarterData?->students?->sortBy('sequence_number')->values() ?? collect();
        $assessByCategory = [];
        foreach ($categories as $cat) {
            $assessByCategory[$cat->id] = ($quarterData?->assessments ?? collect())
                ->where('grading_category_id', $cat->id)
                ->sortBy('sort_order')
                ->values();
        }

        $computed = [];
        if ($quarterData) {
            $categoriesForGrader = $categories->map(fn ($cat) => [
                'id' => $cat->id,
                'code' => $cat->code,
                'weight' => (float) $cat->weight,
                'assessments' => ($assessByCategory[$cat->id] ?? collect())
                    ->map(fn ($a) => ['id' => $a->id, 'maxScore' => (float) $a->max_score])
                    ->values()->toArray(),
            ])->toArray();

            $studentsForGrader = $students->map(fn ($s) => [
                'id' => $s->id,
                'scores' => collect($scoresMap)
                    ->filter(fn ($v, $k) => str_starts_with($k, "{$s->id}_"))
                    ->mapWithKeys(fn ($v, $k) => [(int) explode('_', $k)[1] => $v !== null ? (float) $v : null])
                    ->toArray(),
            ])->toArray();

            $result = $this->grader->computeFullComplianceClassRecord([
                'gradingOption' => ['categories' => $categoriesForGrader],
                'students' => $studentsForGrader,
                'passThreshold' => $passThreshold,
                'passLabel' => $passLabel,
                'failLabel' => $failLabel,
            ]);
            $computed = collect($result['students'])->keyBy(fn ($s) => (int) $s['studentId'])->toArray();
        }

        $ws->setCellValue('B1', '                      Republic of the Philippines');
        $ws->setCellValue('B2', '                      Department of Science and Technology');
        $ws->setCellValue('B3', '                      PHILIPPINE SCIENCE HIGH SCHOOL - CARAGA REGION CAMPUS IN BUTUAN CITY');
        $ws->setCellValue('B5', "SUBJECT:  {$classRecord->display_name}");
        $ws->setCellValue('B6', "YR. LEVEL & SECTION:  {$classRecord->year_level_section}");
        $ws->setCellValue('B7', "SCHOOL YEAR:  {$classRecord->school_year}");
        $ws->setCellValue('B8', "QUARTER:  {$qLabel} Quarter (Compliance)");

        foreach ([1, 2, 3, 5, 6, 7, 8] as $r) {
            $ws->getStyle("B{$r}")->getFont()->setBold(true)->setSize(10);
        }
        $ws->getStyle('B3')->getFont()->setBold(true)->setSize(11);

        $FIXED = 5;
        $colCursor = $FIXED + 1;

        $catCols = [];
        foreach ($categories as $cat) {
            $assmnts = $assessByCategory[$cat->id] ?? collect();
            $start = $colCursor;
            $assCols = [];
            foreach ($assmnts as $a) {
                $assCols[] = $colCursor++;
            }
            $pctCol = $colCursor++;
            $wPctCol = $colCursor++;
            $catCols[$cat->id] = [
                'start' => $start,
                'end' => $colCursor - 1,
                'assCols' => $assCols,
                'pctCol' => $pctCol,
                'wPctCol' => $wPctCol,
                'assmnts' => $assmnts,
            ];
        }

        $complianceCol = $colCursor++;
        $remarkCol = $colCursor++;

        foreach ([1, 2, 3, 4, 5] as $c) {
            $cellAddr = Coordinate::stringFromColumnIndex($c);
            $ws->mergeCells("{$cellAddr}10:{$cellAddr}12");
        }
        $ws->setCellValue('A10', '#');
        $ws->setCellValue('B10', 'Family Name');
        $ws->setCellValue('C10', 'Given Name');
        $ws->setCellValue('D10', 'MI');
        $ws->setCellValue('E10', 'Sex');

        foreach ($categories as $cat) {
            $cc = $catCols[$cat->id];
            $startL = Coordinate::stringFromColumnIndex($cc['start']);
            $endL = Coordinate::stringFromColumnIndex($cc['end']);
            $ws->mergeCells("{$startL}10:{$endL}10");
            $ws->setCellValue("{$startL}10", "{$cat->name} (".round($cat->weight * 100).'%)');
        }

        $sumStart = Coordinate::stringFromColumnIndex($complianceCol);
        $sumEnd = Coordinate::stringFromColumnIndex($remarkCol);
        $ws->mergeCells("{$sumStart}10:{$sumEnd}10");
        $ws->setCellValue("{$sumStart}10", "{$qLabel} Quarter Summary");

        foreach ($categories as $cat) {
            $cc = $catCols[$cat->id];
            foreach ($cc['assmnts'] as $i => $a) {
                $ws->setCellValue(Coordinate::stringFromColumnIndex($cc['assCols'][$i]).'11', $cat->code.$a->assessment_number);
            }
            $ws->setCellValue(Coordinate::stringFromColumnIndex($cc['pctCol']).'11', '%');
            $ws->setCellValue(Coordinate::stringFromColumnIndex($cc['wPctCol']).'11', 'W%');
        }
        $ws->setCellValue(Coordinate::stringFromColumnIndex($complianceCol).'11', 'Compliance %');
        $ws->setCellValue(Coordinate::stringFromColumnIndex($remarkCol).'11', 'Remark');

        foreach ([10, 11, 12] as $row) {
            $lastCol = Coordinate::stringFromColumnIndex($colCursor - 1);
            $ws->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
            $ws->getStyle("A{$row}:{$lastCol}{$row}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);
            $ws->getStyle("A{$row}:{$lastCol}{$row}")
                ->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($row === 10 ? 'BDD7EE' : ($row === 11 ? 'D9E1F2' : 'EBF3FB'));
            $ws->getStyle("A{$row}:{$lastCol}{$row}")
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
        $ws->getRowDimension(10)->setRowHeight(22);
        $ws->getRowDimension(11)->setRowHeight(20);
        $ws->getRowDimension(12)->setRowHeight(18);

        foreach ($students as $sIdx => $student) {
            $row = 13 + $sIdx;
            $sid = $student->id;
            $comp = $computed[$sid] ?? null;

            $ws->setCellValue("A{$row}", $student->sequence_number);
            $ws->setCellValue("B{$row}", $student->family_name);
            $ws->setCellValue("C{$row}", $student->given_name);
            $ws->setCellValue("D{$row}", $student->middle_initial ?? '');
            $ws->setCellValue("E{$row}", $student->sex);

            foreach ($categories as $cat) {
                $cc = $catCols[$cat->id];
                foreach ($cc['assmnts'] as $i => $a) {
                    $score = $scoresMap["{$sid}_{$a->id}"] ?? null;
                    // Complied = score at/above the activity's max — shown as a
                    // checkmark; anything else (0/null) is left blank.
                    $complied = $score !== null && (float) $score >= (float) $a->max_score;
                    $ws->setCellValue(Coordinate::stringFromColumnIndex($cc['assCols'][$i]).$row, $complied ? '✓' : '');
                    $ws->getStyle(Coordinate::stringFromColumnIndex($cc['assCols'][$i]).$row)
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                if ($comp) {
                    $catResult = collect($comp['categories'] ?? [])->firstWhere('categoryId', $cat->id);
                    if ($catResult) {
                        $ws->setCellValue(Coordinate::stringFromColumnIndex($cc['pctCol']).$row, $catResult['percentage']);
                        $ws->setCellValue(Coordinate::stringFromColumnIndex($cc['wPctCol']).$row, $catResult['weightedPercentage']);
                        $ws->getStyle(Coordinate::stringFromColumnIndex($cc['pctCol']).$row)->getNumberFormat()->setFormatCode('0.00%');
                        $ws->getStyle(Coordinate::stringFromColumnIndex($cc['wPctCol']).$row)->getNumberFormat()->setFormatCode('0.00%');
                    }
                }
            }

            if ($comp) {
                $ws->setCellValue(Coordinate::stringFromColumnIndex($complianceCol).$row, $comp['compliancePercentage'] / 100);
                $ws->setCellValue(Coordinate::stringFromColumnIndex($remarkCol).$row, $comp['remark']);
                $ws->getStyle(Coordinate::stringFromColumnIndex($complianceCol).$row)->getNumberFormat()->setFormatCode('0.00%');

                $ws->getStyle(Coordinate::stringFromColumnIndex($remarkCol).$row)
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB($comp['passed'] ? 'C6EFCE' : 'FFC7CE');
            }

            $lastColL = Coordinate::stringFromColumnIndex($colCursor - 1);
            $ws->getStyle("A{$row}:{$lastColL}{$row}")
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            if ($sIdx % 2 === 1) {
                $ws->getStyle("A{$row}:{$lastColL}{$row}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F2F2F2');
            }
        }

        $footerRow = 13 + $students->count() + 3;
        $ws->setCellValue("A{$footerRow}", 'Prepared by:');
        $ws->setCellValue("D{$footerRow}", 'Checked by:');
        $ws->setCellValue("G{$footerRow}", 'Approved by:');
        $ws->setCellValue('A'.($footerRow + 2), $classRecord->teacher?->name ?? '');
        $ws->setCellValue('A'.($footerRow + 3), $classRecord->teacher?->position ?? 'Subject Teacher');
        $ws->getStyle("A{$footerRow}")->getFont()->setBold(true);
        $ws->getStyle("D{$footerRow}")->getFont()->setBold(true);
        $ws->getStyle("G{$footerRow}")->getFont()->setBold(true);

        $logRow = $footerRow + 6;
        $ws->setCellValue("A{$logRow}", 'Compliance Rule: Pass threshold '.round($passThreshold * 100)."% — {$passLabel} / {$failLabel}");
        $ws->getStyle("A{$logRow}")->getFont()->setBold(true);
        $logRow += 2;
        $ws->setCellValue("A{$logRow}", 'Activity Log:');
        $ws->getStyle("A{$logRow}")->getFont()->setBold(true);
        $logRow++;
        foreach ($categories as $cat) {
            $cc = $catCols[$cat->id];
            foreach ($cc['assmnts'] as $i => $a) {
                $label = $cat->code.$a->assessment_number.': '.$a->title;
                $date = $a->activity_date ? ' ('.$a->activity_date.')' : '';
                $ws->setCellValue("A{$logRow}", $label.$date);
                $logRow++;
            }
        }

        $ws->getColumnDimension('A')->setWidth(6);
        $ws->getColumnDimension('B')->setWidth(20);
        $ws->getColumnDimension('C')->setWidth(18);
        $ws->getColumnDimension('D')->setWidth(5);
        $ws->getColumnDimension('E')->setWidth(5);
        for ($c = 6; $c < $colCursor; $c++) {
            $colL = Coordinate::stringFromColumnIndex($c);
            $ws->getColumnDimension($colL)->setWidth($c === $remarkCol ? 16 : 9);
        }

        $ws->freezePane('F13');
    }

    // ── STANINE sheet ─────────────────────────────────────────────────────────

    private function addStanineSheet(Spreadsheet $spreadsheet, array $stanine): void
    {
        $ws = new Worksheet($spreadsheet, 'STANINE');
        $spreadsheet->addSheet($ws);

        $ws->setCellValue('A1', 'TW%');
        $ws->setCellValue('B1', 'Grade Equivalent');
        $ws->setCellValue('C1', 'Adjectival Equivalent');
        $ws->getStyle('A1:C1')->getFont()->setBold(true);
        $ws->getStyle('A1:C1')->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('BDD7EE');
        $ws->getStyle('A1:C1')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $ws->getStyle('A1:C1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach ($stanine as $i => $row) {
            $r = $i + 2;
            $ws->setCellValue("A{$r}", $row['percentage']);
            $ws->setCellValue("B{$r}", $row['grade_equivalent']);
            $ws->setCellValue("C{$r}", $row['adjectival_equivalent']);
            $ws->getStyle("B{$r}")->getNumberFormat()->setFormatCode('0.000');
            $ws->getStyle("A{$r}:C{$r}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $this->applyAdjectivalColor($ws, "C{$r}", $row['adjectival_equivalent']);
        }

        $ws->getColumnDimension('A')->setWidth(10);
        $ws->getColumnDimension('B')->setWidth(18);
        $ws->getColumnDimension('C')->setWidth(22);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Leaf categories of the grading option in force for the given quarter
     * (per-quarter override, else the record default), sorted for display.
     */
    private function effectiveLeafCategories(ClassRecord $classRecord, ?ClassRecordQuarter $quarterData)
    {
        $optionId = $quarterData?->grading_option_id ?? $classRecord->grading_option_id;
        $option = $optionId ? GradingOption::with('categories')->find($optionId) : null;

        return $option ? $option->leafCategories()->sortBy('sort_order')->values() : collect();
    }

    private function loadScoresMap(?int $quarterId): array
    {
        if (! $quarterId) {
            return [];
        }

        return ClassRecordScore::whereHas(
            'student', fn ($q) => $q->where('class_record_quarter_id', $quarterId)
        )->get(['class_record_student_id', 'class_record_assessment_id', 'score'])
            ->mapWithKeys(fn ($s) => [
                "{$s->class_record_student_id}_{$s->class_record_assessment_id}" => $s->score,
            ])
            ->toArray();
    }

    private function computeGrades(ClassRecord $classRecord, int $quarter, array $stanine, array $scoresMap): array
    {
        $quarterData = $classRecord->quarters->firstWhere('quarter', $quarter);
        if (! $quarterData) {
            return [];
        }

        $categories = $this->effectiveLeafCategories($classRecord, $quarterData)->map(function ($cat) use ($quarterData) {
            return [
                'id' => $cat->id,
                'code' => $cat->code,
                'weight' => (float) $cat->weight,
                'assessments' => ($quarterData->assessments ?? collect())
                    ->where('grading_category_id', $cat->id)
                    ->map(fn ($a) => ['id' => $a->id, 'maxScore' => (float) $a->max_score])
                    ->values()->toArray(),
            ];
        })->toArray();

        $students = ($quarterData->students ?? collect())->map(fn ($s) => [
            'id' => $s->id,
            'scores' => collect($scoresMap)
                ->filter(fn ($v, $k) => str_starts_with($k, "{$s->id}_"))
                ->mapWithKeys(fn ($v, $k) => [
                    (int) explode('_', $k)[1] => $v !== null ? (float) $v : null,
                ])->toArray(),
        ])->toArray();

        $previousByMaster = $quarter > 1
            ? $this->getPreviousRunningGrades($classRecord, $quarter - 1, $stanine)
            : [];
        $previousGrades = ($quarterData->students ?? collect())
            ->filter(fn ($student) => $student->student_id !== null)
            ->mapWithKeys(fn ($student) => [
                $student->id => $previousByMaster[$student->student_id] ?? null,
            ])
            ->filter(fn ($grade) => $grade !== null)
            ->all();

        $result = $this->grader->computeFullClassRecord([
            'quarter' => $quarter,
            'gradingOption' => ['categories' => $categories],
            'students' => $students,
            'stanineLookup' => $stanine,
            'previousQuarterGrades' => $previousGrades,
        ]);

        return collect($result['students'])->keyBy(fn ($s) => (int) $s['studentId'])->toArray();
    }

    private function getPreviousRunningGrades(ClassRecord $classRecord, int $prevQ, array $stanine): array
    {
        $prevQuarterData = $classRecord->quarters->firstWhere('quarter', $prevQ);
        if (! $prevQuarterData) {
            return [];
        }

        $scoresMap = $this->loadScoresMap($prevQuarterData->id);
        $computed = $this->computeGrades($classRecord, $prevQ, $stanine, $scoresMap);

        $studentMap = $prevQuarterData->students->keyBy('id');

        return collect($computed)
            ->mapWithKeys(function ($grade, $rosterRowId) use ($studentMap) {
                $masterStudentId = $studentMap->get((int) $rosterRowId)?->student_id;

                return $masterStudentId ? [$masterStudentId => $grade['runningGrade']] : [];
            })
            ->toArray();
    }

    private function stanineAdjectival(float $ge, array $stanine): string
    {
        $ge = round($ge, 3);
        foreach ($stanine as $row) {
            if (round((float) $row['grade_equivalent'], 3) === $ge) {
                return $row['adjectival_equivalent'];
            }
        }
        if ($ge <= 1.140) {
            return 'Excellent';
        }
        if ($ge <= 1.600) {
            return 'Very Good';
        }
        if ($ge <= 2.100) {
            return 'Good';
        }
        if ($ge <= 2.600) {
            return 'Satisfactory';
        }
        if ($ge <= 3.379) {
            return 'Fair';
        }
        if ($ge <= 4.402) {
            return 'Failed on Condition';
        }

        return 'Failed';
    }

    private function applyAdjectivalColor(Worksheet $ws, string $cell, string $adjectival): void
    {
        $a = strtolower($adjectival);
        $rgb = match (true) {
            $a === 'excellent' || $a === 'very good' => 'C6EFCE',  // green
            $a === 'good' || $a === 'satisfactory' => 'BDD7EE',  // blue
            $a === 'fair' => 'FFEB9C',  // amber
            str_contains($a, 'condition') => 'FFCBA4',  // orange
            $a === 'failed' => 'FFC7CE',  // red
            default => null,
        };
        if ($rgb) {
            $ws->getStyle($cell)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($rgb);
        }
    }

    private function saveTempFile(Spreadsheet $spreadsheet): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'cr_export_').'.xlsx';
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($tempFile);

        return $tempFile;
    }
}
