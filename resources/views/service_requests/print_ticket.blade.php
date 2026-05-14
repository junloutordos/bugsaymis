<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Request for Services - Print</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #000;
        }

        .container {
            width: 800px;
            margin: auto;
        }

        .center {
            text-align: center;
        }

        .bold {
            font-weight: bold;
        }

        .mt-10 { margin-top: 10px; }
        .mt-20 { margin-top: 20px; }
        .mt-30 { margin-top: 30px; }

        .line {
            display: inline-block;
            border-bottom: 1px solid #000;
            min-width: 200px;
            height: 14px;
            vertical-align: bottom;
        }

        .line.long { min-width: 500px; }

        .row {
            display: flex;
            align-items: center;
            margin-bottom: 6px;
        }

        .label {
            width: 140px;
        }

        .checkbox {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 1px solid #000;
            margin-right: 6px;
        }

        .checked {
            background: #000;
        }

        .two-col {
            display: flex;
            justify-content: space-between;
        }

        .col {
            width: 48%;
        }

        .signature {
            margin-top: 50px;
        }

        .signature .line {
            min-width: 260px;
        }

        .footer {
            margin-top: 40px;
            font-size: 10px;
        }
        .dig-badge { font-size:9px; color:#166534; background:#f0fdf4; border:1px solid #86efac; border-radius:3px; padding:2px 6px; margin-top:3px; display:inline-block; }
    </style>
</head>

<body onload="window.print()">
<div class="container">

    <!-- HEADER -->
    <div class="center bold">
        PHILIPPINE SCIENCE HIGH SCHOOL SYSTEM
    </div>

    <div class="center mt-10">
        Campus: <span class="line">CARAGA REGION CAMPUS IN BUTUAN CITY</span>
    </div>

    <div class="center bold mt-20">
        REQUEST FOR SERVICES
    </div>

    <div class="two-col mt-20">
        <div></div>
        <div>
            <div>No.: <span class="line">{{ $request->reference_no ?? $request->id }}</span></div>
            <div class="mt-10">Date: <span class="line">{{ now()->toDateString() }}</span></div>
        </div>
    </div>

    <!-- SERVICE REQUESTED -->
    <div class="mt-20 bold">Service Requested:</div>

    <div class="row mt-10">
        <span class="checkbox {{ strtolower($request->service_type)=='reproduction' ? 'checked' : '' }}"></span>
        Reproduction
        <span style="margin-left:30px">No. of copies:</span>
        <span class="line">{{ $request->copies ?? '' }}</span>
    </div>

    <div class="row">
        <span style="width:20px"></span>
        No. of sheets per set (for multiple copies):
        <span class="line">{{ $request->sheets_per_set ?? '' }}</span>
    </div>

    <div class="row mt-10">
        <span class="checkbox {{ strtolower($request->service_type)=='security' ? 'checked' : '' }}"></span>
        Security
        <span style="margin-left:40px"></span>
        <span class="checkbox {{ strtolower($request->service_type)=='janitorial' ? 'checked' : '' }}"></span>
        Janitorial
    </div>

    <!-- DATE / TIME -->
    <div class="row mt-20">
        <div class="label">Date/s Needed:</div>
        <span class="line">{{ optional($request->date_needed)->toDateString() ?? $request->date_needed }}</span>

        <div style="margin-left:40px" class="label">Time Needed:</div>
        <span class="line">{{ $request->time_needed }}</span>
    </div>

    <!-- PURPOSE -->
    <div class="mt-20">
        Purpose/s:
        <div class="line long">{{ $request->purposes }}</div>
    </div>

    <!-- DETAILS -->
    <div class="mt-20">
        Details:
        <div class="line long">{{ $request->details }}</div>
    </div>

    <!-- SIGNATURES -->
    <div class="two-col signature">
        <div class="center">
            Requested by:
                @php
                    $reqSig = null;
                    try {
                        $reqUser = $request->requester ?? null;
                        if (! $reqUser && !empty($request->requestor_id)) {
                            $reqUser = \App\Models\User::find($request->requestor_id);
                        }
                        if (! $reqUser && ! empty($request->requestor)) {
                            $reqUser = \App\Models\User::where('name', $request->requestor)->first();
                        }
                        if ($reqUser && ! empty($reqUser->electronic_signature)) {
                            $reqSig = $reqUser->electronic_signature;
                        }
                    } catch (\Throwable $e) { $reqSig = null; }
                @endphp
                @if(isset($sigs['submission']['uri']))
                    <img src="{{ $sigs['submission']['uri'] }}" alt="requestor signature" style="max-height:70px; display:block; margin:0 auto 6px;" />
                @elseif($reqSig)
                    <img src="{{ asset('storage/' . $reqSig) }}" alt="requestor signature" style="max-height:70px; display:block; margin:0 auto 6px;" />
                @else
                    <div class="line mt-30"></div>
                @endif
                @if(isset($sigs['submission'])) <div class="dig-badge">✓ Digitally Signed · {{ optional($sigs['submission']['signed_at'])->format('M d, Y H:i') }}</div> @endif
                <div class="mt-10"><strong>{{ $request->requester?->name ?? $request->requestor ?? ($request->requestor_id ? optional(\App\Models\User::find($request->requestor_id))->name : '—') }}</strong></div>
            <div class="mt-10">Name & Signature of Requisitioner</div>
            <div class="mt-10">
                Position: <span class="line">{{ $reqUser->position }}</span>
            </div>
        </div>

        <div class="center">
            Recommending Approval:
            @php
                $dcSig = null;
                $dcName = null;
                try {
                    $dcUser = null;
                    if (!empty($request->requester) && $request->requester->division && $request->requester->division->divisionchief) {
                        $dcUser = $request->requester->division->divisionchief;
                    }
                    if (! $dcUser && !empty($request->division_chief_id)) {
                        $dcUser = \App\Models\User::find($request->division_chief_id);
                    }
                    if (! $dcUser && ! empty($request->unit)) {
                        $div = \App\Models\Division::where('division_name', $request->unit)->first();
                        if ($div && $div->divisionchief) {
                            $dcUser = $div->divisionchief;
                        }
                    }
                    if ($dcUser) {
                        $dcName = $dcUser->name ?? null;
                        if (!empty($dcUser->electronic_signature)) {
                            $dcSig = $dcUser->electronic_signature;
                        }
                    }
                } catch (\Throwable $e) { $dcSig = null; $dcName = null; }
            @endphp
            @if(isset($sigs['dc_approval']['uri']))
                <img src="{{ $sigs['dc_approval']['uri'] }}" alt="division chief signature" style="max-height:70px; display:block; margin:0 auto 6px;" />
            @elseif($dcSig)
                <img src="{{ asset('storage/' . $dcSig) }}" alt="division chief signature" style="max-height:70px; display:block; margin:0 auto 6px;" />
            @else
                <div class="line mt-30"></div>
            @endif
            @if(isset($sigs['dc_approval'])) <div class="dig-badge">✓ Digitally Signed · {{ optional($sigs['dc_approval']['signed_at'])->format('M d, Y H:i') }}</div> @endif
            <div class="mt-10"><strong>{{ $dcName ?? '—' }}</strong></div>
            <div class="mt-10">Name & Signature of Division Chief</div>
        </div>
    </div>

    @php
        $st = strtolower($request->status ?? '');
        // Resolve FAD chief / division signature similar to facility logic
        $fadName = null;
        $fadSig = null;
        try {
            $div = \App\Models\Division::where('division_name', 'Finance and Administrative Division')->first();
            if (! $div) {
                $div = \App\Models\Division::where('division_name', 'Finance & Administrative Division')->first();
            }
            if (! $div) {
                $div = \App\Models\Division::whereRaw('lower(division_name) like ?', ['%finance%'])
                    ->where(function($q){
                        $q->whereRaw('lower(division_name) like ?', ['%administrative%'])
                          ->orWhereRaw('lower(division_name) like ?', ['%admin%']);
                    })->first();
            }
            if ($div) {
                $chief = $div->divisionchief;
                $fadName = $chief->name ?? $div->division_name ?? null;
                if (!empty($div->signature_path)) {
                    $fadSig = $div->signature_path;
                } elseif ($chief && !empty($chief->electronic_signature)) {
                    $fadSig = $chief->electronic_signature;
                }
            }
        } catch (\Throwable $e) {
            $fadName = null; $fadSig = null;
        }
    @endphp

    <div class="mt-20">
        <span class="checkbox {{ str_contains($st, 'approved') ? 'checked' : '' }}"></span> Approved &nbsp;&nbsp;&nbsp; 
        <span class="checkbox {{ str_contains($st, 'decline') ? 'checked' : '' }}"></span> Disapproved
    </div>

    <div class="mt-30">
        <div style="text-align:left;">
            @if(isset($sigs['fad_approval']['uri']))
                <img src="{{ $sigs['fad_approval']['uri'] }}" alt="fad signature" style="max-height:70px; display:block; margin:0 0 6px 0;" />
            @elseif($fadSig)
                <img src="{{ asset('storage/' . $fadSig) }}" alt="fad signature" style="max-height:70px; display:block; margin:0 0 6px 0;" />
            @else
                <div class="line mt-30" style="margin:0 0 0 0;"></div>
            @endif
            @if(isset($sigs['fad_approval'])) <div class="dig-badge">✓ Digitally Signed · {{ $sigs['fad_approval']['name'] }} · {{ optional($sigs['fad_approval']['signed_at'])->format('M d, Y H:i') }}</div> @endif
            <div class="mt-10"><strong>{{ $fadName ?? '—' }}</strong></div>
            <div class="mt-6">FAD Chief</div>
        </div>
    </div>

    <!-- FOOTER -->
    <div class="footer bold">
        PSHS-00-F-GSM-01-Ver02-Rev0-02/01/20
    </div>

</div>
</body>
</html>
