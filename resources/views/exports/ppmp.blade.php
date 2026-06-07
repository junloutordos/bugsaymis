<html>
<head>
<style>
    body { font-family: Arial, sans-serif; font-size: 8pt; color: #333; }
    .header { text-align: center; margin-bottom: 10px; }
    .header h2 { margin: 0; font-size: 11pt; }
    .header h3 { margin: 2px 0; font-size: 10pt; font-weight: normal; }
    .meta { font-size: 8pt; margin-bottom: 8px; }
    .meta td { padding: 1px 5px; }
    table.ppmp { width: 100%; border-collapse: collapse; font-size: 7pt; }
    table.ppmp th, table.ppmp td { border: 1px solid #666; padding: 2px 3px; text-align: center; }
    table.ppmp th { background: #e8e8e8; font-weight: bold; font-size: 7pt; }
    table.ppmp td.desc { text-align: left; }
    table.ppmp td.amt { text-align: right; }
    .cat-header { background: #f0f0f0; font-weight: bold; text-align: left !important; }
    .subtotal-row { background: #f5f5f5; font-weight: bold; }
    .grand-total { background: #ddd; font-weight: bold; font-size: 8pt; }
    .footer { margin-top: 20px; font-size: 8pt; }
    .footer td { padding: 3px 10px; vertical-align: top; }
    .sig-line { border-bottom: 1px solid #333; width: 180px; display: inline-block; margin-top: 25px; }
</style>
</head>
<body>
    <div class="header">
        <h2>{{ $agencyName }}</h2>
        <h3>PROJECT PROCUREMENT MANAGEMENT PLAN (PPMP)</h3>
    </div>

    <table class="meta">
        <tr>
            <td><strong>PPMP No:</strong> {{ $ppmp->ppmp_number }}</td>
            <td><strong>Fiscal Year:</strong> {{ $ppmp->fiscal_year }}</td>
        </tr>
        <tr>
            <td><strong>End-User/Unit:</strong> {{ $ppmp->division?->division_name }}</td>
            <td><strong>Project/Program/Activity:</strong> {{ $ppmp->title }}</td>
        </tr>
    </table>

    <table class="ppmp">
        <thead>
            <tr>
                <th style="width:35px">Code</th>
                <th style="width:140px">Item Description</th>
                <th style="width:35px">Unit</th>
                <th>Jan</th><th>Feb</th><th>Mar</th><th>Apr</th><th>May</th><th>Jun</th>
                <th>Jul</th><th>Aug</th><th>Sep</th><th>Oct</th><th>Nov</th><th>Dec</th>
                <th style="width:35px">Total Qty</th>
                <th style="width:55px">Unit Cost</th>
                <th style="width:65px">Total Cost (ABC)</th>
                <th style="width:75px">Mode of Procurement</th>
                <th style="width:35px">Fund</th>
                <th style="width:30px">Proc. Q</th>
                <th style="width:30px">Del. Q</th>
                <th style="width:60px">Remarks</th>
            </tr>
        </thead>
        <tbody>
            @php
                $currentCategory = null;
                $categorySubtotal = 0;
                $months = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];
            @endphp

            @foreach($items as $item)
                @if($item->category !== $currentCategory)
                    @if($currentCategory !== null)
                        {{-- Close previous category subtotal --}}
                        <tr class="subtotal-row">
                            <td colspan="16" style="text-align:right">Subtotal — {{ \App\Models\PPMP\PpmpItem::CATEGORY_LABELS[$currentCategory] ?? $currentCategory }}</td>
                            <td class="amt" colspan="2">{{ number_format($categorySubtotal, 2) }}</td>
                            <td colspan="5"></td>
                        </tr>
                    @endif
                    @php
                        $currentCategory = $item->category;
                        $categorySubtotal = 0;
                    @endphp
                    <tr>
                        <td colspan="23" class="cat-header">{{ \App\Models\PPMP\PpmpItem::CATEGORY_LABELS[$item->category] ?? $item->category }}</td>
                    </tr>
                @endif

                @php $categorySubtotal += $item->total_cost; @endphp

                <tr>
                    <td>{{ $item->code }}</td>
                    <td class="desc">{{ $item->description }}</td>
                    <td>{{ $item->unit }}</td>
                    @foreach($months as $m)
                        <td>{{ $item->$m > 0 ? $item->$m : '—' }}</td>
                    @endforeach
                    <td>{{ $item->total_quantity }}</td>
                    <td class="amt">{{ number_format($item->unit_cost, 2) }}</td>
                    <td class="amt">{{ number_format($item->total_cost, 2) }}</td>
                    <td style="font-size:6pt">{{ \App\Models\PPMP\PpmpItem::PROCUREMENT_METHODS[$item->procurement_method] ?? $item->procurement_method }}</td>
                    <td style="font-size:6pt;text-transform:uppercase">{{ $item->fund_source ?? 'MOOE' }}</td>
                    <td style="font-size:6pt">{{ $item->procurement_quarter ? 'Q'.$item->procurement_quarter : '—' }}</td>
                    <td style="font-size:6pt">{{ $item->delivery_quarter ? 'Q'.$item->delivery_quarter : '—' }}</td>
                    <td style="font-size:6pt">{{ $item->is_ps_dbm ? 'PS-DBM' : '' }} {{ $item->remarks }}</td>
                </tr>
            @endforeach

            @if($currentCategory !== null)
                <tr class="subtotal-row">
                    <td colspan="16" style="text-align:right">Subtotal — {{ \App\Models\PPMP\PpmpItem::CATEGORY_LABELS[$currentCategory] ?? $currentCategory }}</td>
                    <td class="amt" colspan="2">{{ number_format($categorySubtotal, 2) }}</td>
                    <td colspan="2"></td>
                </tr>
            @endif

            <tr class="grand-total">
                <td colspan="16" style="text-align:right">GRAND TOTAL</td>
                <td class="amt" colspan="2">{{ number_format($grandTotal, 2) }}</td>
                <td colspan="5"></td>
            </tr>
        </tbody>
    </table>

    <table class="footer" style="width:100%">
        <tr>
            <td style="width:33%">
                Prepared by:<br>
                <span class="sig-line">&nbsp;</span><br>
                <strong>{{ $ppmp->preparer?->name }}</strong><br>
                {{ $ppmp->preparer?->position }}<br>
                Date: _______________
            </td>
            <td style="width:33%">
                Reviewed by:<br>
                <span class="sig-line">&nbsp;</span><br>
                ___________________________<br>
                BAC Chairperson<br>
                Date: _______________
            </td>
            <td style="width:33%">
                Approved by:<br>
                <span class="sig-line">&nbsp;</span><br>
                {{ $ppmp->approver?->name ?? '___________________________' }}<br>
                Head of Procuring Entity<br>
                Date: {{ $ppmp->approved_at?->format('M d, Y') ?? '_______________' }}
            </td>
        </tr>
    </table>
</body>
</html>
