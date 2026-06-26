@php
    use Illuminate\Support\Carbon;
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
    .org { font-size: 11px; font-weight: bold; }
    h2.title { font-size: 13px; letter-spacing: 1px; margin: 10px 0 2px; }
    table.form { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.form td { border: 1px solid #333; padding: 5px 7px; vertical-align: top; }
    .label { font-size: 8.5px; text-transform: uppercase; letter-spacing: .4px; color: #555; display:block; margin-bottom: 2px; }
    .value { font-size: 11px; min-height: 12px; }
    .desc { min-height: 40px; }
    .ref { font-size: 10px; color: #444; }
    .divider { border: none; border-top: 1px dashed #999; margin: 22px 0 4px; }
    .footer-code { margin-top: 10px; font-size: 8px; color: #666; }
</style>
</head>
<body>
    {{-- ── Confiscation Receipt ── --}}
    <div class="center">
        <div class="org">PHILIPPINE SCIENCE HIGH SCHOOL SYSTEM — Caraga Region Campus</div>
        <h2 class="title">CONFISCATION RECEIPT</h2>
        <div class="ref">Receipt No. {{ $item->receipt_no }}</div>
    </div>

    <table class="form">
        <tr>
            <td style="width:60%">
                <span class="label">Student</span>
                <span class="value">{{ $student['name'] }}</span>
            </td>
            <td style="width:40%">
                <span class="label">Grade / Section</span>
                <span class="value">{{ $student['grade_section'] }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="label">Item(s) Confiscated</span>
                <div class="value desc">{!! nl2br(e($item->item_description)) !!}</div>
            </td>
        </tr>
        <tr>
            <td>
                <span class="label">Place</span>
                <span class="value">{{ $item->place }}</span>
            </td>
            <td>
                <span class="label">Date / Time Confiscated</span>
                <span class="value">{{ $fmtDateTime($item->confiscated_at) }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="label">Reason</span>
                <div class="value">{!! nl2br(e($item->reason)) !!}</div>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="label">Confiscated By</span>
                <span class="value">{{ $item->confiscatedBy?->name }}</span>
            </td>
        </tr>
    </table>

    <hr class="divider">

    {{-- ── Item Claim Form ── --}}
    <div class="center">
        <h2 class="title">ITEM CLAIM FORM</h2>
        <div class="ref">Ref. Receipt No. {{ $item->receipt_no }}</div>
    </div>

    <table class="form">
        <tr>
            <td colspan="2">
                <span class="label">Item(s) to be Claimed</span>
                <div class="value">{!! nl2br(e($item->item_description)) !!}</div>
            </td>
        </tr>
        <tr>
            <td style="width:50%">
                <span class="label">Acknowledged By ({{ ucfirst($item->claim_ack_role ?? 'Adviser / Counselor / Dorm Manager') }})</span>
                <span class="value">{{ $item->claimAckBy?->name }}</span>
            </td>
            <td style="width:50%">
                <span class="label">Date / Time Claimed</span>
                <span class="value">{{ $fmtDateTime($item->claimed_at) }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="label">Remarks</span>
                <div class="value">{!! nl2br(e($item->claim_remarks)) !!}</div>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="label">Status</span>
                <span class="value">{{ ucfirst($item->status) }}</span>
            </td>
        </tr>
    </table>

    <div class="footer-code center">PSHS-00-F-SDO Confiscation / Item Claim</div>
</body>
</html>
