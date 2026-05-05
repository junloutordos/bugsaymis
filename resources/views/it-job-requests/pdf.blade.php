<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: Arial, Helvetica, sans-serif;
      font-size: 11px;
      color: #000;
      background: #fff;
    }

    /* ── Header ─────────────────────────────── */
    .header {
      text-align: center;
      margin-bottom: 16px;
      line-height: 1.6;
    }
    .header .org   { font-size: 13px; font-weight: bold; }
    .header .title { font-size: 13px; font-weight: bold; letter-spacing: 3px; margin-top: 10px; }

    /* ── Table ──────────────────────────────── */
    .ft {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
    }

    /* Fixed column widths via colgroup */
    col.c1 { width: 33%; }
    col.c2 { width: 28%; }
    col.c3 { width: 39%; }

    .ft td {
      border: 1px solid #000;
      padding: 6px 10px;
      vertical-align: top;
      word-wrap: break-word;
    }

    .lbl  { font-size: 10.5px; color: #333; }
    .val  { font-weight: bold; font-size: 11px; word-break: break-word; }
    .uline{ text-decoration: underline; }

    /* ── Text value rows (label + value stacked) ── */
    .field-label { font-size: 10px; color: #555; margin-bottom: 3px; }
    .field-value { font-weight: bold; font-size: 11px; text-decoration: underline; word-break: break-word; }
    .field-value-plain { font-weight: bold; font-size: 11px; word-break: break-word; }

    /* ── Signature block ────────────────────── */
    .sig-cell    { text-align: center; padding: 4px 6px 4px; }
    .sig-cell img { display: block; margin: 0 auto 2px; max-height: 30px; max-width: 90px; }
    .sig-gap     { height: 30px; }
    .sig-name    {
      display: inline-block;
      font-weight: bold;
      font-size: 11.5px;
      border-bottom: 1.5px solid #000;
      padding-bottom: 1px;
      margin-top: 2px;
      min-width: 110px;
    }
    .sig-pos     { font-size: 10px; margin-top: 2px; color: #333; }

    /* ── Section label rows ─────────────────── */
    .section-label { font-size: 10px; color: #333; font-weight: normal; padding: 5px 10px 3px; border-bottom: none; }

    /* ── Footer ─────────────────────────────── */
    .footer { font-size: 9px; color: #555; margin-top: 6px; }
  </style>
</head>
<body>

  {{-- Header --}}
  <div class="header">
    <div class="org">PHILIPPINE SCIENCE HIGH SCHOOL SYSTEM</div>
    <div class="org">CAMPUS: <span style="text-decoration:underline;">CARAGA REGION</span></div>
    <div class="title">IT  JOB  REQUEST  FORM</div>
  </div>

  <table class="ft">
    <colgroup>
      <col class="c1">
      <col class="c2">
      <col class="c3">
    </colgroup>

    {{-- Row 1: Requested by | ITJRF # --}}
    <tr>
      <td colspan="2" style="border-right:1px solid #000;">
        <div class="field-label">Requested by:</div>
        <div class="val">{{ strtoupper($jobRequest->user->name ?? '—') }}</div>
        @if($jobRequest->user?->division?->division_name)
          <div style="font-size:10px; color:#555; margin-top:2px;">
            {{ $jobRequest->user->division->division_name }}
          </div>
        @endif
      </td>
      <td>
        <div class="field-label">ITJRF #:</div>
        <div class="val">{{ $jobRequest->itjr_no }}</div>
      </td>
    </tr>

    {{-- Row 2: Approved by (DC) | Date --}}
    <tr>
      <td colspan="2" style="border-right:1px solid #000;">
        <div class="field-label" style="margin-bottom:4px;">Approved by (Division Chief):</div>
        <div class="sig-cell">
          @if($dcSig)
            <img src="{{ $dcSig }}" alt="">
          @else
            <div class="sig-gap"></div>
          @endif
          <div><span class="sig-name">{{ strtoupper($jobRequest->divisionChief?->name ?? '') }}</span></div>
          <div class="sig-pos">{{ $jobRequest->divisionChief?->position ?? 'Division Chief' }}</div>
        </div>
      </td>
      <td style="vertical-align:middle;">
        <div class="field-label">Date:</div>
        <div class="val">
          {{ $jobRequest->dc_approval_date
              ? \Carbon\Carbon::parse($jobRequest->dc_approval_date)->format('F j, Y')
              : '' }}
        </div>
      </td>
    </tr>

    {{-- Row 3: Request / Problem --}}
    <tr>
      <td colspan="3">
        <div class="field-label">Request / Problem:</div>
        <div class="field-value">{{ $jobRequest->description ?? '—' }}</div>
      </td>
    </tr>

    {{-- Row 4: Assessment --}}
    <tr>
      <td colspan="3">
        <div class="field-label">Assessment:</div>
        <div class="field-value">{{ $jobRequest->mis_assessment ?? '—' }}</div>
      </td>
    </tr>

    {{-- Row 5: Recommendation --}}
    <tr>
      <td colspan="3">
        <div class="field-label">Recommendation:</div>
        <div class="field-value-plain">{{ $recommendation }}</div>
      </td>
    </tr>

    {{-- Row 6: Section labels for signature row --}}
    <tr>
      <td class="section-label">Assigned Staff (IT/ISA):</td>
      <td class="section-label" style="text-align:center;">Target Date of Completion:</td>
      <td class="section-label" style="text-align:center;">Approved by:</td>
    </tr>

    {{-- Row 7: Assigned staff sig | Target date | Director sig --}}
    <tr>
      <td style="border-top:none; text-align:center; padding-top:0;">
        <div class="sig-cell">
          @if($assignedSig)
            <img src="{{ $assignedSig }}" alt="">
          @else
            <div class="sig-gap"></div>
          @endif
          <div><span class="sig-name">{{ strtoupper($jobRequest->assignedTo?->name ?? '') }}</span></div>
          <div class="sig-pos">{{ $jobRequest->assignedTo?->position ?? 'IT/ISA Staff' }}</div>
        </div>
      </td>
      <td style="border-top:none; text-align:center; vertical-align:middle;">
        @if($jobRequest->expected_completion_date)
          <span class="val uline">
            {{ \Carbon\Carbon::parse($jobRequest->expected_completion_date)->format('F j, Y') }}
          </span>
        @endif
      </td>
      <td style="border-top:none; text-align:center; padding-top:0;">
        <div class="sig-cell">
          @if($directorSig)
            <img src="{{ $directorSig }}" alt="">
          @else
            <div class="sig-gap"></div>
          @endif
          <div><span class="sig-name">{{ strtoupper($director?->name ?? '') }}</span></div>
          <div class="sig-pos">{{ $director?->position ?? 'Campus Director' }}</div>
        </div>
      </td>
    </tr>

    {{-- Row 8: Action Taken --}}
    <tr>
      <td colspan="3">
        <div class="field-label">Action Taken:</div>
        <div class="field-value">{{ $jobRequest->action_taken ?? '—' }}</div>
      </td>
    </tr>

    {{-- Row 9: Status / Condition --}}
    <tr>
      <td colspan="3">
        <div class="field-label">Status / Condition:</div>
        <div class="field-value-plain">{{ $jobRequest->status ?? '—' }}</div>
      </td>
    </tr>

    {{-- Row 10: Section labels --}}
    <tr>
      <td class="section-label">Date Completed:</td>
      <td class="section-label" style="text-align:center;">Serviced By:</td>
      <td class="section-label" style="text-align:center;">Confirmed by User:</td>
    </tr>

    {{-- Row 11: Date completed | Serviced by (name + position) | Confirmed by user --}}
    <tr>
      <td style="border-top:none; vertical-align:middle; padding:8px 10px;">
        @if($jobRequest->completed_at)
          <span class="val uline">
            {{ \Carbon\Carbon::parse($jobRequest->completed_at)->format('F j, Y') }}
          </span>
        @endif
      </td>
      <td style="border-top:none; text-align:center; vertical-align:middle; padding:8px 6px;">
        <div class="val">{{ strtoupper($jobRequest->assignedTo?->name ?? '—') }}</div>
        @if($jobRequest->assignedTo?->position)
          <div class="sig-pos" style="margin-top:3px;">{{ $jobRequest->assignedTo->position }}</div>
        @endif
      </td>
      <td style="border-top:none; text-align:center; vertical-align:middle; padding:8px 6px;">
        <div class="val">{{ strtoupper($jobRequest->user?->name ?? '—') }}</div>
      </td>
    </tr>

  </table>

  <div class="footer">PSHS-00-F-ITU-01-Ver02-Rev2-12/31/21</div>

</body>
</html>
