@php
    use Illuminate\Support\Carbon;
    $fmtDate = fn ($d) => $d ? Carbon::parse($d)->format('F j, Y') : '';
    $fmtDateTime = fn ($d) => $d ? Carbon::parse($d)->format('F j, Y g:i A') : '';
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    * { font-family: 'dejavusans', sans-serif; }
    body { font-size: 11px; color: #111; }
    .center { text-align: center; }
    .muted { color: #555; }
    h1.title { font-size: 15px; letter-spacing: 1px; margin: 6px 0 2px; }
    .org { font-size: 11px; font-weight: bold; }
    .case-no { font-size: 10px; color: #444; margin-top: 2px; }
    table.form { width: 100%; border-collapse: collapse; margin-top: 10px; }
    table.form td { border: 1px solid #333; padding: 5px 7px; vertical-align: top; }
    .label { font-size: 8.5px; text-transform: uppercase; letter-spacing: .4px; color: #555; display:block; margin-bottom: 2px; }
    .value { font-size: 11px; min-height: 12px; }
    .narr { min-height: 120px; }
    .interv { min-height: 60px; }
    .section-head { background: #f0f0f0; font-weight: bold; font-size: 9px; text-transform: uppercase; letter-spacing: .5px; }
    .footer-code { margin-top: 14px; font-size: 8px; color: #666; }
    .badge { display:inline-block; padding:1px 6px; border:1px solid #999; border-radius:3px; font-size:9px; }
    .signed { font-size:8px; color:#059669; margin-top:2px; }
    .sig-img { max-height:55px; max-width:170px; object-fit:contain; }
</style>
</head>
<body>
    <div class="center">
        <div class="org">PHILIPPINE SCIENCE HIGH SCHOOL SYSTEM</div>
        <div class="muted">Caraga Region Campus (PSHS-CRC)</div>
        <h1 class="title">ANECDOTAL REPORT</h1>
        <div class="case-no">Case No. {{ $case->case_no }}
            @if($case->offense_level) &nbsp;|&nbsp; <span class="badge">Level {{ $case->offense_level }} Offense</span> @endif
            @if($case->is_bullying) &nbsp;|&nbsp; <span class="badge">Bullying</span> @endif
        </div>
    </div>

    <table class="form">
        <tr>
            <td style="width:50%">
                <span class="label">Filed By</span>
                <span class="value">{{ $case->filer?->name }}</span>
            </td>
            <td style="width:50%">
                <span class="label">Position / Yr-Sec</span>
                <span class="value">{{ $case->filer_position_snapshot ?: ($case->filer?->position) }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="label">Filed Against</span>
                <span class="value">{{ $student['name'] }}</span>
            </td>
            <td>
                <span class="label">Position / Yr-Sec</span>
                <span class="value">{{ $case->filer_section_snapshot ?: $student['grade_section'] }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="label">Nature of the Offense (consult Code of Conduct)</span>
                <span class="value">
                    @if($case->offense)
                        {{ $case->offense->title }}@if($case->offense->category) <span class="muted">({{ $case->offense->category }})</span>@endif
                    @endif
                    @if($case->nature_of_offense) {{ $case->offense ? ' — ' : '' }}{{ $case->nature_of_offense }} @endif
                </span>
            </td>
        </tr>
        <tr>
            <td style="width:50%">
                <span class="label">Date of Incident</span>
                <span class="value">{{ $fmtDate($case->incident_date) }}</span>
            </td>
            <td style="width:50%">
                <span class="label">Time of Incident</span>
                <span class="value">{{ $case->incident_time }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="label">Place</span>
                <span class="value">{{ $case->place }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="label">Other Witnesses</span>
                <span class="value">{{ $case->other_witnesses }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="label">Narrative Report</span>
                <div class="value narr">{!! nl2br(e($case->narrative)) !!}</div>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="label">Attachments (enumerate attached pictures or documents, if any)</span>
                <div class="value">{!! nl2br(e($case->attachments_note)) !!}</div>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="label">Interventions Done</span>
                <div class="value interv">{!! nl2br(e($case->interventions_done)) !!}</div>
            </td>
        </tr>
    </table>

    <table class="form">
        <tr><td colspan="3" class="section-head">Receiving Information (to be accomplished by Discipline Office personnel)</td></tr>
        <tr>
            <td style="width:34%">
                <span class="label">Date Filed</span>
                <span class="value">{{ $fmtDate($case->date_filed) }}</span>
            </td>
            <td style="width:33%">
                <span class="label">Time Filed</span>
                <span class="value">{{ $case->time_filed }}</span>
            </td>
            <td style="width:33%">
                <span class="label">Received By</span>
                @if($receivedSig)<div><img src="{{ $receivedSig }}" class="sig-img"></div>@endif
                <span class="value">{{ $case->receiver?->name }}</span>
                @if(!empty($signedBadges['received']))<div class="signed">&#10003; Digitally Signed · {{ $signedBadges['received'] }}</div>@endif
            </td>
        </tr>
    </table>

    @if($case->status === 'resolved' || $case->resolution || $case->sanction)
    <table class="form">
        <tr><td colspan="2" class="section-head">Resolution</td></tr>
        <tr>
            <td colspan="2">
                <span class="label">Sanction / Action</span>
                <div class="value">{!! nl2br(e($case->sanction)) !!}</div>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="label">Resolution Notes</span>
                <div class="value">{!! nl2br(e($case->resolution)) !!}</div>
            </td>
        </tr>
        <tr>
            <td style="width:50%">
                <span class="label">Resolved By</span>
                @if($resolvedSig)<div><img src="{{ $resolvedSig }}" class="sig-img"></div>@endif
                <span class="value">{{ $case->resolver?->name }}</span>
                @if(!empty($signedBadges['resolved']))<div class="signed">&#10003; Digitally Signed · {{ $signedBadges['resolved'] }}</div>@endif
            </td>
            <td style="width:50%">
                <span class="label">Date Resolved</span>
                <span class="value">{{ $fmtDateTime($case->resolved_at) }}</span>
            </td>
        </tr>
    </table>
    @endif

    @if($documentQr)
    <table style="width:100%; margin-top:14px;"><tr>
        <td style="width:90px; vertical-align:middle;">
            <img src="data:image/svg+xml;base64,{{ $documentQr }}" style="width:80px;height:80px;">
        </td>
        <td style="vertical-align:middle; font-size:9px; color:#475569;">
            <strong style="color:#1e293b;">Digitally Signed Document</strong><br>
            This Anecdotal Report was digitally signed within the CRCMIS.<br>
            Scan the QR code to verify its authenticity.
        </td>
    </tr></table>
    @endif

    <div class="footer-code center">PSHS-00-F-SDO-01-Ver02-Rev0-02/01/20</div>
</body>
</html>
