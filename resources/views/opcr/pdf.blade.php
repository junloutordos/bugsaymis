<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 9px; }
        h1 { font-size: 13px; text-align: center; margin-bottom: 2px; }
        h2 { font-size: 10px; text-align: center; margin-top: 0; font-weight: normal; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #888; padding: 3px 4px; vertical-align: top; }
        th { background: #eee; font-size: 8px; text-transform: uppercase; }
        .commitment { margin-top: 10px; }
        .signatures td { border: none; padding: 4px 0; vertical-align: top; }
        .sig-block { margin-top: 20px; }
        .sig-name { border-top: 1px solid #000; display: inline-block; padding-top: 2px; font-weight: bold; }
        .legend { border: 1px solid #888; padding: 6px 10px; font-size: 8px; }
        .banner { margin-top: 15px; font-style: italic; font-weight: bold; }
    </style>
</head>
<body>
    <h1>OFFICE PERFORMANCE COMMITMENT AND REVIEW (OPCR)</h1>
    <h2>FY {{ $fiscalYear }}</h2>

    <p class="commitment">
        {{ $settings->commitment_statement
            ?: "I, {$settings->campus_director_name}, Campus Director of the PSHS-Caraga Region Campus, commit to deliver and agree to be rated on the attainment of the following targets in accordance with the indicated measures for FY {$fiscalYear}." }}
    </p>

    <table class="signatures">
        <tr>
            <td style="width:66%;"></td>
            <td style="width:34%;" class="sig-block">
                <span class="sig-name">{{ $settings->campus_director_name ?? '—' }}</span><br>
                Campus Director<br>
                PSHS Caraga Region Campus<br>
                Date:______________________________
            </td>
        </tr>
        <tr>
            <td class="sig-block">
                <span class="sig-name">{{ $settings->executive_director_name ?? '—' }}</span><br>
                Executive Director, PSHS System<br>
                Date:_____________________
            </td>
            <td>
                <div class="legend">
                    5 - Outstanding<br>
                    4 - Very Satisfactory<br>
                    3 - Satisfactory<br>
                    2 - Unsatisfactory<br>
                    1 - Poor
                </div>
            </td>
        </tr>
    </table>

    <p class="banner">AGENCY ORGANIZATIONAL OUTCOME: Increased Competitiveness of Filipinos in Science and Engineering</p>

    <table>
        <thead>
            <tr>
                <th>Pillar / Outcome</th>
                <th>Strategy</th>
                <th>Sub-Strategy</th>
                <th>PSHS Program</th>
                <th>Performance Indicator</th>
                <th>Target</th>
                <th>Budget</th>
                <th>Division</th>
                <th>Actual Accomplishment</th>
                <th>Q</th>
                <th>E</th>
                <th>T</th>
                <th>A</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @php $indicator = $row['indicator']; @endphp
                <tr>
                    @if ($row['show']['pillar'])
                        <td rowspan="{{ $row['rowspan']['pillar'] }}">{!! $row['pillar_text'] ? nl2br(e($row['pillar_text'])) : '—' !!}</td>
                    @endif
                    @if ($row['show']['strategy'])
                        <td rowspan="{{ $row['rowspan']['strategy'] }}">{{ $row['strategy_text'] ?? '—' }}</td>
                    @endif
                    @if ($row['show']['sub_strategy'])
                        <td rowspan="{{ $row['rowspan']['sub_strategy'] }}">{{ $row['sub_strategy_text'] ?? '—' }}</td>
                    @endif
                    @if ($row['show']['program'])
                        <td rowspan="{{ $row['rowspan']['program'] }}">{{ $indicator->agencyOutcome?->outcome ?? '—' }}</td>
                    @endif
                    <td>{{ $indicator->description }}</td>
                    <td>{{ $indicator->target ?? '—' }}</td>
                    <td>{{ $indicator->budget !== null ? number_format($indicator->budget, 2) : '—' }}</td>
                    <td>{{ $indicator->divisions->pluck('acronym')->implode(', ') ?: '—' }}</td>
                    <td>{{ $indicator->displayed_accomplishment ?? '—' }}</td>
                    <td>{{ $indicator->rating_quality ?? '—' }}</td>
                    <td>{{ $indicator->rating_efficiency ?? '—' }}</td>
                    <td>{{ $indicator->rating_timeliness ?? '—' }}</td>
                    <td>{{ $indicator->rating_average ?? '—' }}</td>
                    <td>{{ $indicator->remarks ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="14" style="text-align:center;">No indicators tagged yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
