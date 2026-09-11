<?php

namespace App\Services\PerformanceManagement;

use App\Models\EmployeeIPCR;
use App\Models\EmployeeIPCRPlan;
use App\Models\User;
use App\Services\PersonNameFormatter;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Server-side PDF generation for IPCR V1 — replaces the unreliable browser
 * print (@media print / window.print()) approach, which could not reliably
 * paginate rowspan-merged SubOutcome/Performance-Indicator cells or long
 * wrapped target/accomplishment text across page breaks. mPDF paginates
 * plain HTML tables (including rowspan) correctly and natively.
 *
 * Mirrors App\Services\IPCRV2\IpcrV2PdfService's conventions.
 */
class IPCRV1PdfService
{
    public function __construct(
        private IPCRWorkflowService $workflow = new IPCRWorkflowService(),
        private PersonNameFormatter $nameFormatter = new PersonNameFormatter()
    ) {}

    public function stream(EmployeeIPCR $ipcr): StreamedResponse
    {
        $html = $this->renderHtml($ipcr);
        $employeeName = $this->nameFormatter->formal($ipcr->user);

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'tempDir' => sys_get_temp_dir(),
        ]);

        $mpdf->SetTitle('IPCR — ' . $employeeName . ' — ' . ($ipcr->period->label ?? $ipcr->rating_period));
        $mpdf->WriteHTML($html);

        $pdfBytes = $mpdf->Output('', 'S');
        $filename = 'IPCR_' . str_replace(' ', '_', $employeeName) . '.pdf';

        return new StreamedResponse(function () use ($pdfBytes) {
            echo $pdfBytes;
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Content-Length' => strlen($pdfBytes),
        ]);
    }

    public function renderHtml(EmployeeIPCR $ipcr): string
    {
        $ipcr->loadMissing([
            'user.division.divisionchief',
            'user.pds.personalInfo',
            'period',
            'plans.performance_indicator.agencyOutcome.parent',
            'coachingSessions',
        ]);

        // Immediate supervisor per the SPMS chain (same resolution as
        // EmployeeIPCRController::show()) — a Division Chief's head is the
        // Campus Director (OCD).
        $supervisor = $this->workflow->immediateSupervisorFor($ipcr->user)
            ?? ($ipcr->user->hasRole('DivisionChief') ? User::havingRole('OCD')->first() : null);
        $ocdUser = User::havingRole('OCD')->first();

        // Load pivot IDs + accomplishment counts, matching
        // EmployeeIPCRController::show()'s shape so plan.pivot / plan.accomplishments_count
        // are populated exactly like the on-screen Show pages.
        $ipcrPlanIds = EmployeeIPCRPlan::where('ipcr_id', $ipcr->id)->pluck('id', 'plan_id');
        $accCounts = \App\Models\Accomplishment::whereIn('ipcr_plan_id', $ipcrPlanIds->values())
            ->selectRaw('ipcr_plan_id, count(*) as cnt')
            ->groupBy('ipcr_plan_id')
            ->pluck('cnt', 'ipcr_plan_id');

        $plans = $ipcr->plans->map(function ($plan) use ($ipcrPlanIds, $accCounts) {
            $pivotId = $ipcrPlanIds[$plan->id] ?? null;
            $plan->accomplishments_count = $pivotId ? ($accCounts[$pivotId] ?? 0) : 0;

            return $plan;
        });

        // Rendering follows AdminIPCRShow.vue (IPCR Monitoring) exactly — this
        // is the module the PDF is generated from. Unlike EmployeeIPCRShow.vue,
        // the monitoring view has no PRE_RATING_STATUSES/PMT_STAGES gating: it
        // always shows the Self/DC rating split and always shows the Rating
        // Summary + signature block, regardless of the IPCR's current status.
        $grouped = IPCRV1OutcomeGrouper::groupPlansByOutcome($plans);
        $summary = $this->buildSummary($plans);

        // Once the DC/supervisor rating is finalized (status reaches "Approved
        // by PMT" or the terminal "Director Signed"), the self-rating is no
        // longer relevant to show alongside it — only the DC rating displays
        // from this point on.
        $hideSelfRating = in_array($ipcr->status, ['Approved by PMT', 'Director Signed'], true);

        $finalNumericRating = $this->workflow->computeWeightedAverage($plans);
        $finalAdjectivalRating = $this->workflow->adjectivalRating($finalNumericRating);

        return view('ipcr-v1.pdf', [
            'ipcr' => $ipcr,
            'employee' => $ipcr->user,
            'employeeName' => $this->nameFormatter->formal($ipcr->user),
            'ratingYear' => $ipcr->period->year ?? $this->extractYearFromRatingPeriod((string) $ipcr->rating_period),
            'hideSelfRating' => $hideSelfRating,
            'supervisor' => $supervisor,
            'supervisorName' => $supervisor ? $this->nameFormatter->formal($supervisor) : null,
            'ocdUser' => $ocdUser,
            'ocdUserName' => $ocdUser ? $this->nameFormatter->formal($ocdUser) : null,
            'grouped' => $grouped,
            'summary' => $summary,
            'finalNumericRating' => $finalNumericRating,
            'finalAdjectivalRating' => $finalAdjectivalRating,
            'coachingSessions' => $ipcr->coachingSessions,
        ])->render();
    }

    /**
     * Ported from AdminIPCRShow.vue's activityTypeLabel().
     */
    public static function activityTypeLabel(?string $value): string
    {
        return $value === 'monitoring' ? 'Monitoring' : 'Coaching';
    }

    /**
     * Ported from AdminIPCRShow.vue's mechanismLabel().
     */
    public static function mechanismLabel(?string $value): string
    {
        return $value === 'group' ? 'Group' : 'One-on-One';
    }

    /**
     * Per-function-type Q/E/T/Average breakdown for the Rating Summary
     * table. Mirrors AdminIPCRShow.vue's summaryByFunctionType computed
     * exactly: supervisor ratings only, no self-rating fallback.
     *
     * @return array<string, array{countQ:int,totalQ:float,countE:int,totalE:float,countT:int,totalT:float,countA:int,totalA:float,weight:float}>
     */
    private function buildSummary($plans): array
    {
        $weights = [
            'Strategic Functions' => 0.30,
            'Core Functions' => 0.55,
            'Support Functions' => 0.15,
            'Uncategorized' => 0,
        ];

        $summary = [];

        foreach ($plans as $plan) {
            $functionType = IPCRV1OutcomeGrouper::normalizeFunctionType(
                $plan->performance_indicator?->agencyOutcome?->function_type
            );

            $summary[$functionType] ??= [
                'countQ' => 0, 'totalQ' => 0.0,
                'countE' => 0, 'totalE' => 0.0,
                'countT' => 0, 'totalT' => 0.0,
                'countA' => 0, 'totalA' => 0.0,
                'weight' => $weights[$functionType] ?? 0,
            ];

            $pivot = $plan->pivot;
            if (! $pivot) {
                continue;
            }

            $q = $pivot->sup_quality;
            $e = $pivot->sup_efficiency;
            $t = $pivot->sup_timeliness;

            if ($q !== null && $q !== '' && is_numeric($q)) {
                $summary[$functionType]['totalQ'] += (float) $q;
                $summary[$functionType]['countQ']++;
            }
            if ($e !== null && $e !== '' && is_numeric($e)) {
                $summary[$functionType]['totalE'] += (float) $e;
                $summary[$functionType]['countE']++;
            }
            if ($t !== null && $t !== '' && is_numeric($t)) {
                $summary[$functionType]['totalT'] += (float) $t;
                $summary[$functionType]['countT']++;
            }

            $avgFromPivot = $pivot->sup_average;
            $planAvg = null;
            if ($avgFromPivot !== null && $avgFromPivot !== '' && is_numeric($avgFromPivot)) {
                $planAvg = (float) $avgFromPivot;
            } else {
                $values = array_filter([$q, $e, $t], fn ($v) => $v !== null && $v !== '' && is_numeric($v));
                if (! empty($values)) {
                    $planAvg = array_sum(array_map('floatval', $values)) / count($values);
                }
            }

            if ($planAvg !== null) {
                $summary[$functionType]['totalA'] += $planAvg;
                $summary[$functionType]['countA']++;
            }
        }

        $order = IPCRV1OutcomeGrouper::FUNCTION_TYPE_ORDER;
        $keys = array_keys($summary);
        usort($keys, fn ($a, $b) => ($order[$a] ?? 99) <=> ($order[$b] ?? 99));

        $sorted = [];
        foreach ($keys as $key) {
            $sorted[$key] = $summary[$key];
        }

        return $sorted;
    }

    /**
     * Ported from AdminIPCRShow.vue's extractYearFromRatingPeriod() — pulls
     * a 19xx/20xx year out of a free-text rating_period string, used as a
     * fallback when the IPCR has no linked IPCRRatingPeriod (period->year).
     */
    private function extractYearFromRatingPeriod(string $ratingPeriod): string
    {
        if (preg_match('/(19|20)\d{2}/', $ratingPeriod, $matches)) {
            return $matches[0];
        }

        return '';
    }

    /**
     * Ported from AdminIPCRShow.vue's computeAverage() — mean of whichever
     * Q/E/T values are numeric, formatted to 2 decimals, or '—' if none are.
     * Used as the display fallback when a pivot's self_average/sup_average
     * column itself is null (average was never explicitly saved).
     */
    public static function computeAverage($q, $e, $t): string
    {
        $values = array_filter([$q, $e, $t], fn ($v) => $v !== null && $v !== '' && is_numeric($v));

        if (empty($values)) {
            return '—';
        }

        return number_format(array_sum(array_map('floatval', $values)) / count($values), 2);
    }
}
