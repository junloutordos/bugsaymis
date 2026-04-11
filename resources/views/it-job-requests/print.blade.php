<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>IT JRF — {{ $jobRequest->itjr_no }}</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: Arial, Helvetica, sans-serif;
      font-size: 11px;
      color: #000;
      background: #fff;
    }

    .page {
      width: 700px;
      margin: 28px auto;
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
    /*  3 fixed columns: 33% | 28% | 39%         */
    .ft {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
    }
    .ft colgroup col:nth-child(1) { width: 33%; }
    .ft colgroup col:nth-child(2) { width: 28%; }
    .ft colgroup col:nth-child(3) { width: 39%; }

    .ft td {
      border: 1px solid #000;
      padding: 6px 10px;
      vertical-align: top;
    }

    .lbl { font-size: 10.5px; }

    /* label + value inline */
    .kv         { display: flex; gap: 6px; align-items: baseline; flex-wrap: wrap; }
    .kv .v      { flex: 1; word-break: break-word; }
    .uline      { text-decoration: underline; }
    .bold       { font-weight: bold; }

    /* ── Signature block ────────────────────── */
    .sig {
      text-align: center;
      padding: 2px 4px 6px;
    }
    .sig img {
      display: block;
      margin: 0 auto 0;
      max-height: 54px;
      max-width: 160px;
      object-fit: contain;
    }
    .sig .gap    { height: 56px; }
    .sig .name   {
      display: inline-block;
      font-weight: bold;
      font-size: 11.5px;
      border-bottom: 1.5px solid #000;
      padding-bottom: 1px;
      margin-top: 3px;
    }
    .sig .pos    { font-size: 10.5px; margin-top: 3px; }

    /* ── Footer ─────────────────────────────── */
    .footer { font-size: 9px; color: #555; margin-top: 6px; }

    @media print {
      body  { margin: 0; }
      .page { margin: 0; padding: 12px 16px; width: 100%; }
    }
  </style>
</head>
<body>
<div class="page">

  {{-- Header --}}
  <div class="header">
    <div class="org">PHILIPPINE SCIENCE HIGH SCHOOL SYSTEM</div>
    <div class="org">CAMPUS: <span style="text-decoration:underline;">CARAGA REGION</span></div>
    <div class="title">IT  JOB  REQUEST  FORM</div>
  </div>

  {{--
    TABLE STRUCTURE (3 columns: 33% | 28% | 39%)
    ┌──────────────────────────┬─────────────────────────────┐
    │ Requested by   [col1+2]  │ IT JRF #           [col3]   │
    ├──────────────────────────┼─────────────────────────────┤
    │ Approved by    [col1+2]  │ Date               [col3]   │
    ├──────────────────────────────────────────────────────  │
    │ Request / Problem                         [col1+2+3]   │
    ├─────────────────────────────────────────────────────── │
    │ Assessment                                [col1+2+3]   │
    ├─────────────────────────────────────────────────────── │
    │ Recommendation                            [col1+2+3]   │
    ├──────────────────┬──────────────────┬──────────────────┤
    │ Assigned Staff   │ Target Date      │ Approved by      │
    │ [col1]           │ [col2]           │ [col3]           │
    ├──────────────────┴──────────────────┴──────────────────┤
    │ Action Taken                          [col1+2+3]       │
    ├─────────────────────────────────────────────────────── │
    │ Status / Condition                    [col1+2+3]       │
    ├──────────────────┬──────────────────┬──────────────────┤
    │ Date Completed   │ Serviced By      │ Confirmed by User│
    │ [col1]           │ [col2]           │ [col3]           │
    └──────────────────┴──────────────────┴──────────────────┘
  --}}

  <table class="ft">
    <colgroup>
      <col><col><col>
    </colgroup>

    {{-- ── Row 1: Requested by | IT JRF # ──────────────── --}}
    <tr>
      <td colspan="2">
        <div class="kv">
          <span class="lbl">Requested by:</span>
          <span class="bold">{{ strtoupper($jobRequest->user->name ?? '—') }}</span>
        </div>
        @if($jobRequest->user?->division?->name)
          <div style="font-size:10px; color:#444; margin-top:2px; padding-left:84px;">
            {{ $jobRequest->user->division->name }}
          </div>
        @endif
      </td>
      <td>
        <div class="kv">
          <span class="lbl">ITJRF #:</span>
          <span class="bold">{{ $jobRequest->itjr_no }}</span>
        </div>
      </td>
    </tr>

    {{-- ── Row 2: Approved by (DC) | Date ───────────────── --}}
    <tr>
      <td colspan="2">
        <div class="lbl" style="margin-bottom:4px;">Approved by:</div>
        <div class="sig">
          @if($jobRequest->divisionChief?->electronic_signature)
            <img src="{{ asset('storage/signatures/' . $jobRequest->divisionChief->electronic_signature) }}" alt="">
          @else
            <div class="gap"></div>
          @endif
          <div><span class="name">{{ strtoupper($jobRequest->divisionChief?->name ?? '') }}</span></div>
          <div class="pos">{{ $jobRequest->divisionChief?->position ?? 'Division Chief' }}</div>
        </div>
      </td>
      <td style="vertical-align:middle;">
        <div class="kv">
          <span class="lbl">Date:</span>
          <span class="bold">
            {{ $jobRequest->dc_approval_date
                ? \Carbon\Carbon::parse($jobRequest->dc_approval_date)->format('Y-m-d')
                : '' }}
          </span>
        </div>
      </td>
    </tr>

    {{-- ── Row 3: Request / Problem ──────────────────────── --}}
    <tr>
      <td colspan="3">
        <div class="kv">
          <span class="lbl" style="white-space:nowrap;">Request / Problem:</span>
          <span class="v uline">{{ $jobRequest->description ?? '—' }}</span>
        </div>
      </td>
    </tr>

    {{-- ── Row 4: Assessment ─────────────────────────────── --}}
    <tr>
      <td colspan="3">
        <div class="kv">
          <span class="lbl" style="white-space:nowrap;">Assessment:</span>
          <span class="v uline">{{ $jobRequest->mis_assessment ?? '—' }}</span>
        </div>
      </td>
    </tr>

    {{-- ── Row 5: Recommendation ────────────────────────── --}}
    <tr>
      <td colspan="3">
        <div class="kv">
          <span class="lbl" style="white-space:nowrap;">Recommendation:</span>
          <span class="v">{{ $recommendation }}</span>
        </div>
      </td>
    </tr>

    {{-- ── Row 6: Assigned Staff | Target Date | Approved by (labels) ── --}}
    <tr>
      <td style="border-bottom:none; padding-bottom:2px;">
        <span class="lbl">Assigned Staff (IT/ISA):</span>
      </td>
      <td style="border-bottom:none; padding-bottom:2px;">
        <span class="lbl">Target Date of Completion:</span>
      </td>
      <td style="border-bottom:none; padding-bottom:2px;">
        <span class="lbl">Approved by:</span>
      </td>
    </tr>

    {{-- ── Row 7: Assigned Staff | Target Date | OCD (signatures) ────── --}}
    <tr>
      {{-- Assigned Staff --}}
      <td style="border-top:none; text-align:center; padding-top:0;">
        <div class="sig">
          @if($jobRequest->assignedTo?->electronic_signature)
            <img src="{{ asset('storage/signatures/' . $jobRequest->assignedTo->electronic_signature) }}" alt="">
          @else
            <div class="gap"></div>
          @endif
          <div><span class="name">{{ strtoupper($jobRequest->assignedTo?->name ?? '') }}</span></div>
          <div class="pos">{{ $jobRequest->assignedTo?->position ?? 'IT/ISA Staff' }}</div>
        </div>
      </td>
      {{-- Target Date --}}
      <td style="border-top:none; text-align:center; vertical-align:middle;">
        @if($jobRequest->expected_completion_date)
          <span class="bold uline">
            {{ \Carbon\Carbon::parse($jobRequest->expected_completion_date)->format('Y-m-d') }}
          </span>
        @endif
      </td>
      {{-- OCD / Campus Director --}}
      <td style="border-top:none; text-align:center; padding-top:0;">
        <div class="sig">
          @if($ocdApprover?->electronic_signature)
            <img src="{{ asset('storage/signatures/' . $ocdApprover->electronic_signature) }}" alt="">
          @else
            <div class="gap"></div>
          @endif
          <div><span class="name">{{ strtoupper($ocdApprover?->name ?? '') }}</span></div>
          <div class="pos">{{ $ocdApprover?->position ?? 'Campus Director' }}</div>
        </div>
      </td>
    </tr>

    {{-- ── Row 8: Action Taken ───────────────────────────── --}}
    <tr>
      <td colspan="3">
        <div class="kv">
          <span class="lbl" style="white-space:nowrap;">Action Taken:</span>
          <span class="v uline">{{ $jobRequest->action_taken ?? '—' }}</span>
        </div>
      </td>
    </tr>

    {{-- ── Row 9: Status / Condition ────────────────────── --}}
    <tr>
      <td colspan="3">
        <div class="kv">
          <span class="lbl" style="white-space:nowrap;">Status / Condition:</span>
          <span class="v">{{ $jobRequest->status ?? '—' }}</span>
        </div>
      </td>
    </tr>

    {{-- ── Row 10: Date Completed | Serviced By | Confirmed (labels) ─── --}}
    <tr>
      <td style="border-bottom:none; padding-bottom:2px;">
        <span class="lbl">Date Completed:</span>
      </td>
      <td style="border-bottom:none; padding-bottom:2px; text-align:center;">
        <span class="lbl">Serviced By:</span>
      </td>
      <td style="border-bottom:none; padding-bottom:2px; text-align:center;">
        <span class="lbl">Confirmed by User:</span>
      </td>
    </tr>

    {{-- ── Row 11: Date | Serviced By sig | Confirmed name ─────────── --}}
    <tr>
      {{-- Date Completed --}}
      <td style="border-top:none; vertical-align:bottom; padding-bottom:12px; padding-top:0;">
        @if($jobRequest->completed_at)
          <span class="bold uline">
            {{ \Carbon\Carbon::parse($jobRequest->completed_at)->format('Y-m-d') }}
          </span>
        @endif
      </td>
      {{-- Serviced By --}}
      <td style="border-top:none; text-align:center; padding-top:0;">
        <div class="sig">
          @if($jobRequest->assignedTo?->electronic_signature)
            <img src="{{ asset('storage/signatures/' . $jobRequest->assignedTo->electronic_signature) }}" alt="">
          @else
            <div class="gap"></div>
          @endif
          <div><span class="name">{{ strtoupper($jobRequest->assignedTo?->name ?? '') }}</span></div>
          <div class="pos">{{ $jobRequest->assignedTo?->position ?? 'IT/ISA Staff' }}</div>
        </div>
      </td>
      {{-- Confirmed by User --}}
      <td style="border-top:none; text-align:center; vertical-align:bottom; padding-bottom:12px; padding-top:0;">
        <span class="name">{{ strtoupper($jobRequest->user?->name ?? '—') }}</span>
      </td>
    </tr>

  </table>

  <div class="footer">PSHS-00-F-ITU-01-Ver02-Rev2-12/31/21</div>
</div>

<script>
  window.addEventListener('load', function () { window.print(); });
</script>
</body>
</html>
