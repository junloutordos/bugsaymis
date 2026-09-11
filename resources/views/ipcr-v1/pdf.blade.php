<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 4px 6px; text-align: left; }
        .center { text-align: center; }
        .band { background: #cbd5e1; font-weight: bold; text-transform: uppercase; padding: 4px 6px; }
        .band-light { background: #e5e7eb; font-weight: 600; padding: 4px 6px; }
        .band-strategic { font-weight: bold; padding: 4px 6px; }
        .title { text-align: center; font-size: 12px; font-weight: bold; margin-bottom: 8px; }
        .uppercase { text-transform: uppercase; }
        .self-dc { font-size: 7px; }
        .self-dc .self { color: #6b7280; }
        .sig-block { padding-top: 16px !important; }
    </style>
</head>
<body>
    {{-- Intro block — mirrors AdminIPCRShow.vue's "Intro block" + rating legend grid --}}
    <p class="title">Individual Performance Commitment and Review (IPCR)<br>FY {{ $ratingYear }}</p>
    <p>
        I, <strong class="uppercase">{{ $employeeName }}</strong>, <strong class="uppercase">{{ $employee->position ?? '' }}</strong>
        of Philippine Science High School - Caraga Region Campus, commit to deliver
        and agree to be rated on the attainment of the following targets in accordance with
        the indicated measures for the period <strong class="uppercase">{{ $ipcr->rating_period }}</strong>.
    </p>

    <table style="border: none; margin-bottom: 8px;">
        <tr style="border: none;">
            <td style="border: none; width: 40%;"></td>
            <td class="center" style="border: none; width: 40%;">
                <strong class="uppercase">{{ $employeeName }}</strong><br>
                <small>{{ $employee->position ?? '' }}</small><br>
                <small>Date: {{ optional($ipcr->submitted_for_review_at)->format('F j, Y') ?? '—' }}</small>
            </td>
            <td style="border: none; width: 20%;">
                <small>5 - Outstanding</small><br>
                <small>4 - Very Satisfactory</small><br>
                <small>3 - Satisfactory</small><br>
                <small>2 - Unsatisfactory</small><br>
                <small>1 - Poor</small>
            </td>
        </tr>
    </table>

    {{-- Reviewed by / Approved by block --}}
    <table>
        <tr>
            <td colspan="4">Reviewed by:</td>
            <td colspan="2">Date:</td>
            <td colspan="4">Approved by:</td>
            <td colspan="2">Date:</td>
        </tr>
        <tr>
            <td colspan="4" class="center sig-block">
                <strong class="uppercase">{{ $supervisorName ?? '—' }}</strong><br>
                <small>{{ $supervisor->position ?? '' }}</small>
            </td>
            <td colspan="2" class="center sig-block">{{ optional($ipcr->target_approved_at)->format('F j, Y') ?? '—' }}</td>
            <td colspan="4" class="center sig-block">
                <strong class="uppercase">{{ $ocdUserName ?? '—' }}</strong><br>
                <small>{{ $ocdUser->position ?? '' }}</small>
            </td>
            <td colspan="2" class="center sig-block">{{ optional($ipcr->target_approved_at)->format('F j, Y') ?? '—' }}</td>
        </tr>
    </table>

    {{-- Main Plans Table — 10 columns: SubOutcome + PI (Output) | Success Indicators |
         Actual Accomplishment | Means of Verification | Q | E | T | A | Remarks.
         Ratings always show the Self/DC split (Admin Monitoring never hides ratings
         by status, unlike EmployeeIPCRShow.vue's pre-rating gating). --}}
    <table style="margin-top: 8px;">
        <thead>
            <tr>
                <th colspan="2" class="center">Output</th>
                <th>Success Indicators</th>
                <th>Actual Accomplishment</th>
                <th>Means of Verification</th>
                <th colspan="4" class="center">Rating</th>
                <th>Remarks</th>
            </tr>
            <tr>
                <th colspan="2"></th>
                <th></th>
                <th></th>
                <th></th>
                <th class="center">Q</th>
                <th class="center">E</th>
                <th class="center">T</th>
                <th class="center">A</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($grouped as $functionType => $outcomes)
                <tr><td colspan="10" class="band">{{ $functionType }}</td></tr>

                @if ($functionType === 'Strategic Functions')
                    <tr><td colspan="10" class="band-strategic">DOST POINT AGENDA</td></tr>
                    <tr><td colspan="10" class="band-strategic">INCREASED IN COMPETITIVENESS OF FILIPINOS IN SCIENCE AND ENGINEERING</td></tr>
                @endif

                @foreach ($outcomes as $outcome => $subGroups)
                    <tr><td colspan="10" class="band-light">{{ $outcome }}</td></tr>

                    @foreach ($subGroups as $subOutcomeLabel => $pis)
                        @php($subOutcomeRowspan = collect($pis)->sum(fn ($group) => count($group)))
                        @php($subOutcomeText = \App\Services\PerformanceManagement\IPCRV1OutcomeGrouper::subOutcomeDisplayFor($pis, $subOutcomeLabel))
                        @php($firstPiInSub = true)

                        @foreach ($pis as $piDesc => $planGroup)
                            @foreach ($planGroup as $i => $plan)
                                <tr>
                                    @if ($firstPiInSub && $i === 0)
                                        <td rowspan="{{ $subOutcomeRowspan }}">{{ $subOutcomeText !== '—' ? $subOutcomeText : '' }}</td>
                                    @endif
                                    @if ($i === 0)
                                        <td rowspan="{{ count($planGroup) }}">{{ $piDesc }}</td>
                                    @endif
                                    <td>{{ $plan['success_indicator'] ?? '' }}</td>
                                    <td>
                                        {{ $plan['pivot']['accomplishment'] ?? '—' }}
                                        @if (($plan['accomplishments_count'] ?? 0) > 0)
                                            <br><small>{{ $plan['accomplishments_count'] }} accomplishment{{ $plan['accomplishments_count'] > 1 ? 's' : '' }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $plan['pivot']['mov_link'] ?? '—' }}</td>
                                    <td class="center">
                                        @if ($hideSelfRating)
                                            {{ $plan['pivot']['sup_quality'] ?? '—' }}
                                        @else
                                            <div class="self-dc"><span class="self">Self: {{ $plan['pivot']['self_quality'] ?? '—' }}</span><br>DC: {{ $plan['pivot']['sup_quality'] ?? '—' }}</div>
                                        @endif
                                    </td>
                                    <td class="center">
                                        @if ($hideSelfRating)
                                            {{ $plan['pivot']['sup_efficiency'] ?? '—' }}
                                        @else
                                            <div class="self-dc"><span class="self">Self: {{ $plan['pivot']['self_efficiency'] ?? '—' }}</span><br>DC: {{ $plan['pivot']['sup_efficiency'] ?? '—' }}</div>
                                        @endif
                                    </td>
                                    <td class="center">
                                        @if ($hideSelfRating)
                                            {{ $plan['pivot']['sup_timeliness'] ?? '—' }}
                                        @else
                                            <div class="self-dc"><span class="self">Self: {{ $plan['pivot']['self_timeliness'] ?? '—' }}</span><br>DC: {{ $plan['pivot']['sup_timeliness'] ?? '—' }}</div>
                                        @endif
                                    </td>
                                    <td class="center">
                                        @php($selfAvg = $plan['pivot']['self_average'] ?? \App\Services\PerformanceManagement\IPCRV1PdfService::computeAverage($plan['pivot']['self_quality'] ?? null, $plan['pivot']['self_efficiency'] ?? null, $plan['pivot']['self_timeliness'] ?? null))
                                        @php($supAvg = $plan['pivot']['sup_average'] ?? \App\Services\PerformanceManagement\IPCRV1PdfService::computeAverage($plan['pivot']['sup_quality'] ?? null, $plan['pivot']['sup_efficiency'] ?? null, $plan['pivot']['sup_timeliness'] ?? null))
                                        @if ($hideSelfRating)
                                            {{ $supAvg }}
                                        @else
                                            <div class="self-dc"><span class="self">Self: {{ $selfAvg }}</span><br>DC: {{ $supAvg }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $plan['pivot']['remarks'] ?? '—' }}</td>
                                </tr>
                                @php($firstPiInSub = false)
                            @endforeach
                        @endforeach
                    @endforeach
                @endforeach
            @endforeach
        </tbody>
    </table>

    {{-- Rating Summary — always shown on Admin Monitoring, no status gating.
         7 columns: Output | Q | E | T | A | %Weight | Overall Weighted Score --}}
    <table style="margin-top: 8px;">
        <thead>
            <tr>
                <th class="center">Output</th>
                <th colspan="4" class="center">Rating</th>
                <th class="center">% Weight</th>
                <th class="center">Overall Weighted Score</th>
            </tr>
            <tr>
                <th></th>
                <th class="center">Q</th>
                <th class="center">E</th>
                <th class="center">T</th>
                <th class="center">A</th>
                <th></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($summary as $type => $row)
                <tr>
                    <td>{{ $type }}</td>
                    <td class="center">{{ $row['countQ'] ? number_format($row['totalQ'] / $row['countQ'], 2) : '—' }}</td>
                    <td class="center">{{ $row['countE'] ? number_format($row['totalE'] / $row['countE'], 2) : '—' }}</td>
                    <td class="center">{{ $row['countT'] ? number_format($row['totalT'] / $row['countT'], 2) : '—' }}</td>
                    <td class="center">{{ $row['countA'] ? number_format($row['totalA'] / $row['countA'], 2) : '—' }}</td>
                    <td class="center">{{ number_format($row['weight'] * 100, 0) }}%</td>
                    <td class="center">{{ $row['countA'] ? number_format(($row['totalA'] / $row['countA']) * $row['weight'], 2) : '—' }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="6" class="band-light">TOTAL</td>
                <td class="center" style="font-weight: bold;">{{ $finalNumericRating !== null ? number_format($finalNumericRating, 2) : '—' }}</td>
            </tr>
            <tr>
                <td colspan="6" class="band-light">Adjectival Rating</td>
                <td class="center" style="font-weight: bold;">{{ $finalAdjectivalRating ?? '—' }}</td>
            </tr>
            <tr>
                <td colspan="7">Comments and Recommendations for Development Purposes: <i>{{ $ipcr->remarks }}</i></td>
            </tr>
        </tbody>
    </table>

    {{-- Signature footer — always shown, 12 columns:
         Discuss with(2)+Date(1) | Assessed by(3)+Date(1) | Final Rating by(3)+Date(2) --}}
    <table style="margin-top: 8px;">
        <tr>
            <td colspan="2">Discuss with:</td>
            <td>Date:</td>
            <td colspan="3">Assessed by:</td>
            <td>Date:</td>
            <td colspan="3">Final Rating by:</td>
            <td colspan="2">Date:</td>
        </tr>
        <tr>
            <td colspan="2" class="center sig-block">
                <strong class="uppercase">{{ $employeeName }}</strong><br>
                <small>{{ $employee->position ?? '' }}</small>
            </td>
            <td class="center sig-block">{{ optional($ipcr->submitted_for_review_at)->format('M j, Y') ?? '—' }}</td>
            <td colspan="3" class="center sig-block">
                <strong class="uppercase">{{ $supervisorName ?? '—' }}</strong><br>
                <small>{{ $supervisor->position ?? '' }}</small>
            </td>
            <td class="center sig-block">{{ optional($ipcr->submitted_rating_at)->format('M j, Y') ?? '—' }}</td>
            <td colspan="3" class="center sig-block">
                <strong class="uppercase">{{ $ocdUserName ?? '—' }}</strong><br>
                <small>{{ $ocdUser->position ?? '' }}</small>
            </td>
            <td colspan="2" class="center sig-block">____________________</td>
        </tr>
        <tr>
            <td colspan="12">
                <small><i>Legend: &nbsp;&nbsp;&nbsp;&nbsp;1 - Effectiveness/Quality &nbsp;&nbsp;&nbsp;&nbsp;2 - Efficiency &nbsp;&nbsp;&nbsp;&nbsp;3 - Timeliness &nbsp;&nbsp;&nbsp;&nbsp;4 - Average</i></small>
            </td>
        </tr>
    </table>

    @if ($coachingSessions->isNotEmpty())
        {{-- Performance Monitoring and Coaching Journal — carried over from the Division
             Chief's submission (mirrors AdminIPCRShow.vue). Forced onto its own page: mPDF
             (unlike a browser) honors page-break-before reliably, so this is a safe,
             real page break rather than a CSS-only approximation. --}}
        <div style="page-break-before: always;">
            <h2 style="font-size: 10px; font-weight: bold; text-transform: uppercase; margin-bottom: 8px;">
                Performance Monitoring and Coaching Journal
            </h2>

            @foreach ($coachingSessions as $session)
                <table style="margin-bottom: 12px;">
                    <tr><td colspan="4" style="font-weight: bold;">Performance Monitoring and Coaching Journal</td></tr>
                    <tr><td colspan="4">Rating Period: {{ $ipcr->period->label ?? $ipcr->rating_period }}</td></tr>
                    <tr><td colspan="4">Name of Campus: Philippine Science High School &ndash; Caraga Region Campus</td></tr>
                    <tr><td colspan="4">Name of Division/Unit: {{ $employee->division->name ?? '—' }}</td></tr>
                    <tr><td colspan="4">Name of Personnel: {{ $employee->name }}, {{ $employee->position }}</td></tr>
                    <tr><td colspan="4">Immediate Supervisor: {{ $supervisor->name ?? '—' }}</td></tr>
                    <tr>
                        <td style="font-weight: 600; width: 22%;">Activity</td>
                        <td>{{ \App\Services\PerformanceManagement\IPCRV1PdfService::activityTypeLabel($session->activity_type) }}</td>
                        <td style="font-weight: 600; width: 22%;">Mechanism</td>
                        <td>{{ \App\Services\PerformanceManagement\IPCRV1PdfService::mechanismLabel($session->mechanism) }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600;">Date of Meeting</td>
                        <td>{{ optional($session->meeting_date)->format('F j, Y') ?? '—' }}</td>
                        <td style="font-weight: 600;">Channel</td>
                        <td>
                            @php($channelParts = array_filter([$session->channel_memo ? 'Memo' : null, $session->channel_others ?: null]))
                            {{ ! empty($channelParts) ? implode('; ', $channelParts) : '—' }}
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 600; vertical-align: top;">Remarks</td>
                        <td colspan="3" style="white-space: pre-wrap;">{{ $session->remarks }}</td>
                    </tr>
                    <tr>
                        <td colspan="2" class="center sig-block">
                            <strong class="uppercase">{{ $session->conducted_by_name }}</strong><br>
                            <small>Conducted by &mdash; {{ optional($session->conducted_at)->format('F j, Y') ?? '—' }}</small>
                        </td>
                        <td colspan="2" class="center sig-block">
                            <strong class="uppercase">{{ $session->noted_by_name ?? '—' }}</strong><br>
                            <small>Noted by &mdash; {{ optional($session->noted_at)->format('F j, Y') ?? '—' }}</small>
                        </td>
                    </tr>
                </table>
            @endforeach
        </div>
    @endif
</body>
</html>
