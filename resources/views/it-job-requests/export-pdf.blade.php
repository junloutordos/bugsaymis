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

    /* ── Main table ──
       Widths are set via width="" on <th> (most reliable in mPDF).
       CSS class widths are a fallback only. */
    .itjr-table {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
      margin-bottom: 16px;
    }
    .itjr-table th {
      background: #ddd;
      border: 1.5px solid #000;
      padding: 5px 4px;
      font-size: 9pt;
      font-weight: bold;
      text-align: center;
      vertical-align: middle;
    }
    .itjr-table td {
      border: 1px solid #666;
      padding: 5px 4px;
      font-size: 10pt;
      vertical-align: top;
      line-height: 1.4;
      word-break: break-word;
    }
    .itjr-table tr:nth-child(even) td { background: #f5f5f5; }
    .tc { text-align: center; }

    .no-data {
      text-align: center;
      padding: 28px;
      color: #888;
      font-size: 12pt;
    }

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
          <th width="3%"  class="tc">#</th>
          <th width="10%">ITJR #</th>
          <th width="19%">Request Title</th>
          <th width="11%">Category</th>
          <th width="11%">Submitted By</th>
          <th width="7%"  class="tc">Date Filed</th>
          <th width="19%">Action Taken</th>
          <th width="7%"  class="tc">Date Completed</th>
          <th width="7%"  class="tc">Status</th>
          <th width="6%"  class="tc">Rating</th>
        </tr>
      </thead>
      <tbody>
        @foreach($records as $i => $rec)
          @php
            $actionText = strip_tags($rec->action_taken ?? '');
          @endphp
          <tr>
            <td class="tc">{{ $i + 1 }}</td>
            <td>{{ $rec->itjr_no }}</td>
            <td>{{ $rec->title }}</td>
            <td>{{ $rec->category }}</td>
            <td>{{ $rec->user?->name ?? '—' }}</td>
            <td class="tc">{{ $rec->created_at ? \Carbon\Carbon::parse($rec->created_at)->format('m/d/Y') : '—' }}</td>
            <td>{{ $actionText ?: '—' }}</td>
            <td class="tc">{{ $rec->completed_at ? \Carbon\Carbon::parse($rec->completed_at)->format('m/d/Y') : '—' }}</td>
            <td class="tc">{{ $statusAbbrev[$rec->status] ?? $rec->status }}</td>
            <td class="tc">
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
