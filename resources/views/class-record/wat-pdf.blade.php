<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: Arial, Helvetica, sans-serif;
      font-size: 8.5pt;
      color: #0f172a;
      background: #fff;
    }

    /* mPDF margins are 0 (header/footer images are full-bleed); this pads
       the actual content in from the left/right paper edge. */
    .page-body { padding: 1mm 10mm 0; }

    .wat-heading    { text-align: center; margin-bottom: 3mm; }
    /* font-weight: bold (not a numeric weight like 800) — mPDF's font
       substitution only reliably maps normal/bold, so a numeric weight can
       silently fall back to regular. */
    .wat-heading h1 { font-size: 13pt; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; margin: 0 0 2mm; }
    .wat-meta       { font-size: 8pt; text-align: left; }
    .wat-meta span  { margin: 0 9px; }

    /* Widths set via width="" on <th> — most reliable in mPDF. */
    .wat-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 4mm;
    }
    .wat-table th {
      background: #f1f5f9;
      border: 1px solid #94a3b8;
      padding: 4px 6px;
      font-size: 7.5pt;
      text-transform: uppercase;
      letter-spacing: 0.3px;
      text-align: left;
    }
    .wat-table td {
      border: 1px solid #94a3b8;
      padding: 4px 6px;
      font-size: 8.5pt;
      vertical-align: top;
    }
    /* mPDF repeats <thead> on every page automatically; this keeps a single
       assessment row from being split across a page break. */
    .wat-table tr { page-break-inside: avoid; }
    .wat-day    { font-weight: 600; white-space: nowrap; }
    .wat-empty  { color: #94a3b8; font-style: italic; }
    .wat-center { text-align: center; white-space: nowrap; }
    .wat-title  { font-weight: 700; }
    .wat-major  { font-weight: 700; color: #b91c1c; }

    /* Signatories — 3-column table, matches the .wat-signatories layout
       previously used in the browser-print version. Extra margin-top is the
       deliberate breathing room/break between the assessment table and the
       signature block. */
    .wat-signatories { width: 100%; margin-top: 16mm; }
    .wat-signatory   { width: 33.33%; text-align: center; vertical-align: top; padding: 0 6mm; }
    .wat-signatory-caption {
      text-align: left; font-size: 7.5pt; color: #334155; margin-bottom: 3mm;
    }
    /* Blank space for the wet signature, with the sign-line as its
       border-bottom — sits ABOVE the printed name, not under it. */
    .wat-signature-line { height: 11mm; border-bottom: 1px solid #0f172a; }
    /* No text-transform: uppercase here — PersonNameFormatter::assemble()
       already uppercases the base name in PHP; forcing it in CSS would also
       uppercase any pre-/post-nominal title, which must keep its original
       casing (e.g. "Dr." not "DR."). */
    .wat-signatory-name {
      font-size: 8.5pt; font-weight: bold; letter-spacing: 0.3px;
      margin-top: 2px;
    }
    .wat-signatory-position { font-size: 7pt; color: #475569; margin-top: 3px; }

    .wat-reviewed { margin-top: 4mm; font-size: 7.5pt; color: #475569; font-style: italic; }
  </style>
</head>
<body>

<div class="page-body">

  <div class="wat-heading">
    <h1><b>Weekly Assessment Tracker</b></h1>
    <div class="wat-meta">
      <span><strong>Section:</strong> Grade {{ $section['level'] }} — {{ $section['name'] }}</span>
      <span><strong>Week:</strong> {{ \Carbon\Carbon::parse($wat['week_start'])->format('F j, Y') }} – {{ \Carbon\Carbon::parse($wat['week_end'])->format('F j, Y') }}</span>
      <span><strong>School Year:</strong> {{ $schoolYear['name'] ?? '' }}</span>
    </div>
  </div>

  <table class="wat-table">
    <thead>
      <tr>
        <th width="10%">Day</th>
        <th width="9%">Time</th>
        <th width="13%">Subject</th>
        <th>Assessment</th>
        <th width="14%">Type of Assessment</th>
        <th width="8%">Category</th>
        <th width="12%">Teacher</th>
        <th width="9%" class="wat-center">% Compliance</th>
      </tr>
    </thead>
    <tbody>
      @foreach($wat['days'] as $day)
        @if(empty($day['items']))
          <tr>
            <td class="wat-day">{{ \Carbon\Carbon::parse($day['date'])->format('l, M j') }}</td>
            <td colspan="7" class="wat-empty">No assessments scheduled</td>
          </tr>
        @else
          @foreach($day['items'] as $idx => $item)
            <tr>
              @if($idx === 0)
                <td class="wat-day" rowspan="{{ count($day['items']) }}">{{ \Carbon\Carbon::parse($day['date'])->format('l, M j') }}</td>
              @endif
              <td class="wat-center">{{ $item['time_label'] ?? '—' }}</td>
              <td>{{ $item['subject_name'] }}</td>
              <td>
                <span class="wat-title">{{ $item['title'] }}</span>
                @if($item['is_major'])<span class="wat-major"> (Major)</span>@endif
              </td>
              <td>{{ $item['type_label'] }}</td>
              <td>{{ $item['is_graded'] ? 'Graded' : 'Non-graded' }}</td>
              <td>{{ $item['teacher_name'] }}</td>
              <td class="wat-center">{{ $item['compliance'] !== null ? $item['compliance'].'%' : '—' }}</td>
            </tr>
          @endforeach
        @endif
      @endforeach
    </tbody>
  </table>

  <table class="wat-signatories">
    <tr>
      <td class="wat-signatory">
        <div class="wat-signatory-caption">Consolidated by:</div>
        <div class="wat-signature-line">&nbsp;</div>
        <div class="wat-signatory-name"><b>{{ $coordinatorName ?? '' }}</b></div>
        <div class="wat-signatory-position">Homeroom Coordinator</div>
      </td>
      <td class="wat-signatory">
        <div class="wat-signatory-caption">Reviewed by:</div>
        <div class="wat-signature-line">&nbsp;</div>
        <div class="wat-signatory-name"><b>{{ $acidaaName ?? '' }}</b></div>
        <div class="wat-signatory-position">Assistant CID Chief for Academic Affairs</div>
      </td>
      <td class="wat-signatory">
        <div class="wat-signatory-caption">Approved by:</div>
        <div class="wat-signature-line">&nbsp;</div>
        <div class="wat-signatory-name"><b>{{ $cidChiefName ?? '' }}</b></div>
        <div class="wat-signatory-position">CID Chief</div>
      </td>
    </tr>
  </table>

  @if($wat['review'])
    <p class="wat-reviewed">
      Reviewed by {{ $wat['review']->reviewedBy?->name }} on
      {{ \Carbon\Carbon::parse($wat['review']->reviewed_at)->format('F j, Y') }}
      @if($wat['review']->remarks) — &ldquo;{{ $wat['review']->remarks }}&rdquo; @endif
    </p>
  @endif

</div>
</body>
</html>
