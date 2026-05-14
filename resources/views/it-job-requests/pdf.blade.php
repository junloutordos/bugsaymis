<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #000; background: #fff; }

    .header { text-align: center; margin-bottom: 16px; line-height: 1.6; }
    .header .org   { font-size: 13px; font-weight: bold; }
    .header .title { font-size: 13px; font-weight: bold; letter-spacing: 3px; margin-top: 10px; }

    .ft { width: 100%; border-collapse: collapse; table-layout: fixed; }
    col.c1 { width: 33%; }
    col.c2 { width: 28%; }
    col.c3 { width: 39%; }

    .ft td { border: 1px solid #000; padding: 6px 10px; vertical-align: top; word-wrap: break-word; }

    .val   { font-weight: bold; font-size: 11px; word-break: break-word; }
    .uline { text-decoration: underline; }
    .field-label       { font-size: 10px; color: #555; margin-bottom: 3px; }
    .field-value       { font-weight: bold; font-size: 11px; text-decoration: underline; word-break: break-word; }
    .field-value-plain { font-weight: bold; font-size: 11px; word-break: break-word; }
    .sig-name { display: inline-block; font-weight: bold; font-size: 11px; border-bottom: 1.5px solid #000; padding-bottom: 1px; margin-top: 2px; min-width: 90px; }
    .sig-pos  { font-size: 9.5px; margin-top: 2px; color: #333; }
    .section-label { font-size: 10px; color: #333; font-weight: normal; padding: 5px 10px 3px; border-bottom: none; }
    .footer { font-size: 9px; color: #555; margin-top: 6px; }
  </style>
</head>
<body>

  <div class="header">
    <div class="org">PHILIPPINE SCIENCE HIGH SCHOOL SYSTEM</div>
    <div class="org">CAMPUS: <span style="text-decoration:underline;">CARAGA REGION</span></div>
    <div class="title">IT  JOB  REQUEST  FORM</div>
  </div>

  <table class="ft">
    <colgroup><col class="c1"><col class="c2"><col class="c3"></colgroup>

    {{-- Row 1: Requested by | ITJRF # --}}
    <tr>
      <td colspan="2" style="border-right:1px solid #000;">
        <div class="field-label" style="margin-bottom:4px;">Requested by:</div>
        <div style="text-align:center;">
          <table style="border:none;border-collapse:collapse;margin:0 auto;">
            <tr>
              <td style="border:none;padding:0;vertical-align:middle;">
                @if($requesterSig)
                  <img src="{{ $requesterSig }}" alt="" style="display:block;height:36px;width:auto;">
                @else
                  <div style="height:36px;width:50px;"></div>
                @endif
              </td>
              @if(isset($sigBadges['submission']))
              <td style="border:none;padding:0;vertical-align:middle;">
                <div style="font-size:7.5px;color:#1d4ed8;font-weight:bold;white-space:nowrap;">&#10003; Digitally Signed</div>
                <div style="font-size:6.5px;color:#64748b;white-space:nowrap;">{{ $sigBadges['submission'] }}</div>
              </td>
              @endif
            </tr>
          </table>
          <div style="margin-top:2px;"><span class="sig-name">{{ strtoupper($jobRequest->user?->name ?? '') }}</span></div>
          @if($jobRequest->user?->division?->division_name)
            <div class="sig-pos">{{ $jobRequest->user->division->division_name }}</div>
          @endif
        </div>
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
        <div style="text-align:center;">
          <table style="border:none;border-collapse:collapse;margin:0 auto;">
            <tr>
              <td style="border:none;padding:0;vertical-align:middle;">
                @if($dcSig)
                  <img src="{{ $dcSig }}" alt="" style="display:block;height:36px;width:auto;">
                @else
                  <div style="height:36px;width:50px;"></div>
                @endif
              </td>
              @if(isset($sigBadges['dc_approval']))
              <td style="border:none;padding:0;vertical-align:middle;">
                <div style="font-size:7.5px;color:#1d4ed8;font-weight:bold;white-space:nowrap;">&#10003; Digitally Signed</div>
                <div style="font-size:6.5px;color:#64748b;white-space:nowrap;">{{ $sigBadges['dc_approval'] }}</div>
              </td>
              @endif
            </tr>
          </table>
          <div style="margin-top:2px;"><span class="sig-name">{{ strtoupper($jobRequest->divisionChief?->name ?? '') }}</span></div>
          <div class="sig-pos">{{ $jobRequest->divisionChief?->position ?? 'Division Chief' }}</div>
        </div>
      </td>
      <td style="vertical-align:middle;">
        <div class="field-label">Date:</div>
        <div class="val">
          {{ $jobRequest->dc_approval_date ? \Carbon\Carbon::parse($jobRequest->dc_approval_date)->format('F j, Y') : '' }}
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

    {{-- Row 6: Section labels --}}
    <tr>
      <td class="section-label">Assigned Staff (IT/ISA):</td>
      <td class="section-label" style="text-align:center;">Target Date of Completion:</td>
      <td class="section-label" style="text-align:center;">Approved by:</td>
    </tr>

    {{-- Row 7: Assigned staff | Target date | Director --}}
    <tr>
      <td style="border-top:none;padding-top:4px;">
        <div style="text-align:center;">
          <table style="border:none;border-collapse:collapse;margin:0 auto;">
            <tr>
              <td style="border:none;padding:0;vertical-align:middle;">
                @if($assignedSig)
                  <img src="{{ $assignedSig }}" alt="" style="display:block;height:36px;width:auto;">
                @else
                  <div style="height:36px;width:50px;"></div>
                @endif
              </td>
              @if(isset($sigBadges['mis_acted']))
              <td style="border:none;padding:0;vertical-align:middle;">
                <div style="font-size:7.5px;color:#1d4ed8;font-weight:bold;white-space:nowrap;">&#10003; Digitally Signed</div>
                <div style="font-size:6.5px;color:#64748b;white-space:nowrap;">{{ $sigBadges['mis_acted'] }}</div>
              </td>
              @endif
            </tr>
          </table>
          <div style="margin-top:2px;"><span class="sig-name">{{ strtoupper($jobRequest->assignedTo?->name ?? '') }}</span></div>
          <div class="sig-pos">{{ $jobRequest->assignedTo?->position ?? 'IT/ISA Staff' }}</div>
        </div>
      </td>
      <td style="border-top:none;text-align:center;vertical-align:middle;">
        @if($jobRequest->expected_completion_date)
          <span class="val uline">{{ \Carbon\Carbon::parse($jobRequest->expected_completion_date)->format('F j, Y') }}</span>
        @endif
      </td>
      <td style="border-top:none;padding-top:4px;">
        <div style="text-align:center;">
          <table style="border:none;border-collapse:collapse;margin:0 auto;">
            <tr>
              <td style="border:none;padding:0;vertical-align:middle;">
                @if($directorSig)
                  <img src="{{ $directorSig }}" alt="" style="display:block;height:36px;width:auto;">
                @else
                  <div style="height:36px;width:50px;"></div>
                @endif
              </td>
              @if(isset($sigBadges['ocd_approval']))
              <td style="border:none;padding:0;vertical-align:middle;">
                <div style="font-size:7.5px;color:#1d4ed8;font-weight:bold;white-space:nowrap;">&#10003; Digitally Signed</div>
                <div style="font-size:6.5px;color:#64748b;white-space:nowrap;">{{ $sigBadges['ocd_approval'] }}</div>
              </td>
              @endif
            </tr>
          </table>
          <div style="margin-top:2px;"><span class="sig-name">{{ strtoupper($director?->name ?? '') }}</span></div>
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

    {{-- Row 11: Date completed | Serviced by | Confirmed by user --}}
    <tr>
      <td style="border-top:none;vertical-align:middle;padding:8px 10px;">
        @if($jobRequest->completed_at)
          <span class="val uline">{{ \Carbon\Carbon::parse($jobRequest->completed_at)->format('F j, Y') }}</span>
        @endif
      </td>
      <td style="border-top:none;padding:4px 6px;">
        <div style="text-align:center;">
          <table style="border:none;border-collapse:collapse;margin:0 auto;">
            <tr>
              <td style="border:none;padding:0;vertical-align:middle;">
                @if($assignedSig)
                  <img src="{{ $assignedSig }}" alt="" style="display:block;height:36px;width:auto;">
                @else
                  <div style="height:36px;width:50px;"></div>
                @endif
              </td>
              @if(isset($sigBadges['mis_acted']))
              <td style="border:none;padding:0;vertical-align:middle;">
                <div style="font-size:7.5px;color:#1d4ed8;font-weight:bold;white-space:nowrap;">&#10003; Digitally Signed</div>
                <div style="font-size:6.5px;color:#64748b;white-space:nowrap;">{{ $sigBadges['mis_acted'] }}</div>
              </td>
              @endif
            </tr>
          </table>
          <div style="margin-top:2px;"><span class="sig-name">{{ strtoupper($jobRequest->assignedTo?->name ?? '') }}</span></div>
          @if($jobRequest->assignedTo?->position)
            <div class="sig-pos">{{ $jobRequest->assignedTo->position }}</div>
          @endif
        </div>
      </td>
      <td style="border-top:none;padding:4px 6px;">
        <div style="text-align:center;">
          <table style="border:none;border-collapse:collapse;margin:0 auto;">
            <tr>
              <td style="border:none;padding:0;vertical-align:middle;">
                @if($completionSig)
                  <img src="{{ $completionSig }}" alt="" style="display:block;height:36px;width:auto;">
                @else
                  <div style="height:36px;width:50px;"></div>
                @endif
              </td>
              @if(isset($sigBadges['completion']))
              <td style="border:none;padding:0;vertical-align:middle;">
                <div style="font-size:7.5px;color:#1d4ed8;font-weight:bold;white-space:nowrap;">&#10003; Digitally Signed</div>
                <div style="font-size:6.5px;color:#64748b;white-space:nowrap;">{{ $sigBadges['completion'] }}</div>
              </td>
              @endif
            </tr>
          </table>
          <div style="margin-top:2px;"><span class="sig-name">{{ strtoupper($jobRequest->user?->name ?? '') }}</span></div>
          @if($jobRequest->user?->position)
            <div class="sig-pos">{{ $jobRequest->user->position }}</div>
          @endif
        </div>
      </td>
    </tr>

  </table>

  <div class="footer">PSHS-00-F-ITU-01-Ver02-Rev2-12/31/21</div>

  {{-- Single document-level QR — only when at least one digital signature exists --}}
  @if($documentQr)
  <div style="margin-top:10px;border:1px solid #e2e8f0;border-radius:5px;padding:8px 12px;">
    <table style="width:100%;border:none;border-collapse:collapse;">
      <tr>
        <td style="border:none;padding:0;width:70px;vertical-align:middle;">
          <img src="data:image/svg+xml;base64,{{ $documentQr }}" style="width:65px;height:65px;display:block;">
        </td>
        <td style="border:none;padding:0 0 0 10px;vertical-align:middle;">
          <div style="font-size:9px;font-weight:bold;color:#1e293b;margin-bottom:2px;">Digitally Signed Document</div>
          <div style="font-size:7.5px;color:#64748b;">ITJRF #{{ $jobRequest->itjr_no }} &mdash; {{ count($sigBadges) }} digital signature(s) on record</div>
          <div style="font-size:7px;color:#94a3b8;margin-top:2px;">Scan QR code to verify all digital signatures and their authenticity</div>
        </td>
      </tr>
    </table>
  </div>
  @endif

</body>
</html>
