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

    /* 0.5 inch left/right padding — mPDF margins are 0 so header/footer can be full-width */
    .page-body { padding: 10px 12.7mm 14px; }

    /* ── Report title ── */
    .report-title    { text-align: center; margin-bottom: 14px; }
    .report-title h2 { font-size: 15pt; font-weight: bold; letter-spacing: 1px; margin: 0 0 4px; }
    .report-subtitle { font-size: 12pt; color: #444; }

    /* ── Main table ── */
    .itjr-table {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;   /* enforces column widths strictly in mPDF */
      margin-bottom: 16px;
    }
    .itjr-table th {
      background: #ddd;
      border: 1.5px solid #000;
      padding: 7px 8px;
      font-size: 12pt;
      font-weight: bold;
      text-align: center;
      vertical-align: middle;
    }
    .itjr-table td {
      border: 1px solid #666;
      padding: 7px 8px;
      font-size: 13pt;
      vertical-align: top;
      line-height: 1.5;
      word-break: break-word;
    }
    .itjr-table tr:nth-child(even) td { background: #f5f5f5; }

    /* Portrait A4 usable ≈ 184.6mm; table-layout:fixed makes widths exact */
    .col-seq    { width: 4%;  text-align: center; }
    .col-no     { width: 9%; }
    .col-title  { width: 19%; }
    .col-cat    { width: 10%; }
    .col-by     { width: 10%; }
    .col-filed  { width: 7%;  text-align: center; }
    .col-action { width: 19%; }
    .col-date   { width: 7%;  text-align: center; }
    .col-status { width: 8%;  text-align: center; }
    .col-rating { width: 7%;  text-align: center; }

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

<div class="page-body">

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

  {{-- Records table --}}
  @if($records->isEmpty())
    <div class="no-data">No IT Job Request records found for the selected filters.</div>
  @else
    <table class="itjr-table">
      <thead>
        <tr>
          <th class="col-seq">#</th>
          <th class="col-no">ITJR #</th>
          <th class="col-title">Request Title</th>
          <th class="col-cat">Category</th>
          <th class="col-by">Submitted By</th>
          <th class="col-filed">Date Filed</th>
          <th class="col-action">Action Taken</th>
          <th class="col-date">Date Completed</th>
          <th class="col-status">Status</th>
          <th class="col-rating">Rating</th>
        </tr>
      </thead>
      <tbody>
        @foreach($records as $i => $rec)
          <tr>
            <td class="col-seq">{{ $i + 1 }}</td>
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
            <td class="col-rating">
              @if($rec->rating)
                {{ number_format($rec->rating, 1) }} ★
              @else
                —
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif

  {{-- Signature blocks — names and positions only; wet signatures applied on the printed copy --}}
  <table style="width:100%; margin-top:36px;">
    <tr>
      <td style="width:50%; vertical-align:top; padding-right:30px;">
        <div style="font-size:11pt;">Prepared by:</div>
        <div style="height:50px;"></div>
        <br>
        <br>
        <div style="border-bottom:1.5px solid #000; min-width:180px; display:inline-block; padding-bottom:2px; margin-bottom:4px; font-weight:bold; font-size:12pt; text-decoration:underline;">
          {{ strtoupper($preparedBy->name) }}
        </div>
        <div style="font-size:11pt; color:#333;">{{ $preparedBy->position ?? 'Personnel' }}</div>
      </td>
      <td style="width:50%; vertical-align:top;">
        <div style="font-size:11pt;">Noted by:</div>
        <div style="height:50px;"></div>
        <br>
        <br>
        <div style="border-bottom:1.5px solid #000; min-width:180px; display:inline-block; padding-bottom:2px; margin-bottom:4px; font-weight:bold; font-size:12pt; text-decoration:underline;">
          {{ strtoupper($notedBy?->name ?? '') }}
        </div>
        <div style="font-size:11pt; color:#333;">{{ $notedBy?->position ?? 'Campus Director' }}</div>
      </td>
    </tr>
  </table>

</div>
</body>
</html>
