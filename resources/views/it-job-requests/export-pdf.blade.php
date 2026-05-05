<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: Arial, Helvetica, sans-serif;
      font-size: 9pt;
      color: #000;
      background: #fff;
    }

    /* ── Repeating page header/footer via table thead/tfoot ── */
    #pt-wrap { width: 100%; border-collapse: collapse; }

    #pt-head, #pt-foot { padding: 0; }
    #pt-head img, #pt-foot img { width: 100%; display: block; }

    #pt-body { padding: 6px 0 10px; vertical-align: top; }

    /* ── Report title block ── */
    .report-title {
      text-align: center;
      margin-bottom: 8px;
    }
    .report-title h2 {
      font-size: 12pt;
      font-weight: bold;
      letter-spacing: 1px;
      margin: 0 0 2px;
    }
    .report-subtitle {
      font-size: 9pt;
      color: #444;
    }

    /* ── Main table ── */
    .itjr-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 10px;
    }
    .itjr-table th {
      background: #f0f0f0;
      border: 1px solid #000;
      padding: 4px 5px;
      font-size: 8pt;
      font-weight: bold;
      text-align: center;
      vertical-align: middle;
    }
    .itjr-table td {
      border: 1px solid #000;
      padding: 3px 5px;
      font-size: 8pt;
      vertical-align: top;
      word-break: break-word;
    }
    .itjr-table tr:nth-child(even) td { background: #fafafa; }

    .col-no      { width: 10%; }
    .col-title   { width: 17%; }
    .col-cat     { width: 12%; }
    .col-by      { width: 12%; }
    .col-filed   { width: 9%; text-align: center; }
    .col-action  { width: 17%; }
    .col-date    { width: 9%; text-align: center; }
    .col-status  { width: 14%; text-align: center; }

    /* ── Signature section ── */
    .sig-section {
      margin-top: 18px;
      display: flex;
      gap: 60px;
    }
    .sig-block { min-width: 180px; }
    .sig-label { font-size: 8.5pt; margin-bottom: 20px; }
    .sig-name  {
      font-weight: bold;
      font-size: 9.5pt;
      text-decoration: underline;
      border-bottom: 1px solid #000;
      padding-bottom: 2px;
      margin-bottom: 3px;
      min-width: 160px;
      display: inline-block;
    }
    .sig-pos   { font-size: 8pt; color: #333; }

    .no-data {
      text-align: center;
      padding: 20px;
      color: #888;
      font-size: 9pt;
    }
  </style>
</head>
<body>

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

  {{-- Body --}}
  <tbody>
    <tr><td id="pt-body">

      {{-- Title --}}
      <div class="report-title">
        <h2>IT JOB REQUEST REPORT</h2>
        <div class="report-subtitle">
          @if($dateFrom || $dateTo)
            Period:
            {{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('F j, Y') : 'Start' }}
            –
            {{ $dateTo   ? \Carbon\Carbon::parse($dateTo)->format('F j, Y')   : 'Present' }}
            &nbsp;&nbsp;
          @endif
          @if($category)
            Category: {{ $category }}
          @endif
        </div>
      </div>

      {{-- Records table --}}
      @if($records->isEmpty())
        <div class="no-data">No IT Job Request records found for the selected period.</div>
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
                <td class="col-status">{{ $rec->status }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif

      {{-- Signatures --}}
      <table style="width:100%; margin-top:20px;">
        <tr>
          <td style="width:50%; vertical-align:top; padding-right:20px;">
            <div style="font-size:8.5pt; margin-bottom:22px;">Prepared by:</div>
            <div class="sig-name">{{ strtoupper($preparedBy->name) }}</div>
            <div class="sig-pos">{{ $preparedBy->position ?? 'Personnel' }}</div>
          </td>
          <td style="width:50%; vertical-align:top;">
            <div style="font-size:8.5pt; margin-bottom:22px;">Noted by:</div>
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
