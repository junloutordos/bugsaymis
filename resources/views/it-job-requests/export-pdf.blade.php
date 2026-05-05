<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: Arial, Helvetica, sans-serif;
      font-size: 12pt;
      color: #000;
      background: #fff;
    }

    /* ── Outer wrapper table: full paper width, no extra spacing ── */
    #pt-wrap {
      width: 100%;
      border-collapse: collapse;
      border-spacing: 0;
    }

    /* Header / footer cells — zero padding so images bleed to paper edge */
    #pt-head, #pt-foot { padding: 0; margin: 0; border: none; }
    #pt-head img, #pt-foot img { width: 100%; display: block; }

    /* Body cell — inset 13mm left/right for readable content */
    #pt-body {
      padding: 10px 37px 14px;   /* 37px ≈ 13mm at 96dpi */
      vertical-align: top;
    }

    /* ── Report title ── */
    .report-title    { text-align: center; margin-bottom: 12px; }
    .report-title h2 { font-size: 15pt; font-weight: bold; letter-spacing: 1px; margin: 0 0 4px; }
    .report-subtitle { font-size: 11pt; color: #444; }

    /* ── Main table ── */
    .itjr-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 14px;
    }
    .itjr-table th {
      background: #e0e0e0;
      border: 1px solid #333;
      padding: 6px 7px;
      font-size: 11pt;
      font-weight: bold;
      text-align: center;
      vertical-align: middle;
    }
    .itjr-table td {
      border: 1px solid #555;
      padding: 6px 7px;
      font-size: 12pt;
      vertical-align: top;
      line-height: 1.45;
      word-break: break-word;
    }
    .itjr-table tr:nth-child(even) td { background: #f5f5f5; }

    /* Portrait A4 usable width ≈ 186mm (with 13mm padding each side).
       Column proportions chosen so even 12pt text wraps gracefully. */
    .col-no     { width: 11%; }
    .col-title  { width: 18%; }
    .col-cat    { width: 13%; }
    .col-by     { width: 13%; }
    .col-filed  { width: 8%; text-align: center; }
    .col-action { width: 19%; }
    .col-date   { width: 8%; text-align: center; }
    .col-status { width: 10%; text-align: center; }

    /* ── Signatures ── */
    .sig-label { font-size: 11pt; margin-bottom: 28px; }
    .sig-name  {
      font-weight: bold;
      font-size: 12pt;
      text-decoration: underline;
      border-bottom: 1px solid #000;
      padding-bottom: 2px;
      margin-bottom: 4px;
      display: inline-block;
      min-width: 180px;
    }
    .sig-pos { font-size: 11pt; color: #333; }

    .no-data {
      text-align: center;
      padding: 28px;
      color: #888;
      font-size: 12pt;
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
    'Rejected by Division Chief'      => 'Rejected DC',
    'Rejected by OCD'                 => 'Rejected OCD',
];
@endphp

<table id="pt-wrap">

  {{-- Repeating header — full paper width --}}
  <thead>
    <tr><td id="pt-head">
      <img src="{{ $headerPath }}" alt="" style="width:100%; display:block;">
    </td></tr>
  </thead>

  {{-- Repeating footer — full paper width --}}
  <tfoot>
    <tr><td id="pt-foot">
      <img src="{{ $footerPath }}" alt="" style="width:100%; display:block;">
    </td></tr>
  </tfoot>

  {{-- Body — padded for readability --}}
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
            <strong>{{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('F j, Y') : 'Present' }}</strong>
            &emsp;
          @endif
          @if($category)
            Category: <strong>{{ $category }}</strong>
          @endif
        </div>
      </div>

      {{-- Records --}}
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
                <td class="col-filed">
                  {{ $rec->created_at ? \Carbon\Carbon::parse($rec->created_at)->format('m/d/Y') : '—' }}
                </td>
                <td class="col-action">{{ $rec->action_taken ?? '—' }}</td>
                <td class="col-date">
                  {{ $rec->completed_at ? \Carbon\Carbon::parse($rec->completed_at)->format('m/d/Y') : '—' }}
                </td>
                <td class="col-status">{{ $statusAbbrev[$rec->status] ?? $rec->status }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif

      {{-- Signatures --}}
      <table style="width:100%; margin-top:28px;">
        <tr>
          <td style="width:50%; vertical-align:top; padding-right:28px;">
            <div class="sig-label">Prepared by:</div>
            <div class="sig-name">{{ strtoupper($preparedBy->name) }}</div>
            <div class="sig-pos">{{ $preparedBy->position ?? 'Personnel' }}</div>
          </td>
          <td style="width:50%; vertical-align:top;">
            <div class="sig-label">Noted by:</div>
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
