<html>
<head>
<style>
    body { font-family: Arial, sans-serif; font-size: 8pt; color: #222; }
    h2, h3, h4 { margin: 0; }

    /* ── Header ── */
    .doc-header { text-align: center; margin-bottom: 8px; }
    .doc-header h2 { font-size: 11pt; }
    .doc-header h3 { font-size: 10pt; font-weight: normal; }
    .doc-header h4 { font-size: 8pt; font-weight: normal; color: #555; margin-top: 2px; }

    /* ── Meta row ── */
    .meta-table { width: 100%; margin-bottom: 8px; border-collapse: collapse; }
    .meta-table td { padding: 1px 5px; font-size: 8pt; }

    /* ── Part banner ── */
    .part-banner { background: #dce8f7; font-size: 8pt; font-weight: bold; padding: 3px 5px; margin-top: 8px; margin-bottom: 2px; border: 1px solid #aac4e0; }
    .part-banner.part2 { background: #d9f0e5; border-color: #7fc5a0; }
    .part-note { font-size: 7pt; color: #555; margin-bottom: 4px; }

    /* ── Main table ── */
    table.ppmp { width: 100%; border-collapse: collapse; font-size: 7pt; }
    table.ppmp th, table.ppmp td { border: 1px solid #777; padding: 2px 3px; text-align: center; }
    table.ppmp th { background: #e8e8e8; font-weight: bold; font-size: 7pt; }
    table.ppmp td.desc { text-align: left; }
    table.ppmp td.amt  { text-align: right; }
    .cat-header   { background: #f0f0f0; font-weight: bold; text-align: left !important; }
    .subtotal-row { background: #f5f5f5; font-weight: bold; font-style: italic; }
    .part-total   { background: #d5e8f5; font-weight: bold; }
    .part2-total  { background: #c9f0da; font-weight: bold; }
    .grand-total  { background: #ddd; font-weight: bold; font-size: 8pt; }

    /* ── Footer / signatures ── */
    .sig-section { margin-top: 20px; }
    .sig-table { width: 100%; border-collapse: collapse; font-size: 8pt; }
    .sig-table td { padding: 3px 10px; vertical-align: bottom; width: 33%; }
    .sig-line { border-bottom: 1px solid #333; display: block; margin: 22px 0 2px; }
</style>
</head>
<body>
    {{-- ── Document Header ── --}}
    <div class="doc-header">
        <h2>{{ $agencyName }}</h2>
        <h3>PROJECT PROCUREMENT MANAGEMENT PLAN (PPMP)</h3>
        <h4>Annual Procurement Plan – Fiscal Year {{ $ppmp->fiscal_year }}</h4>
    </div>

    {{-- ── Meta ── --}}
    <table class="meta-table">
        <tr>
            <td><strong>PPMP No.:</strong> {{ $ppmp->ppmp_number }}</td>
            <td><strong>Fiscal Year:</strong> {{ $ppmp->fiscal_year }}</td>
            <td><strong>Status:</strong> {{ ucwords(str_replace('_', ' ', $ppmp->status)) }}</td>
        </tr>
        <tr>
            <td><strong>End-User/Unit:</strong> {{ $ppmp->division?->division_name }}</td>
            <td colspan="2"><strong>Project/Program/Activity:</strong> {{ $ppmp->title }}</td>
        </tr>
        @if($ppmp->is_supplemental)
        <tr>
            <td colspan="3"><strong>Type:</strong> Supplemental PPMP {{ $ppmp->parentPpmp ? '(Parent: '.$ppmp->parentPpmp->ppmp_number.')' : '' }}</td>
        </tr>
        @endif
    </table>

    @php
        $months = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];
        $monthLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $part1Items = $items->filter(fn($i) => $i->catalogue_id);
        $part2Items = $items->filter(fn($i) => !$i->catalogue_id);
        $part1Total = $part1Items->sum('total_cost');
        $part2Total = $part2Items->sum('total_cost');
        $categoryLabels = \App\Models\PPMP\PpmpItem::CATEGORY_LABELS;
        $procMethods    = \App\Models\PPMP\PpmpItem::PROCUREMENT_METHODS;
    @endphp

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- PART I                                                                 --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <div class="part-banner">PART I — Items Available from Procurement Service — DBM (Agency-to-Agency Procurement)</div>
    <div class="part-note">Common-Use Supplies and Equipment (CUSE) procured from PS-DBM at published catalogue prices. (RA 9184 Sec. 53.5; EO 359)</div>

    <table class="ppmp">
        <thead>
            <tr>
                <th style="width:50px">Stock No.</th>
                <th style="width:150px">Item Description</th>
                <th style="width:30px">Unit</th>
                @foreach($monthLabels as $ml)<th>{{ $ml }}</th>@endforeach
                <th style="width:35px">Total Qty</th>
                <th style="width:58px">Unit Cost (₱)</th>
                <th style="width:68px">Total Cost / ABC (₱)</th>
                <th style="width:35px">Fund Source</th>
                <th style="width:30px">Proc. Q</th>
                <th style="width:30px">Del. Q</th>
                <th style="width:55px">Remarks</th>
            </tr>
        </thead>
        <tbody>
            @forelse($part1Items as $item)
                <tr>
                    <td class="desc" style="font-family:monospace;font-size:6.5pt">{{ $item->code }}</td>
                    <td class="desc">{{ $item->description }}</td>
                    <td>{{ $item->unit }}</td>
                    @foreach($months as $m)
                        <td>{{ $item->$m > 0 ? $item->$m : '' }}</td>
                    @endforeach
                    <td>{{ $item->total_quantity }}</td>
                    <td class="amt">{{ number_format($item->unit_cost, 2) }}</td>
                    <td class="amt">{{ number_format($item->total_cost, 2) }}</td>
                    <td style="text-transform:uppercase;font-size:6.5pt">{{ $item->fund_source ?? 'MOOE' }}</td>
                    <td style="font-size:6.5pt">{{ $item->procurement_quarter ? 'Q'.$item->procurement_quarter : '' }}</td>
                    <td style="font-size:6.5pt">{{ $item->delivery_quarter ? 'Q'.$item->delivery_quarter : '' }}</td>
                    <td class="desc" style="font-size:6.5pt">{{ $item->remarks }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="17" style="text-align:center;font-style:italic;color:#888;padding:6px">No PS-DBM items.</td>
                </tr>
            @endforelse

            <tr class="part-total">
                <td colspan="15" style="text-align:right;font-size:7.5pt">PART I SUBTOTAL</td>
                <td class="amt" colspan="2">{{ number_format($part1Total, 2) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- PART II                                                                --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <div class="part-banner part2" style="margin-top:10px">PART II — Other Procurement Items (Not Available from PS-DBM)</div>
    <div class="part-note">Items procured through Competitive Bidding, Shopping, SVP, or other alternative modes as provided under RA 9184.</div>

    <table class="ppmp">
        <thead>
            <tr>
                <th style="width:35px">Code</th>
                <th style="width:150px">Item Description</th>
                <th style="width:30px">Unit</th>
                @foreach($monthLabels as $ml)<th>{{ $ml }}</th>@endforeach
                <th style="width:35px">Total Qty</th>
                <th style="width:58px">Unit Cost (₱)</th>
                <th style="width:68px">Total Cost / ABC (₱)</th>
                <th style="width:70px">Mode of Procurement</th>
                <th style="width:35px">Fund Source</th>
                <th style="width:30px">Proc. Q</th>
                <th style="width:30px">Del. Q</th>
                <th style="width:55px">Remarks</th>
            </tr>
        </thead>
        <tbody>
            @php
                $currentCat    = null;
                $catSubtotal   = 0;
                $part2ItemList = $part2Items->values();
            @endphp

            @foreach($part2ItemList as $item)
                @if($item->category !== $currentCat)
                    @if($currentCat !== null)
                        <tr class="subtotal-row">
                            <td colspan="15" style="text-align:right">Subtotal — {{ $categoryLabels[$currentCat] ?? $currentCat }}</td>
                            <td class="amt" colspan="2">{{ number_format($catSubtotal, 2) }}</td>
                            <td colspan="4"></td>
                        </tr>
                    @endif
                    @php $currentCat = $item->category; $catSubtotal = 0; @endphp
                    <tr>
                        <td colspan="19" class="cat-header">{{ $categoryLabels[$item->category] ?? $item->category }}</td>
                    </tr>
                @endif

                @php $catSubtotal += $item->total_cost; @endphp
                <tr>
                    <td class="desc" style="font-size:6.5pt">{{ $item->code }}</td>
                    <td class="desc">{{ $item->description }}</td>
                    <td>{{ $item->unit }}</td>
                    @foreach($months as $m)
                        <td>{{ $item->$m > 0 ? $item->$m : '' }}</td>
                    @endforeach
                    <td>{{ $item->total_quantity }}</td>
                    <td class="amt">{{ number_format($item->unit_cost, 2) }}</td>
                    <td class="amt">{{ number_format($item->total_cost, 2) }}</td>
                    <td class="desc" style="font-size:6pt">{{ $procMethods[$item->procurement_method] ?? $item->procurement_method }}</td>
                    <td style="text-transform:uppercase;font-size:6.5pt">{{ $item->fund_source ?? 'MOOE' }}</td>
                    <td style="font-size:6.5pt">{{ $item->procurement_quarter ? 'Q'.$item->procurement_quarter : '' }}</td>
                    <td style="font-size:6.5pt">{{ $item->delivery_quarter ? 'Q'.$item->delivery_quarter : '' }}</td>
                    <td class="desc" style="font-size:6.5pt">{{ $item->remarks }}</td>
                </tr>
            @endforeach

            @if($currentCat !== null)
                <tr class="subtotal-row">
                    <td colspan="15" style="text-align:right">Subtotal — {{ $categoryLabels[$currentCat] ?? $currentCat }}</td>
                    <td class="amt" colspan="2">{{ number_format($catSubtotal, 2) }}</td>
                    <td colspan="4"></td>
                </tr>
            @endif

            @if($part2Items->isEmpty())
                <tr>
                    <td colspan="19" style="text-align:center;font-style:italic;color:#888;padding:6px">No Part II items.</td>
                </tr>
            @endif

            <tr class="part2-total">
                <td colspan="15" style="text-align:right;font-size:7.5pt">PART II SUBTOTAL</td>
                <td class="amt" colspan="2">{{ number_format($part2Total, 2) }}</td>
                <td colspan="4"></td>
            </tr>

            <tr class="grand-total">
                <td colspan="15" style="text-align:right;font-size:8pt">GRAND TOTAL (ABC)</td>
                <td class="amt" colspan="2" style="font-size:8pt">{{ number_format($grandTotal, 2) }}</td>
                <td colspan="4"></td>
            </tr>
        </tbody>
    </table>

    {{-- ── Certification / Signature Block (APP-CSE prescribed format) ── --}}
    <div class="sig-section">
        <table class="sig-table">
            <tr>
                <td>
                    Prepared by:<br>
                    <span class="sig-line"></span>
                    <strong>{{ strtoupper($ppmp->preparer?->name ?? '') }}</strong><br>
                    {{ $ppmp->preparer?->position ?? 'Property / Supply Officer' }}<br>
                    Date: _______________
                </td>
                <td>
                    Reviewed/Certified by:<br>
                    <span class="sig-line"></span>
                    ___________________________<br>
                    BAC Chairperson / Accountant / Budget Officer<br>
                    Date: {{ $ppmp->bac_reviewed_at?->format('M d, Y') ?? '_______________' }}
                </td>
                <td>
                    Approved by:<br>
                    <span class="sig-line"></span>
                    {{ strtoupper($ppmp->approver?->name ?? '') ?: '___________________________' }}<br>
                    Head of Office / Agency<br>
                    Date: {{ $ppmp->approved_at?->format('M d, Y') ?? '_______________' }}
                </td>
            </tr>
        </table>
        <p style="font-size:7pt;margin-top:8px;color:#555">
            This PPMP is prepared in accordance with RA 9184 (Government Procurement Reform Act) and its Revised Implementing Rules and Regulations (2016 IRR).
            All items herein are consistent with the approved budget of the procuring entity for FY {{ $ppmp->fiscal_year }}.
        </p>
    </div>
</body>
</html>
