<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: Arial, Helvetica, sans-serif;
      font-size: 10pt;
      color: #000;
      background: #fff;
    }

    /* ── Repeating page header/footer ── */
    #pt-wrap { width: 100%; border-collapse: collapse; }
    #pt-head, #pt-foot { padding: 0; }
    #pt-head img, #pt-foot img { width: 100%; display: block; }
    #pt-body { padding: 8px 0 12px; vertical-align: top; }

    /* ── Title ── */
    .report-title   { text-align: center; margin-bottom: 10px; }
    .report-title h2 {
      font-size: 13pt;
      font-weight: bold;
      letter-spacing: 1px;
      margin: 0 0 3px;
    }
    .report-subtitle { font-size: 9.5pt; color: #444; }

    /* ── Table ── */
    .itjr-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 12px;
    }
    .itjr-table th {
      background: #e8e8e8;
      border: 1px solid #333;
      padding: 5px 6px;
      font-size: 9pt;
      font-weight: bold;
      text-align: center;
      vertical-align: middle;
    }
    .itjr-table td {
      border: 1px solid #555;
      padding: 5px 6px;
      font-size: 9.5pt;
      vertical-align: top;
      line-height: 1.4;
      word-break: break-word;
    }
    .itjr-table tr:nth-child(even) td { background: #f7f7f7; }

    /* Portrait column widths (A4 usable ≈ 186mm with 12mm margins each side) */
    .col-no     { width: 12%; }
    .col-title  { width: 20%; }
    .col-cat    { width: 13%; }
    .col-by     { width: 13%; }
    .col-filed  { width: 9%; text-align: center; }
    .col-action { width: 20%; }
    .col-date   { width: 9%; text-align: center; }
    .col-status { width: 4%; }   {{-- handled with abbreviated text --}}

    .text-center { text-align: center; }

    /* ── Signatures ── */
    .sig-name {
      font-weight: bold;
      font-size: 10pt;
      text-decoration: underline;
      border-bottom: 1px solid #000;
      padding-bottom: 2px;
      margin-bottom: 3px;
      display: inline-block;
      min-width: 160px;
    }
    .sig-pos { font-size: 9pt; color: #333; }

    .no-data {
      text-align: center;
      padding: 24px;
      color: #888;
      font-size: 10pt;
    }
  </style>
</head>
<body>

@php
$statusAbbrev = [
    'Pending Division Chief Approval' => 'Pending DC',
    'Pending OCD Approval'            => 'Pending OCD',
    'In Progress'                     => 'In Progress',
    'MIS Assessed the Request'        => 'MIS Assessed',
    'Acted by MIS'                    => 'Acted',
    'Request Completed'               => 'Completed',
    'Rejected by Division Chief'      => 'Rejected (DC)',
    'Rejected by OCD'                 => 'Rejected (OCD)',
];
@endphp

<table id="pt-wrap">

  {{-- Repeating header --}}
  <thead>
    <tr><td id="pt-head">
      <img src="{{ $headerPath }}" alt="">
    </td></tr>
  </thead>

  {{-- Repeating footer --}}
  <tfoot>
    <tr><td id="pt-foot">
      <img src="{{ $footerPath }}" alt="">
    </td></tr>
  </tfoot>

  <tbody>
    <tr><td id="pt-body">

      {{-- Title --}}
      <div class="report-title">
        <h2>IT JOB REQUEST REPORT</h2>
        <div class="report-subtitle">
          @if($dateFrom || $dateTo)
            Period:
            <strong>{{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('F j, Y') : 'Beginning' }}</strong>
            &ndash;
            <strong>{{ $dateTo   ? \Carbon\Carbon::parse($dateTo)->format('F j, Y')   : 'Present' }}</strong>
            &emsp;
          @endif
          @if($category)
            Category: <strong>{{ $category }}</strong>
          @endif
        </div>
      </div>

      {{-- Records table --}}
      @if($records->isEmpty())
        <div class="no-data">No IT Job Request records found for the selected filters.</div>
      @else
        <table class="itjr-table">
          <thead>
            <tr>
              <th class="col-no">ITJR #</th>
              <th class="col-title">Request Title</th>
              <th class="col-cat">Category</th>
              <th class="col-by">Submitted By</th>
              <th class="col-filed">Date Filed</th>
              <th class="col-action">Action Taken</th>
              <th class="col-date">Date Completed</th>
              <th class="col-status">Status</th>
            </tr>
          </thead>
          <tbody>
            @foreach($records as $rec)
              <tr>
                <td class="col-no">{{ $rec->itjr_no }}</td>
                <td class="col-title">{{ $rec->title }}</td>
                <td class="col-cat">{{ $rec->category }}</td>
                <td class="col-by">{{ $rec->user?->name ?? '—' }}</td>
                <td class="col-filed text-center">
                  {{ $rec->created_at ? \Carbon\Carbon::parse($rec->created_at)->format('m/d/Y') : '—' }}
                </td>
                <td class="col-action">{{ $rec->action_taken ?? '—' }}</td>
                <td class="col-date text-center">
                  {{ $rec->completed_at ? \Carbon\Carbon::parse($rec->completed_at)->format('m/d/Y') : '—' }}
                </td>
                <td class="col-status">
                  {{ $statusAbbrev[$rec->status] ?? $rec->status }}
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif

      {{-- Signatures --}}
      <table style="width:100%; margin-top:24px;">
        <tr>
          <td style="width:50%; vertical-align:top; padding-right:24px;">
            <div style="font-size:9.5pt; margin-bottom:26px;">Prepared by:</div>
            <div class="sig-name">{{ strtoupper($preparedBy->name) }}</div>
            <div class="sig-pos">{{ $preparedBy->position ?? 'Personnel' }}</div>
          </td>
          <td style="width:50%; vertical-align:top;">
            <div style="font-size:9.5pt; margin-bottom:26px;">Noted by:</div>
            <div class="sig-name">{{ strtoupper($notedBy?->name ?? '') }}</div>
            <div class="sig-pos">{{ $notedBy?->position ?? 'Campus Director' }}</div>
          </td>
        </tr>
      </table>

    </td></tr>
  </tbody>
</table>

</body>
</html>
