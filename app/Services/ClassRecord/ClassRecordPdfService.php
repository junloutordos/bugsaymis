<?php

namespace App\Services\ClassRecord;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\GradingOption;
use App\Models\ClassRecord\StanineLookup;
use Mpdf\Mpdf;
use Mpdf\MpdfException;

class ClassRecordPdfService
{
    private const QUARTER_LABELS = ['', 'First', 'Second', 'Third', 'Fourth'];

    public function __construct(private readonly GradeComputationService $grader) {}

    /**
     * Generate a PDF for one quarter. Returns raw PDF bytes.
     */
    public function exportQuarter(ClassRecord $classRecord, int $quarter): string
    {
        $classRecord->loadMissing(['teacher:id,name,position', 'gradingOption.categories']);

        $stanine = StanineLookup::orderByDesc('percentage')->get()->toArray();

        $qModel = ClassRecordQuarter::where('class_record_id', $classRecord->id)
            ->where('quarter', $quarter)
            ->with(['assessments.gradingCategory', 'students.scores'])
            ->first();

        if (! $qModel) {
            throw new \RuntimeException("Quarter {$quarter} not found.");
        }

        $categories = $this->buildCategories($classRecord, $qModel);
        $studentsData = $this->buildStudentScores($qModel);
        $previousByMaster = $this->getPreviousRunningGrades($classRecord, $quarter, $stanine);
        $previousGrades = $qModel->students
            ->filter(fn ($student) => $student->student_id !== null)
            ->mapWithKeys(fn ($student) => [
                $student->id => $previousByMaster[$student->student_id] ?? null,
            ])
            ->filter(fn ($grade) => $grade !== null)
            ->all();

        $result = $this->grader->computeFullClassRecord([
            'quarter' => $quarter,
            'gradingOption' => ['categories' => $categories],
            'students' => $studentsData,
            'stanineLookup' => $stanine,
            'previousQuarterGrades' => $previousGrades,
        ]);

        $gradeMap = [];
        foreach ($result['students'] as $row) {
            $gradeMap[$row['studentId']] = $row;
        }

        $scoreLookup = [];
        foreach ($qModel->students as $student) {
            $scoreLookup[$student->id] = $student->scores
                ->mapWithKeys(fn ($s) => [$s->class_record_assessment_id => $s->score])
                ->toArray();
        }

        $html = $this->buildHtml($classRecord, $qModel, $categories, $gradeMap, $scoreLookup, $stanine);

        try {
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A3',
                'orientation' => 'L',
                'margin_left' => 8,
                'margin_right' => 8,
                'margin_top' => 8,
                'margin_bottom' => 8,
                'default_font' => 'dejavusans',
                'default_font_size' => 7,
                'tempDir' => sys_get_temp_dir(),
            ]);

            $mpdf->SetTitle(sprintf(
                'Class Record Q%d — %s — %s',
                $quarter,
                $classRecord->subject_name,
                $classRecord->year_level_section,
            ));
            $mpdf->SetAuthor('Atlas');

            $mpdf->WriteHTML($html);

