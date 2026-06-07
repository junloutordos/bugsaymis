<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:Arial,sans-serif; font-size:8pt; color:#000; line-height:1.3; }
table { width:100%; border-collapse:collapse; }
td,th { border:1px solid #666; padding:2px 4px; vertical-align:middle; }
.no-border td { border:none; }
th { background:#e8e8e8; font-size:7.5pt; text-align:center; font-weight:bold; }
.header-agency { font-size:9pt; text-align:center; }
.form-title { font-size:11pt; font-weight:bold; text-align:center; text-transform:uppercase; margin:4px 0 2px; }
.center { text-align:center; }
.right { text-align:right; }
.left { text-align:left; }
.lowest { background:#d4edda; font-weight:bold; }
.awarded { background:#cce5ff; font-weight:bold; }
.item-desc { text-align:left; font-size:7.5pt; }
.supplier-name { font-size:7pt; font-weight:bold; }
.subtotal-row td { background:#f9f9f9; font-weight:bold; }
.grand-total td { background:#ddd; font-weight:bold; }
.sig-line { border-top:1px solid #000; margin-top:22px; padding-top:2px; text-align:center; font-size:7.5pt; }
</style>
</head>
<body>

<table class="no-border" style="margin-bottom:6px;">
  <tr>
    <td style="width:100%">
      <div class="header-agency">Republic of the Philippines</div>
      <div class="header-agency"><strong>Philippine Science High School – Caraga Region Campus</strong></div>
    </td>
  </tr>
</table>

<div class="form-title">Abstract of Quotations</div>
<div style="text-align:center;font-size:7.5pt;margin-bottom:6px;">(RA 9184 — Section 52/53 Shopping/Small Value Procurement)</div>

{{-- Meta --}}
<table class="no-border" style="font-size:8pt;margin-bottom:8px;">
  <tr>
    <td style="width:33%"><strong>RFQ No.:</strong> {{ $rfq->rfq_number }}</td>
    <td style="width:33%"><strong>Date:</strong> {{ $rfq->rfq_date?->format('F d, Y') }}</td>
    <td style="width:33%"><strong>PR Ref.:</strong> {{ $rfq->pr?->assigned_pr_number ?: $rfq->pr?->pr_no }}</td>
  </tr>
  <tr>
    <td colspan="2"><strong>Purpose:</strong> {{ $rfq->purpose }}</td>
    <td><strong>Unit:</strong> {{ $rfq->pr?->division?->division_name ?? '—' }}</td>
  </tr>
</table>

{{-- AoQ Table --}}
<table style="margin-bottom:12px; font-size:7pt;">
  <thead>
    <tr>
      <th rowspan="2" style="width:4%">Item</th>
      <th rowspan="2" style="width:6%">Unit</th>
      <th rowspan="2" style="width:6%">Qty</th>
      <th rowspan="2" class="item-desc" style="width:22%">Description</th>
      <th rowspan="2" style="width:8%">ABC<br>(per unit)</th>
      @foreach($suppliers as $supplier)
        <th colspan="2" class="supplier-name" style="width:{{ max(8, floor(46 / max(1,$suppliers->count()))) }}%">{{ $supplier->supplier_name }}</th>
      @endforeach
      <th rowspan="2" style="width:7%">Awarded To</th>
    </tr>
    <tr>
      @foreach($suppliers as $supplier)
        <th style="font-size:7pt">Unit Price</th>
        <th style="font-size:7pt">Total</th>
      @endforeach
    </tr>
  </thead>
  <tbody>
    @php $supplierTotals = []; foreach($suppliers as $s) { $supplierTotals[$s->id] = 0; } @endphp

    @foreach($matrix as $row)
      @php
        $item = $row['item'];
        $quotations = $row['quotations'];
        $lowestCost = $row['lowest_cost'];
        $awardedSupplierId = null;
        foreach($quotations as $sid => $q) {
            if ($q && $q['is_awarded']) { $awardedSupplierId = $sid; break; }
        }
        $awardedSupplier = $suppliers->firstWhere('id', $awardedSupplierId);
      @endphp
      <tr>
        <td class="center">{{ $item->item_no }}</td>
        <td class="center">{{ $item->unit }}</td>
        <td class="center">{{ $item->quantity }}</td>
        <td class="item-desc">{{ $item->description }}</td>
        <td class="right">{{ $item->abc > 0 ? number_format($item->abc / max(1,$item->quantity),2) : '—' }}</td>
        @foreach($suppliers as $supplier)
          @php
            $q = $quotations[$supplier->id] ?? null;
            $uc = $q ? $q['unit_cost'] : null;
            $tc = $q ? $q['total_cost'] : null;
            $isLowest = $uc && $lowestCost && abs($uc - $lowestCost) < 0.005;
            $isAwarded = $q && $q['is_awarded'];
            if ($tc) $supplierTotals[$supplier->id] = ($supplierTotals[$supplier->id] ?? 0) + $tc;
            $cellClass = $isAwarded ? 'awarded' : ($isLowest ? 'lowest' : '');
          @endphp
          <td class="right {{ $cellClass }}">{{ $uc ? number_format($uc,2) : '—' }}</td>
          <td class="right {{ $cellClass }}">{{ $tc ? number_format($tc,2) : '—' }}</td>
        @endforeach
        <td class="center" style="font-size:7pt">{{ $awardedSupplier?->supplier_name ?? '—' }}</td>
      </tr>
    @endforeach

    {{-- Supplier totals row --}}
    <tr class="subtotal-row">
      <td colspan="5" class="right">Total Quoted Amount:</td>
      @foreach($suppliers as $supplier)
        <td colspan="2" class="right">{{ isset($supplierTotals[$supplier->id]) && $supplierTotals[$supplier->id] > 0 ? '₱'.number_format($supplierTotals[$supplier->id],2) : '—' }}</td>
      @endforeach
      <td></td>
    </tr>
  </tbody>
</table>

<div style="font-size:7.5pt;margin-bottom:4px;">
  <span style="display:inline-block;width:14px;height:10px;background:#cce5ff;border:1px solid #666;margin-right:4px;"></span>Awarded &nbsp;&nbsp;
  <span style="display:inline-block;width:14px;height:10px;background:#d4edda;border:1px solid #666;margin-right:4px;"></span>Lowest Quoted Price
</div>

{{-- Signature blocks --}}
<table class="no-border" style="margin-top:16px; font-size:8pt;">
  <tr>
    <td style="width:33%; text-align:center; padding:0 10px;">
      <div class="sig-line">Prepared by</div>
      <strong>BAC Secretariat</strong><br>
      Date: _______________
    </td>
    <td style="width:33%; text-align:center; padding:0 10px;">
      <div class="sig-line">Reviewed by</div>
      <strong>BAC Chairperson</strong><br>
      Date: _______________
    </td>
    <td style="width:33%; text-align:center; padding:0 10px;">
      <div class="sig-line">Approved by</div>
      <strong>Head of Procuring Entity</strong><br>
      Date: {{ $rfq->awarded_at?->format('m/d/Y') ?? '_______________' }}
    </td>
  </tr>
</table>

</body>
</html>
