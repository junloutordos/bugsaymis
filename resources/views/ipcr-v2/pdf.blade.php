<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 4px 6px; text-align: left; }
        .center { text-align: center; }
        .band { background: #d1d5db; font-weight: bold; text-transform: uppercase; padding: 4px 6px; }
        .title { text-align: center; font-size: 12px; font-weight: bold; margin-bottom: 8px; }
        .preamble { text-align: center; font-size: 9px; margin-bottom: 2px; }
    </style>
</head>
<body>
    <p class="preamble">Republic of the Philippines</p>
    <p class="preamble">Department of Science and Technology</p>
    <p class="title">Individual Performance Commitment and Review (IPCR)</p>
    <p>
        I, <strong>{{ strtoupper($ipcr->user->name) }}</strong>, <strong>{{ strtoupper($ipcr->user->position ?? '') }}</strong>,
        of Philippine Science High School &ndash; Caraga Region Campus, commit to deliver and agree to be rated on the
        attainment of the following targets in accordance with the indicated measures for the period of
        <strong>{{ strtoupper($ipcr->period->label) }}</strong>.
    </p>

    <table>
        <tr>
            <td class="center"><strong>{{ strtoupper($ipcr->user->name) }}</strong><br>{{ $ipcr->user->position }}</td>
            <td class="center"><strong>{{ $supervisor ? strtoupper($supervisor->name) : '&mdash;' }}</strong><br>{{ $supervisor->position ?? 'Division Chief' }}</td>
            <td class="center"><strong>{{ $ocdUser ? strtoupper($ocdUser->name) : '&mdash;' }}</strong><br>{{ $ocdUser->position ?? 'Campus Director' }}</td>
        </tr>
    </table>

    <table style="margin-top: 8px;">
        <thead>
            <tr>
                <th rowspan="2">Function</th>
                <th colspan="2" class="center">Output/Outcomes</th>
                <th rowspan="2">Success Indicator</th>
                <th rowspan="2">Target</th>
                <th rowspan="2">Actual Accomplishment</th>
                <th colspan="4" class="center">Rating</th>
                <th rowspan="2">Remarks</th>
            </tr>
            <tr>
                <th class="center">Sub Strategy</th>
                <th class="center">Program</th>
                <th class="center">Q</th>
                <th class="center">E</th>
                <th class="center">T</th>
                <th class="center">A</th>
            </tr>
        </thead>
        <tbody>
            <tr><td colspan="11" class="band">Strategic Function (30%)</td></tr>
            @foreach ($strategicIndicators as $indicator)
                @php($source = $indicator->performanceIndicator?->agencyOutcome ?? $indicator->agencyOutcome)
                <tr>
                    @if ($indicator->strategy_rowspan)
                        <td rowspan="{{ $indicator->strategy_rowspan }}">{{ $source?->dost_strategy_names_joined ?? '&mdash;' }}</td>
                    @endif
                    @if ($indicator->sub_strategy_rowspan)
                        <td rowspan="{{ $indicator->sub_strategy_rowspan }}">{{ $source?->dost_sub_strategy_descriptions_joined ?? '&mdash;' }}</td>
                    @endif
                    @if ($indicator->program_rowspan)
                        <td rowspan="{{ $indicator->program_rowspan }}">{{ $indicator->agencyOutcome?->outcome ?? '&mdash;' }}</td>
                    @endif
                    <td>{{ $indicator->description }}</td>
                    <td>{{ $indicator->target }}</td>
                    <td>{{ $indicator->displayed_accomplishment ?? '&mdash;' }}</td>
                    <td class="center">{{ $indicator->rating_quality ?? '&mdash;' }}</td>
                    <td class="center">{{ $indicator->rating_efficiency ?? '&mdash;' }}</td>
                    <td class="center">{{ $indicator->rating_timeliness ?? '&mdash;' }}</td>
                    <td class="center">{{ $indicator->rating_average ?? '&mdash;' }}</td>
                    <td>{{ $indicator->remarks ?? '&mdash;' }}</td>
                </tr>
            @endforeach

            <tr><td colspan="11" class="band">Core Function (50%)</td></tr>
            @foreach ($ipcr->coreItems as $item)
                @if ($item->success_indicator)
                    <tr>
                        @if ($item->function_rowspan)
                            <td rowspan="{{ $item->function_rowspan }}">{{ $item->label }}<br><small>Weight: {{ $item->weight_percent ?? '&mdash;' }}%</small></td>
                            <td rowspan="{{ $item->function_rowspan }}">&mdash;</td>
                            <td rowspan="{{ $item->function_rowspan }}">&mdash;</td>
                        @endif
                        <td>{{ $item->success_indicator }}</td>
                        <td>{{ $item->target ?? '&mdash;' }}</td>
                        <td>{{ $item->actual_accomplishment ?? '&mdash;' }} @if($item->mov_link) <br><small>MOV: {{ $item->mov_link }}</small> @endif</td>
                        <td class="center">{{ $item->quality_rating ?? '&mdash;' }}</td>
                        <td class="center">{{ $item->efficiency_rating ?? '&mdash;' }}</td>
                        <td class="center">{{ $item->timeliness_rating ?? '&mdash;' }}</td>
                        <td class="center">{{ $item->row_average ?? '&mdash;' }}</td>
                        <td>{{ $item->remarks ?? '&mdash;' }}</td>
                    </tr>
                @else
                    <tr>
                        <td rowspan="5">{{ $item->label }}<br><small>Weight: {{ $item->weight_percent ?? '&mdash;' }}%</small></td>
                        <td rowspan="5">&mdash;</td>
                        <td rowspan="5">&mdash;</td>
                        <td>Positive feedback from students (30%)</td>
                        <td rowspan="4">{{ $item->target }}</td>
                        <td rowspan="4">{{ $item->actual_accomplishment }} @if($item->mov_link) <br><small>MOV: {{ $item->mov_link }}</small> @endif</td>
                        <td class="center">&mdash;</td>
                        <td class="center">&mdash;</td>
                        <td class="center">&mdash;</td>
                        <td class="center">{{ $item->student_feedback_rating ?? '&mdash;' }}</td>
                        <td rowspan="5">{{ $item->remarks ?? '&mdash;' }}</td>
                    </tr>
                    <tr>
                        <td>Positive feedback from immediate supervisor (20%)</td>
                        <td class="center">&mdash;</td>
                        <td class="center">&mdash;</td>
                        <td class="center">&mdash;</td>
                        <td class="center">{{ $item->supervisor_feedback_rating ?? '&mdash;' }}</td>
                    </tr>
                    <tr>
                        <td>Instructional materials development (20%)</td>
                        <td class="center">&mdash;</td>
                        <td class="center">&mdash;</td>
                        <td class="center">&mdash;</td>
                        <td class="center">{{ $item->im_development_rating ?? '&mdash;' }}</td>
                    </tr>
                    <tr>
                        <td>Timely submission of forms and documents (30%)</td>
                        <td class="center">&mdash;</td>
                        <td class="center">&mdash;</td>
                        <td class="center">&mdash;</td>
                        <td class="center">{{ $item->timeliness_rating ?? '&mdash;' }}</td>
                    </tr>
                    <tr>
                        <td colspan="3"><strong>Row Average</strong></td>
                        <td class="center">&mdash;</td>
                        <td class="center">&mdash;</td>
                        <td class="center">&mdash;</td>
                        <td class="center"><strong>{{ $item->row_average ?? '&mdash;' }}</strong></td>
                    </tr>
                @endif
            @endforeach

            <tr><td colspan="11" class="band">Support Function (20%)</td></tr>
            @foreach ($ipcr->supportItems as $item)
                <tr>
                    @if ($item->function_rowspan)
                        <td rowspan="{{ $item->function_rowspan }}">{{ $item->label }}</td>
                        <td rowspan="{{ $item->function_rowspan }}">&mdash;</td>
                        <td rowspan="{{ $item->function_rowspan }}">&mdash;</td>
                    @endif
                    <td>{{ $item->success_indicator ?? '&mdash;' }}</td>
                    <td>{{ $item->target ?? '&mdash;' }}</td>
                    <td>{{ $item->actual_accomplishment ?? '&mdash;' }} @if($item->mov_link) <br><small>MOV: {{ $item->mov_link }}</small> @endif</td>
                    <td class="center">{{ $item->quality_rating ?? '&mdash;' }}</td>
                    <td class="center">{{ $item->efficiency_rating ?? '&mdash;' }}</td>
                    <td class="center">{{ $item->timeliness_rating ?? '&mdash;' }}</td>
                    <td class="center">{{ $item->row_average ?? '&mdash;' }}</td>
                    <td>{{ $item->remarks ?? '&mdash;' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p style="margin-top: 10px;"><strong>Rating Summary</strong></p>
    <table>
        <thead>
            <tr>
                <th>Agency Organizational Outcome</th>
                <th class="center">Quality</th>
                <th class="center">Efficiency</th>
                <th class="center">Timeliness</th>
                <th class="center">Average</th>
                <th>Equivalent</th>
            </tr>
        </thead>
        <tbody>
            <tr><td colspan="6" class="band">Strategic Functions (30%)</td></tr>
            @foreach ($summary['strategic'] as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td class="center">{{ $row['quality'] ?? '&mdash;' }}</td>
                    <td class="center">{{ $row['efficiency'] ?? '&mdash;' }}</td>
                    <td class="center">{{ $row['timeliness'] ?? '&mdash;' }}</td>
                    <td class="center">{{ $row['average'] ?? '&mdash;' }}</td>
                    <td>{{ $row['equivalent'] ?? '&mdash;' }}</td>
                </tr>
            @endforeach
            <tr><td colspan="6" class="band">Core Functions (50%)</td></tr>
            @foreach ($summary['core'] as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td class="center">{{ $row['quality'] ?? '&mdash;' }}</td>
                    <td class="center">{{ $row['efficiency'] ?? '&mdash;' }}</td>
                    <td class="center">{{ $row['timeliness'] ?? '&mdash;' }}</td>
                    <td class="center">{{ $row['average'] ?? '&mdash;' }}</td>
                    <td>{{ $row['equivalent'] ?? '&mdash;' }}</td>
                </tr>
            @endforeach
            <tr><td colspan="6" class="band">Support Functions (20%)</td></tr>
            @foreach ($summary['support'] as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td class="center">{{ $row['quality'] ?? '&mdash;' }}</td>
                    <td class="center">{{ $row['efficiency'] ?? '&mdash;' }}</td>
                    <td class="center">{{ $row['timeliness'] ?? '&mdash;' }}</td>
                    <td class="center">{{ $row['average'] ?? '&mdash;' }}</td>
                    <td>{{ $row['equivalent'] ?? '&mdash;' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p style="margin-top: 6px; font-size: 8px; font-style: italic;">
        Legend: 5 - Outstanding &nbsp; 4 - Very Satisfactory &nbsp; 3 - Satisfactory &nbsp; 2 - Unsatisfactory &nbsp; 1 - Poor
    </p>

    <table style="margin-top: 14px;">
        <tr>
            <td class="center"><strong>Discussed with</strong></td>
            <td class="center"><strong>Assessed by</strong></td>
            <td class="center"><strong>Final Rating by</strong></td>
        </tr>
        <tr>
            <td class="center" style="padding-top: 24px;">____________________</td>
            <td class="center" style="padding-top: 24px;">____________________</td>
            <td class="center" style="padding-top: 24px;">____________________</td>
        </tr>
    </table>
</body>
</html>
