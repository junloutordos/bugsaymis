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
        .signatures { margin-top: 30px; width: 100%; }
        .signatures td { border: none; text-align: center; padding-top: 20px; }
        .sig-name { border-top: 1px solid #000; display: inline-block; padding-top: 2px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>OFFICE PERFORMANCE COMMITMENT AND REVIEW (OPCR)</h1>
    <h2>FY {{ $fiscalYear }}</h2>

    <p class="commitment">
        {{ $settings->commitment_statement
            ?: "I, {$settings->campus_director_name}, Campus Director of the PSHS-Caraga Region Campus, commit to deliver and agree to be rated on the attainment of the following targets in accordance with the indicated measures for FY {$fiscalYear}." }}
    </p>

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
                <th>Q1</th>
                <th>Q2</th>
                <th>Q3</th>
                <th>Q4</th>
                <th>Q</th>
                <th>E</th>
                <th>T</th>
                <th>A</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($indicators as $indicator)
                <tr>
                    <td>{{ $indicator->subStrategy?->strategy?->pillar?->name ?? '—' }}</td>
                    <td>{{ $indicator->subStrategy?->strategy?->name ?? '—' }}</td>
                    <td>{{ $indicator->subStrategy?->description ?? '—' }}</td>
                    <td>{{ $indicator->agencyOutcome?->outcome ?? '—' }}</td>
                    <td>{{ $indicator->description }}</td>
                    <td>{{ $indicator->target ?? '—' }}</td>
                    <td>{{ $indicator->budget !== null ? number_format($indicator->budget, 2) : '—' }}</td>
                    <td>{{ $indicator->divisions->pluck('acronym')->implode(', ') ?: '—' }}</td>
                    @for ($q = 1; $q <= 4; $q++)
                        <td>{{ $indicator->actuals->firstWhere('quarter', $q)?->value ?? '—' }}</td>
                    @endfor
                    <td>{{ $indicator->rating_quality ?? '—' }}</td>
                    <td>{{ $indicator->rating_efficiency ?? '—' }}</td>
                    <td>{{ $indicator->rating_timeliness ?? '—' }}</td>
                    <td>{{ $indicator->rating_average ?? '—' }}</td>
                    <td>{{ $indicator->remarks ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="16" style="text-align:center;">No indicators tagged yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td style="width:33%;">
                <span class="sig-name">{{ $settings->campus_director_name ?? '—' }}</span><br>
                Campus Director
            </td>
            <td style="width:33%;">
                <span class="sig-name">{{ $settings->oic_campus_director_name ?? '—' }}</span><br>
                OIC-Campus Director
            </td>
            <td style="width:33%;">
                <span class="sig-name">{{ $settings->executive_director_name ?? '—' }}</span><br>
                Executive Director, PSHS System
            </td>
        </tr>
    </table>
</body>
</html>