            return $mpdf->Output('', 'S');

        } catch (MpdfException $e) {
            throw new \RuntimeException('PDF generation failed: '.$e->getMessage(), 0, $e);
        }
    }

    // ── Data builders ─────────────────────────────────────────────────────────

    private function buildCategories(ClassRecord $classRecord, ClassRecordQuarter $quarter): array
    {
        $optionId = $quarter->grading_option_id ?? $classRecord->grading_option_id;
        $option = $optionId ? GradingOption::with('categories')->find($optionId) : null;
        $leaves = $option ? $option->leafCategories()->sortBy('sort_order')->values() : collect();

        return $leaves->map(function ($cat) use ($quarter) {
            return [
                'id' => $cat->id,
                'code' => $cat->code,
                'weight' => (float) $cat->weight,
                'assessments' => $quarter->assessments
                    ->where('grading_category_id', $cat->id)
                    ->sortBy('sort_order')
                    ->map(fn ($a) => [
                        'id' => $a->id,
                        'maxScore' => (float) $a->max_score,
                        'num' => $a->assessment_number,
                    ])
                    ->values()
                    ->toArray(),
            ];
        })->toArray();
    }

    private function buildStudentScores(ClassRecordQuarter $quarter): array
    {
        return $quarter->students->map(function ($student) {
            return [
                'id' => $student->id,
                'scores' => $student->scores
                    ->mapWithKeys(fn ($s) => [
                        $s->class_record_assessment_id => $s->score !== null ? (float) $s->score : null,
                    ])->toArray(),
            ];
        })->toArray();
    }

    /**
     * Recursively compute running grades from the previous quarter (depth ≤ 3).
     */
    private function getPreviousRunningGrades(ClassRecord $classRecord, int $quarter, array $stanine): array
    {
        if ($quarter <= 1) {
            return [];
        }

        $prevModel = ClassRecordQuarter::where('class_record_id', $classRecord->id)
            ->where('quarter', $quarter - 1)
            ->with(['assessments.gradingCategory', 'students.scores'])
            ->first();

        if (! $prevModel) {
            return [];
        }

        $classRecord->loadMissing('gradingOption.categories');
        $categories = $this->buildCategories($classRecord, $prevModel);
        $studentsData = $this->buildStudentScores($prevModel);
        $prevPrevByMaster = $this->getPreviousRunningGrades($classRecord, $quarter - 1, $stanine);
        $prevPrev = $prevModel->students
            ->filter(fn ($student) => $student->student_id !== null)
            ->mapWithKeys(fn ($student) => [
                $student->id => $prevPrevByMaster[$student->student_id] ?? null,
            ])
            ->filter(fn ($grade) => $grade !== null)
            ->all();

        $result = $this->grader->computeFullClassRecord([
            'quarter' => $quarter - 1,
            'gradingOption' => ['categories' => $categories],
            'students' => $studentsData,
            'stanineLookup' => $stanine,
            'previousQuarterGrades' => $prevPrev,
        ]);

        $grades = [];
        $studentMap = $prevModel->students->keyBy('id');
        foreach ($result['students'] as $row) {
            $masterStudentId = $studentMap->get((int) $row['studentId'])?->student_id;
            if ($masterStudentId) {
                $grades[$masterStudentId] = $row['runningGrade'] ?? null;
            }
        }

        return $grades;
    }

    // ── HTML builder ──────────────────────────────────────────────────────────

    private function buildHtml(
        ClassRecord $classRecord,
        ClassRecordQuarter $quarter,
        array $categories,
        array $gradeMap,
        array $scoreLookup,
        array $stanine,
    ): string {
        $qLabel = self::QUARTER_LABELS[$quarter->quarter];
        $teacherName = $classRecord->teacher?->name ?? '—';
        $teacherPos = $classRecord->teacher?->position ?? '';

        $th = 'border:0.4pt solid #9aabbb; padding:2px 2px; text-align:center; background:#e4eaf6; font-weight:bold;';
        $thG = 'border:0.4pt solid #9aabbb; padding:2px 2px; text-align:center; background:#cdd8f0; font-weight:bold;';
        $thP = 'border:0.4pt solid #9aabbb; padding:2px 2px; text-align:center; background:#d4ddf5; font-weight:bold;';
        $td = 'border:0.4pt solid #c8d4de; padding:1px 2px; text-align:center;';
        $tdN = 'border:0.4pt solid #c8d4de; padding:1px 4px; text-align:left;';
        $tdP = 'border:0.4pt solid #c8d4de; padding:1px 2px; text-align:center; background:#edf0fb; font-weight:bold;';

        $out = '<!DOCTYPE html><html><head><meta charset="utf-8"></head>'
             .'<body style="font-family:Arial,sans-serif; font-size:7pt;">';

        // ── Title block ───────────────────────────────────────────────────────
        $out .= '<div style="text-align:center; margin-bottom:4px; line-height:1.4;">';
        $out .= '<div style="font-size:9pt; font-weight:bold;">PHILIPPINE SCIENCE HIGH SCHOOL – CARAGA REGION CAMPUS</div>';
        $out .= '<div style="font-size:8pt; font-weight:bold;">CLASS RECORD — '.strtoupper($qLabel).' QUARTER</div>';
        $out .= '<div style="font-size:7pt;">';
        $out .= 'Subject: <b>'.htmlspecialchars($classRecord->subject_name).'</b>&nbsp;|&nbsp;';
        $out .= 'Section: <b>'.htmlspecialchars($classRecord->year_level_section).'</b>&nbsp;|&nbsp;';
        $out .= 'SY '.htmlspecialchars($classRecord->school_year ?? '').'&nbsp;|&nbsp;';
        $out .= 'Teacher: <b>'.htmlspecialchars($teacherName).'</b>';
        if ($teacherPos) {
            $out .= ', '.htmlspecialchars($teacherPos);
        }
        $out .= '</div></div>';

        // ── Grade table ───────────────────────────────────────────────────────
        $out .= '<table style="width:100%; border-collapse:collapse; font-size:6.5pt;">';
        $out .= '<thead>';

        // Header Row 1 — category group headers
        $out .= '<tr>';
        $out .= '<th rowspan="3" style="'.$th.' width:7mm;">#</th>';
        $out .= '<th rowspan="3" style="'.$th.' text-align:left; width:40mm;">Student Name</th>';

        foreach ($categories as $cat) {
            $colspan = count($cat['assessments']) + 1; // assessments + category %
            $wpct = round($cat['weight'] * 100).'%';
            $out .= '<th colspan="'.$colspan.'" style="'.$thG.'">';
            $out .= htmlspecialchars($cat['code']).' ('.$wpct.')';
            $out .= '</th>';
        }

        $out .= '<th rowspan="3" style="'.$th.' width:8mm;">TW%</th>';
        $out .= '<th rowspan="3" style="'.$th.' width:9mm;">QGE</th>';
        $out .= '<th rowspan="3" style="'.$th.' width:9mm;">R.GE</th>';
        $out .= '<th rowspan="3" style="'.$th.' width:13mm;">Rating</th>';
        $out .= '</tr>';

        // Header Row 2 — assessment codes
        $out .= '<tr>';
        foreach ($categories as $cat) {
            foreach ($cat['assessments'] as $a) {
                $out .= '<th style="'.$th.' width:7mm;">'.htmlspecialchars($cat['code']).$a['num'].'</th>';
            }
            $out .= '<th style="'.$thP.' width:8mm;">%</th>';
        }
        $out .= '</tr>';

        // Header Row 3 — max scores
        $out .= '<tr>';
        foreach ($categories as $cat) {
            foreach ($cat['assessments'] as $a) {
                $ms = $a['maxScore'];
                $msStr = ($ms == floor($ms)) ? (int) $ms : $ms;
                $out .= '<th style="'.$th.' font-size:5.5pt; color:#555;">/'.$msStr.'</th>';
            }
            $out .= '<th style="'.$thP.'"></th>';
        }
        $out .= '</tr>';
        $out .= '</thead>';

        // ── Student rows ──────────────────────────────────────────────────────
        $out .= '<tbody>';
        $students = $quarter->students->sortBy('sequence_number');
        $rowNum = 0;

        foreach ($students as $student) {
            $rowNum++;
            $rowBg = $rowNum % 2 === 0 ? '#f3f5fb' : '#ffffff';
            $grade = $gradeMap[$student->id] ?? null;

            $out .= '<tr style="background:'.$rowBg.';">';
            $out .= '<td style="'.$td.'">'.($student->sequence_number ?? $rowNum).'</td>';

            $name = $student->family_name.', '.$student->given_name;
            if ($student->middle_initial) {
                $name .= ' '.$student->middle_initial.'.';
            }
            $out .= '<td style="'.$tdN.'">'.htmlspecialchars($name).'</td>';

            if ($grade) {
                $catMap = [];
                foreach ($grade['categories'] as $cr) {
                    $catMap[$cr['categoryId']] = $cr;
                }

                foreach ($categories as $cat) {
                    foreach ($cat['assessments'] as $a) {
                        $raw = $scoreLookup[$student->id][$a['id']] ?? null;
                        if ($raw === null) {
                            $val = '—';
                        } else {
                            $val = ($raw == floor($raw)) ? (int) $raw : $raw;
                        }
                        $out .= '<td style="'.$td.'">'.$val.'</td>';
                    }
                    $cr = $catMap[$cat['id']] ?? null;
                    $pct = $cr ? round($cr['percentage'] * 100, 1).'%' : '—';
                    $out .= '<td style="'.$tdP.'">'.$pct.'</td>';
                }

                $twPct = round($grade['totalWeightedPercentage'] * 100, 2).'%';
                $out .= '<td style="'.$td.' font-weight:bold;">'.$twPct.'</td>';
                $out .= '<td style="'.$td.' font-weight:bold;">'.number_format($grade['gradeEquivalent'], 3).'</td>';
                $out .= '<td style="'.$td.' font-weight:bold;">'.number_format($grade['runningGrade'], 3).'</td>';

                $adj = $grade['runningAdjectival'] ?? $grade['adjectivalEquivalent'] ?? '—';
                $adjColor = $this->adjColor($adj);
                $out .= '<td style="'.$td.' color:'.$adjColor.'; font-weight:bold;">'.htmlspecialchars($adj).'</td>';
            } else {
                $blankCols = array_sum(array_map(fn ($c) => count($c['assessments']) + 1, $categories)) + 4;
                $out .= '<td colspan="'.$blankCols.'" style="'.$td.'">—</td>';
            }

            $out .= '</tr>';
        }

        if ($rowNum === 0) {
            $totalCols = array_sum(array_map(fn ($c) => count($c['assessments']) + 1, $categories)) + 6;
            $out .= '<tr><td colspan="'.$totalCols.'" style="'.$td.' text-align:center; color:#999; padding:6px;">No students found.</td></tr>';
        }

        $out .= '</tbody></table>';

        // ── Stanine legend ────────────────────────────────────────────────────
        $out .= $this->buildStanineLegend($stanine);

        $out .= '</body></html>';

        return $out;
    }

    private function buildStanineLegend(array $stanine): string
    {
        $groups = [];
        foreach ($stanine as $row) {
            $adj = $row['adjectival_equivalent'];
            $pct = (int) $row['percentage'];
            $ge = (float) $row['grade_equivalent'];
            if (! isset($groups[$adj])) {
                $groups[$adj] = ['minPct' => $pct, 'maxPct' => $pct, 'minGE' => $ge, 'maxGE' => $ge];
            } else {
                $groups[$adj]['minPct'] = min($groups[$adj]['minPct'], $pct);
                $groups[$adj]['maxPct'] = max($groups[$adj]['maxPct'], $pct);
                $groups[$adj]['minGE'] = min($groups[$adj]['minGE'], $ge);
                $groups[$adj]['maxGE'] = max($groups[$adj]['maxGE'], $ge);
            }
        }

        $order = ['Excellent', 'Very Good', 'Good', 'Satisfactory', 'Fair', 'Failed on Condition', 'Failed'];

        $out = '<div style="margin-top:5px; font-size:5.5pt; line-height:1.5;">';
        $out .= '<b>Grading Scale:</b>&nbsp;';
        $parts = [];
        foreach ($order as $adj) {
            if (! isset($groups[$adj])) {
                continue;
            }
            $g = $groups[$adj];
            $color = $this->adjColor($adj);
            $parts[] = '<span style="color:'.$color.';">'
                .htmlspecialchars($adj).': '
                .$g['minPct'].'–'.$g['maxPct'].'%'
                .' (GE '.number_format($g['minGE'], 3).'–'.number_format($g['maxGE'], 3).')'
                .'</span>';
        }
        $out .= implode(' &nbsp;|&nbsp; ', $parts);
        $out .= '</div>';

        return $out;
    }

    private function adjColor(string $adj): string
    {
        $a = strtolower($adj);
        if ($a === 'excellent' || $a === 'very good') {
            return '#059669';
        }
        if ($a === 'good' || $a === 'satisfactory') {
            return '#2563eb';
        }
        if ($a === 'fair') {
            return '#d97706';
        }
        if (str_contains($a, 'condition')) {
            return '#ea580c';
        }
        if ($a === 'failed') {
            return '#dc2626';
        }

        return '#64748b';
    }
}
