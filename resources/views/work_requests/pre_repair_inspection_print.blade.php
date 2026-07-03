<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Pre-Repair Inspection Report #{{ $workRequest->id }}</title>
  <style>
    body { font-family: Arial, Helvetica, sans-serif; color:#111; font-size:12px; }
    .page { width: 900px; margin: 0 auto; }
    .center { text-align:center; }
    .right { text-align:right; }
    h2, h3 { margin: 2px 0; }
    table { width:100%; border-collapse:collapse; }
    td, th { border:1px solid #222; padding:5px; vertical-align:top; }
    .noborder td { border:0; }
    .label { font-weight:700; }
    .muted { color:#555; font-size:11px; }
    .box { min-height:28px; }
    .section-title { background:#f1f5f9; font-weight:700; }
    .signature { height:56px; }
  </style>
</head>
<body onload="window.print()">
  <div class="page">
    <div class="center">
      <h2>PHILIPPINE SCIENCE HIGH SCHOOL SYSTEM</h2>
      <div>Campus/Office: PSHS - Caraga Region Campus</div>
      <h3>PRE-REPAIR INSPECTION REPORT</h3>
      <div class="muted">PSHS-00-F-GSM-14-Ver02-Rev1</div>
    </div>

    @if(! $inspection)
      <p class="center" style="margin-top:30px;">No pre-repair inspection report has been created for this work request.</p>
    @else
      <table style="margin-top:12px;">
        <tr>
          <td colspan="3" class="section-title">I. Reported/Verified by: Equipment Ownership/Supplier Officer/PPF Head</td>
          <td class="right">Reference: WR-{{ $workRequest->id }}</td>
        </tr>
        <tr>
          <td colspan="2" class="center label">BUILDING / FACILITIES</td>
          <td colspan="2" class="center label">EQUIPMENT / MACHINERY</td>
        </tr>
        <tr>
          <td class="label" style="width:20%;">Building/Facility</td>
          <td style="width:30%;">{{ $inspection->asset_type === 'building_facility' ? ($inspection->building_facility ?? '—') : '—' }}</td>
          <td class="label" style="width:20%;">Property No.</td>
          <td>{{ $inspection->property_no ?? '—' }}</td>
        </tr>
        <tr>
          <td class="label">Description</td>
          <td>{{ $inspection->description ?? $workRequest->description ?? '—' }}</td>
          <td class="label">Location</td>
          <td>{{ $inspection->location ?? '—' }}</td>
        </tr>
        <tr>
          <td class="label">Location of Defect</td>
          <td>{{ $inspection->location_of_defect ?? '—' }}</td>
          <td class="label">Serial No.</td>
          <td>{{ $inspection->serial_no ?? '—' }}</td>
        </tr>
        <tr>
          <td class="label">Date of Purchase</td>
          <td>{{ $inspection->date_of_purchase ? \Carbon\Carbon::parse($inspection->date_of_purchase)->format('F j, Y') : '—' }}</td>
          <td class="label">Purchase Cost</td>
          <td>{{ $inspection->purchase_cost ? 'Php ' . number_format((float) $inspection->purchase_cost, 2) : '—' }}</td>
        </tr>
        <tr>
          <td class="label">Warranty</td>
          <td colspan="3">
            {{ match($inspection->warranty_status) {
              'with_warranty' => 'With Warranty',
              'without_warranty' => 'Without Warranty',
              default => 'Unknown / Not indicated',
            } }}
          </td>
        </tr>
      </table>

      <table style="margin-top:10px;">
        <tr><td colspan="4" class="section-title">II. Inspector's Findings and Recommendations</td></tr>
        <tr>
          <td class="label" style="width:22%;">Nature of Defect</td>
          <td colspan="3">{{ $inspection->nature_of_defect ?? '—' }}</td>
        </tr>
        <tr>
          <td class="label">Cause of Defect</td>
          <td colspan="3">{{ $inspection->cause_of_defect ?? '—' }}</td>
        </tr>
        <tr>
          <td class="label">Recommendations</td>
          <td colspan="3">{{ $inspection->recommendations ?? '—' }}</td>
        </tr>
      </table>

      <table style="margin-top:10px;">
        <tr><td colspan="5" class="section-title">A. Estimated Cost of Repair Materials</td></tr>
        <tr>
          <th style="width:8%;">No.</th>
          <th>Item Description/Material</th>
          <th style="width:12%;">Qty</th>
          <th style="width:16%;">Unit Cost</th>
          <th style="width:16%;">Total</th>
        </tr>
        @forelse(($inspection->material_items ?? []) as $idx => $item)
          <tr>
            <td class="center">{{ $idx + 1 }}</td>
            <td>{{ $item['item_description'] ?? '' }}</td>
            <td class="right">{{ number_format((float) ($item['qty'] ?? 0), 2) }}</td>
            <td class="right">{{ number_format((float) ($item['unit_cost'] ?? 0), 2) }}</td>
            <td class="right">{{ number_format((float) ($item['total'] ?? 0), 2) }}</td>
          </tr>
        @empty
          <tr><td colspan="5" class="center muted">No repair materials listed.</td></tr>
        @endforelse
        <tr>
          <td colspan="4" class="right label">Total Estimated Cost - Materials</td>
          <td class="right label">Php {{ number_format((float) $inspection->total_material_cost, 2) }}</td>
        </tr>
      </table>

      <table style="margin-top:10px;">
        <tr><td colspan="5" class="section-title">B. Estimated Labor Cost</td></tr>
        <tr>
          <th style="width:8%;">No.</th>
          <th>Work Description / Scope of Work</th>
          <th style="width:14%;">No. of Man-days</th>
          <th style="width:16%;">Labor Rate</th>
          <th style="width:16%;">Total</th>
        </tr>
        @forelse(($inspection->labor_items ?? []) as $idx => $item)
          <tr>
            <td class="center">{{ $idx + 1 }}</td>
            <td>{{ $item['work_description'] ?? '' }}</td>
            <td class="right">{{ number_format((float) ($item['man_days'] ?? 0), 2) }}</td>
            <td class="right">{{ number_format((float) ($item['labor_rate'] ?? 0), 2) }}</td>
            <td class="right">{{ number_format((float) ($item['total'] ?? 0), 2) }}</td>
          </tr>
        @empty
          <tr><td colspan="5" class="center muted">No labor cost listed.</td></tr>
        @endforelse
        <tr>
          <td colspan="4" class="right label">Total Estimated Cost - Labor</td>
          <td class="right label">Php {{ number_format((float) $inspection->total_labor_cost, 2) }}</td>
        </tr>
        <tr>
          <td colspan="4" class="right label">C. Estimated Indirect Cost (Profit, Taxes, etc.)</td>
          <td class="right label">Php {{ number_format((float) $inspection->indirect_cost, 2) }}</td>
        </tr>
        <tr>
          <td colspan="4" class="right label">Total Estimated Repair Cost</td>
          <td class="right label">Php {{ number_format((float) $inspection->total_estimated_repair_cost, 2) }}</td>
        </tr>
      </table>

      <table style="margin-top:18px;">
        <tr>
          <td class="center signature">
            <div class="label">Inspected by:</div>
            <div style="margin-top:24px;">{{ optional($inspection->inspector)->name ?? '—' }}</div>
            <div class="muted">PPF Maintenance Personnel / Inspector</div>
            <div class="muted">Date: {{ $inspection->inspected_at ? $inspection->inspected_at->format('F j, Y') : '—' }}</div>
          </td>
          <td class="center signature">
            <div class="label">Noted by:</div>
            <div style="margin-top:24px;">{{ optional($inspection->notedBy)->name ?? '—' }}</div>
            <div class="muted">PPF Head</div>
            <div class="muted">Date: {{ $inspection->noted_at ? $inspection->noted_at->format('F j, Y') : '—' }}</div>
          </td>
        </tr>
      </table>
    @endif
  </div>
</body>
</html>
