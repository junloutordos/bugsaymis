<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Transmittal Slip - Print</title>

    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #000;
        }

        .container {
            width: 800px;
            margin: auto;
            padding: 20px 30px;
        }

        /* HEADER */
        .header {
            text-align: center;
            margin-bottom: 15px;
        }

        .header .title {
            font-size: 14px;
            font-weight: bold;
        }

        .header .campus {
            margin-top: 3px;
            font-weight: bold;
        }

        .header .subtitle {
            margin-top: 8px;
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
        }

        /* FORM ROWS */
        .row {
            display: flex;
            margin-bottom: 8px;
        }

        .label {
            width: 260px;
            font-weight: bold;
        }

        .value {
            flex: 1;
            border-bottom: 1px solid #000;
            min-height: 16px;
            padding-left: 3px;
        }

        /* SECTION */
        .section {
            margin-top: 15px;
        }

        /* SIGNATURES */
        .signature {
            margin-top: 35px;
            display: flex;
            justify-content: space-between;
        }

        .sig-box {
            width: 45%;
            text-align: center;
            font-size: 11px;
        }

        .sig-line {
            border-top: 1px solid #000;
            margin-top: 35px;
            padding-top: 4px;
            font-weight: bold;
        }

        .sig-img {
            max-height: 60px;
            display: block;
            margin: 0 auto -32px; /* lift following text so the signature sits over the name */
            z-index: 2;
        }

        .sig-name {
            font-weight: bold;
            margin-top: 40px; /* provide space so name remains readable when signature absent */
            position: relative;
            z-index: 1;
        }

        .sig-label {
            margin-top: 4px;
            font-size: 11px;
        }

        .dig-badge { font-size:9px; color:#166534; background:#f0fdf4; border:1px solid #86efac; border-radius:3px; padding:2px 6px; margin-top:3px; display:inline-block; }

        /* NOTE */
        .note {
            margin-top: 15px;
            font-size: 10px;
            text-align: justify;
        }

        /* FOOTER */
        .footer {
            margin-top: 20px;
            font-size: 10px;
            text-align: center;
            font-weight: bold;
        }
    </style>
</head>

<body onload="window.print()">
<div class="container">

    <!-- HEADER -->
    <div class="header">
        <div class="title">PHILIPPINE SCIENCE HIGH SCHOOL SYSTEM</div>
        <div class="campus">
            CAMPUS: {{ $request->campus ?? 'CARAGA REGION CAMPUS IN BUTUAN CITY' }}
        </div>
        <div class="subtitle">TRANSMITTAL SLIP</div>
    </div>

    <!-- FORM DETAILS -->
    <div class="section">

        <div class="row">
            <div class="label">RFSF Reference No.:</div>
            <div class="value">{{ $request->reference_no ?? '' }}</div>
        </div>

        <div class="row">
            <div class="label">Requesting Office / Origin / Unit:</div>
            <div class="value">{{ $request->unit ?? '' }}</div>
        </div>

        <div class="row">
            <div class="label">Delivery Address:</div>
            <div class="value">{{ $request->destination ?? '' }}</div>
        </div>

        <div class="row">
            <div class="label">Type of Document / Parcel:</div>
            <div class="value">
                {{ is_array($request->messengerial_kinds)
                    ? implode(', ', $request->messengerial_kinds)
                    : ($request->messengerial_kinds ?? '') }}
            </div>
        </div>

        <div class="row">
            <div class="label">Courier Service Provider:</div>
            <div class="value">{{ $request->courier_service_provider ?? '' }}</div>
        </div>

        <div class="row">
            <div class="label">Cost:</div>
            <div class="value">{{ $request->courier_cost ?? '' }}</div>
        </div>

        <div class="row">
            <div class="label">Date Received by the Courier:</div>
            <div class="value">{{ $request->date_received_by_courier ?? '' }}</div>
        </div>

        <div class="row">
            <div class="label">Date Delivered to Addressee:</div>
            <div class="value">{{ $request->date_delivered ?? '' }}</div>
        </div>

        <div class="row">
            <div class="label">Remarks:</div>
            <div class="value">@if($request->purpose)Purpose: {{ $request->purpose }}.@endif @if($request->consignee_name)Consignee: {{ $request->consignee_name }}.@endif @if($request->proof_remarks) {{ $request->proof_remarks }}@endif</div>
        </div>

    </div>

    <!-- SIGNATURES -->
    <div class="signature">
        <div class="sig-box">
            @if(isset($sigs['submission']['uri']))
                <img src="{{ $sigs['submission']['uri'] }}" alt="Signature" class="sig-img" />
            @elseif(!empty($requestorSignature))
                <img src="{{ '/storage/' . $requestorSignature }}" alt="Signature" class="sig-img" />
            @endif

            <div class="sig-line"></div>
            @if(isset($sigs['submission'])) <div class="dig-badge">✓ Digitally Signed · {{ optional($sigs['submission']['signed_at'])->format('M d, Y H:i') }}</div> @endif
            <div class="sig-name">{{ $request->requestor ?? '' }}</div>
            <div class="sig-label">Requested By</div>
        </div>

        <div class="sig-box">
            <div class="sig-line"></div>
            Received by
        </div>
    </div>

    <!-- NOTE -->
    <div class="note">
        <strong>Note:</strong> "Date Delivered to Addressee" and signature in the "Received by" portion
        are applicable for hand-carried documents only. For delivery through courier service,
        photocopy of official receipt shall be attached to this form.
    </div>

    <!-- FOOTER -->
    <div class="footer">
        PSHS-00-F-RMU-05-Ver02-Rev0-10/18/20
    </div>

</div>
<hr>
<div class="container">

    <!-- HEADER -->
    <div class="header">
        <div class="title">PHILIPPINE SCIENCE HIGH SCHOOL SYSTEM</div>
        <div class="campus">
            CAMPUS: {{ $request->campus ?? 'CARAGA REGION CAMPUS IN BUTUAN CITY' }}
        </div>
        <div class="subtitle">TRANSMITTAL SLIP</div>
    </div>

    <!-- FORM DETAILS -->
    <div class="section">

        <div class="row">
            <div class="label">RFSF Reference No.:</div>
            <div class="value">{{ $request->reference_no ?? '' }}</div>
        </div>

        <div class="row">
            <div class="label">Requesting Office / Origin / Unit:</div>
            <div class="value">{{ $request->unit ?? '' }}</div>
        </div>

        <div class="row">
            <div class="label">Delivery Address:</div>
            <div class="value">{{ $request->destination ?? '' }}</div>
        </div>

        <div class="row">
            <div class="label">Type of Document / Parcel:</div>
            <div class="value">
                {{ is_array($request->messengerial_kinds)
                    ? implode(', ', $request->messengerial_kinds)
                    : ($request->messengerial_kinds ?? '') }}
            </div>
        </div>

        <div class="row">
            <div class="label">Courier Service Provider:</div>
            <div class="value">{{ $request->courier_service_provider ?? '' }}</div>
        </div>

        <div class="row">
            <div class="label">Cost:</div>
            <div class="value">{{ $request->courier_cost ?? '' }}</div>
        </div>

        <div class="row">
            <div class="label">Date Received by the Courier:</div>
            <div class="value">{{ $request->date_received_by_courier ?? '' }}</div>
        </div>

        <div class="row">
            <div class="label">Date Delivered to Addressee:</div>
            <div class="value">{{ $request->date_delivered ?? '' }}</div>
        </div>

        <div class="row">
            <div class="label">Remarks:</div>
            <div class="value">@if($request->purpose)Purpose: {{ $request->purpose }}.@endif @if($request->consignee_name)Consignee: {{ $request->consignee_name }}.@endif @if($request->proof_remarks) {{ $request->proof_remarks }}@endif</div>
        </div>

    </div>

    <!-- SIGNATURES -->
    <div class="signature">
        <div class="sig-box">
            @if(isset($sigs['submission']['uri']))
                <img src="{{ $sigs['submission']['uri'] }}" alt="Signature" class="sig-img" />
            @elseif(!empty($requestorSignature))
                <img src="{{ '/storage/' . $requestorSignature }}" alt="Signature" class="sig-img" />
            @endif

            <div class="sig-line"></div>
            @if(isset($sigs['submission'])) <div class="dig-badge">✓ Digitally Signed · {{ optional($sigs['submission']['signed_at'])->format('M d, Y H:i') }}</div> @endif
            <div class="sig-name">{{ $request->requestor ?? '' }}</div>
            <div class="sig-label">Requested By</div>
        </div>

        <div class="sig-box">
            <div class="sig-line"></div>
            Received by
        </div>
    </div>

    <!-- NOTE -->
    <div class="note">
        <strong>Note:</strong> "Date Delivered to Addressee" and signature in the "Received by" portion
        are applicable for hand-carried documents only. For delivery through courier service,
        photocopy of official receipt shall be attached to this form.
    </div>

    <!-- FOOTER -->
    <div class="footer">
        PSHS-00-F-RMU-05-Ver02-Rev0-10/18/20
    </div>

</div>
</body>
</html>
