<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $travel->control_no }} Proposed IOT</title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #111827; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #111827; padding: 4px 5px; vertical-align: top; }
        .no-border td, .no-border th { border: 0; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: 700; }
        .title { font-size: 16px; font-weight: 700; letter-spacing: .02em; }
        .muted { color: #475569; }
        .line { border-bottom: 1px solid #111827; min-height: 18px; display: inline-block; padding: 0 8px; }
        .signature { height: 54px; vertical-align: bottom; text-align: center; }
        .small { font-size: 10px; }
        .nowrap { white-space: nowrap; }
        @media print { .print-button { display: none; } }
    </style>
</head>
<body onload="window.print()">
    <button class="print-button" onclick="window.print()">Print</button>

    <table class="no-border">
        <tr>
            <td></td>
            <td class="right bold" style="width: 180px;">Appendix 45</td>
        </tr>
        <tr>
            <td colspan="2" class="center title">PROPOSED ITINERARY OF TRAVEL</td>
        </tr>
    </table>

    <table class="no-border" style="margin-top: 14px;">
        <tr>
            <td>Entity Name : <span class="bold">Philippine Science High School Caraga Region Campus</span></td>
            <td class="right">No.: <span class="line">{{ $travel->control_no }}</span></td>
        </tr>
        <tr>
            <td>Fund Cluster: <span class="bold">{{ $travel->fund_cluster ?: '01101101' }}</span></td>
            <td></td>
        </tr>
    </table>

    <table style="margin-top: 8px;">
        <tr>
            <td colspan="5">Name : <span class="bold">{{ strtoupper($travel->traveler?->name ?? '') }}</span></td>
            <td colspan="5">Date of Travel : <span class="bold">{{ $travel->start_date?->format('F j') }}{{ $travel->start_date?->format('Y') !== $travel->end_date?->format('Y') ? ', ' . $travel->start_date?->format('Y') : '' }} - {{ $travel->end_date?->format('F j, Y') }}</span></td>
        </tr>
        <tr>
            <td colspan="5">Position : <span class="bold">{{ $travel->traveler?->position }}</span></td>
            <td colspan="5" rowspan="2">Purpose of Travel : <span class="bold">{{ $travel->purpose }}</span></td>
        </tr>
        <tr>
            <td colspan="5">Official Station : <span class="bold">PSHS-CRC</span></td>
        </tr>
    </table>

    <table style="margin-top: 10px;">
        <thead>
            <tr class="center bold">
                <th rowspan="2" style="width: 70px;">Date</th>
                <th colspan="2">Places to be visited<br><span class="small">(Destination)</span></th>
                <th colspan="2">T I M E</th>
                <th rowspan="2" style="width: 120px;">Means of<br>Transportation</th>
                <th rowspan="2" style="width: 88px;">Transportation</th>
                <th rowspan="2" style="width: 88px;">Per Diem</th>
                <th rowspan="2" style="width: 88px;">Others</th>
                <th rowspan="2" style="width: 92px;">Total Amount</th>
            </tr>
            <tr class="center bold">
                <th colspan="2"></th>
                <th style="width: 82px;">Departure</th>
                <th style="width: 82px;">Arrival</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($travel->itineraryItems as $item)
                <tr>
                    <td class="center nowrap">{{ $item->travel_date?->format('M j') }}</td>
                    <td colspan="2">{{ $item->places_to_visit }}</td>
                    <td class="center nowrap">{{ $item->departure_time ? \Carbon\Carbon::parse($item->departure_time)->format('g:i A') : '' }}</td>
                    <td class="center nowrap">{{ $item->arrival_time ? \Carbon\Carbon::parse($item->arrival_time)->format('g:i A') : '' }}</td>
                    <td>{{ $item->means_of_transportation }}</td>
                    <td class="right">{{ $item->transportation_cost > 0 ? number_format((float) $item->transportation_cost, 2) : '' }}</td>
                    <td class="right">{{ $item->per_diem > 0 ? number_format((float) $item->per_diem, 2) : '' }}</td>
                    <td class="right">{{ $item->other_cost > 0 ? number_format((float) $item->other_cost, 2) : '' }}</td>
                    <td class="right">{{ $item->total() > 0 ? number_format($item->total(), 2) : '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="center muted">No proposed itinerary rows recorded.</td>
                </tr>
            @endforelse

            @for ($i = $travel->itineraryItems->count(); $i < 12; $i++)
                <tr>
                    <td>&nbsp;</td><td colspan="2"></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
                </tr>
            @endfor
        </tbody>
        <tfoot>
            <tr class="bold">
                <td colspan="6" class="right">TOTAL</td>
                <td class="right">{{ number_format($totals['transportation'], 2) }}</td>
                <td class="right">{{ number_format($totals['per_diem'], 2) }}</td>
                <td class="right">{{ number_format($totals['others'], 2) }}</td>
                <td class="right">{{ number_format($totals['total'], 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <table class="no-border" style="margin-top: 12px;">
        <tr>
            <td style="width: 46%;">
                I certify that: (1) I have reviewed the foregoing itinerary, (2) the travel is necessary to the service,
                (3) the period covered is reasonable and (4) the expenses claimed are proper.
            </td>
            <td></td>
            <td class="center" style="width: 32%;">Prepared by:</td>
        </tr>
        <tr>
            <td></td>
            <td></td>
            <td class="signature">
                <div class="bold">{{ strtoupper($travel->traveler?->name ?? '') }}</div>
                <div>{{ $travel->traveler?->position }}</div>
            </td>
        </tr>
        <tr>
            <td class="center">Approved by:</td>
            <td></td>
            <td class="center">Noted / Reviewed by:</td>
        </tr>
        <tr>
            <td class="signature">
                <div class="bold">{{ strtoupper($travel->divisionChief?->name ?? '') }}</div>
                <div>Immediate Supervisor / Division Chief</div>
            </td>
            <td></td>
            <td class="signature">
                <div class="bold">FAD / OCD</div>
                <div>Authorized Official</div>
            </td>
        </tr>
    </table>
</body>
</html>
