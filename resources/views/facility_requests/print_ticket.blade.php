<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Permit to Use School Facilities - Print</title>
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
        .header {
            text-align: center;
            margin-bottom: 10px;
        }
        .header h2 {
            margin: 0;
            font-size: 16px;
            font-weight: bold;
        }
        .header h3 {
            margin: 4px 0;
            font-size: 14px;
        }
        .ref-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .section {
            border: 1px solid #000;
            padding: 8px;
            margin-bottom: 10px;
        }
        .row {
            display: flex;
            margin-bottom: 6px;
        }
        .label {
            width: 220px;
            font-weight: bold;
        }
        .value {
            flex: 1;
            border-bottom: 1px solid #000;
            padding-left: 5px;
        }
        .checkbox-group span {
            margin-right: 15px;
        }
        .small {
            font-size: 11px;
        }
        .dig-badge { font-size:9px; color:#166534; background:#f0fdf4; border:1px solid #86efac; border-radius:3px; padding:2px 6px; margin-top:3px; display:inline-block; }
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        .sign {
            width: 30%;
            text-align: center;
        }
        .sign .line {
            border-top: 1px solid #000;
            margin-top: 40px;
        }
        /* When a signature image is present, bring the underline closer */
        .sign.with-image .line {
            margin-top: 8px;
        }
    </style>
</head>

<body onload="window.print()">
<div class="container">

    <div class="header">
        <h3>PHILIPPINE SCIENCE HIGH SCHOOL SYSTEM</h3>
        <h3>CAMPUS/OFFICE: CARAGA REGION CAMPUS IN BUTUAN CITY</h3>
        <br>
        <h2>PERMIT TO USE SCHOOL FACILITIES</h2>
    </div>

    <div class="ref-row">
        <div><strong>Reference No.:</strong> {{ $request->id }}</div>
        <div><strong>Date:</strong> {{ now()->format('F d, Y') }}</div>
    </div>

    <div class="section">
        <div class="row">
            <div class="label">Name of Requestor</div>
            <div class="value">{{ $request->requester?->name ?? $request->requestor ?? '—' }}</div>
        </div>
        <div class="row">
            <div class="label">Club / Organization / Division / Unit</div>
            <div class="value">{{ $request->requester?->division?->division_name ?? $request->unit ?? '—' }}</div>
        </div>
        <div class="row">
            <div class="label">Activity</div>
            <div class="value">{{ $request->activity ?? '—' }}</div>
        </div>
        <div class="row">
            <div class="label">Purpose</div>
            <div class="value">{{ $request->purpose ?? '—' }}</div>
        </div>
    </div>

    <div class="section">
        <div class="row">
            <div class="label">Nature of Activity</div>
            <div class="value checkbox-group">
                <span>[ {{ $request->nature === 'Curricular' ? '✓' : ' ' }} ] Curricular</span>
                <span>[ {{ $request->nature === 'Co-Curricular' ? '✓' : ' ' }} ] Co-Curricular</span>
                <span>[ {{ $request->nature === 'Others' ? '✓' : ' ' }} ] Others</span>
            </div>
        </div>

        <div class="row">
            <div class="label">Date/s Needed</div>
            <div class="value">
                @php
                    $ds = $request->date_start ? (\Carbon\Carbon::parse($request->date_start)->format('F d, Y')) : null;
                    $de = $request->date_end ? (\Carbon\Carbon::parse($request->date_end)->format('F d, Y')) : null;
                @endphp
                {{ $ds ?? '—' }}
                @if($de) – {{ $de }} @endif
            </div>
        </div>

        <div class="row">
            <div class="label">Time Needed</div>
            <div class="value">
                {{ $request->time_start ?? '—' }}
                @if($request->time_end) – {{ $request->time_end }} @endif
            </div>
        </div>

        <div class="row">
            <div class="label">Participants / No. of Pax</div>
            <div class="value">
                @php
                    $participants = $request->participants ?? null;
                    $male = $request->male ?? null;
                    $female = $request->female ?? null;
                    $parts = [];
                    if ($participants) $parts[] = $participants;
                    $counts = [];
                    if (!is_null($male) && $male !== '') $counts[] = "M: {$male}";
                    if (!is_null($female) && $female !== '') $counts[] = "F: {$female}";
                    if (count($counts)) $parts[] = '('.implode(', ', $counts).')';
                @endphp
                {{ count($parts) ? implode(' ', $parts) : '—' }}
            </div>
        </div>
    </div>

    <div class="section">
        <div class="row">
            <div class="label">Venue/s Requested</div>
            <div class="value">
                @php
                    $venues = $request->venue ?? [];
                    if (!is_array($venues) && $venues) $venues = [$venues];
                    $venueNames = [];
                    if (is_array($venues) && count($venues)) {
                        try {
                            $ids = array_values(array_filter($venues));
                            if (count($ids)) {
                                $venueNames = \App\Models\Facility::whereIn('id', $ids)->pluck('name')->toArray();
                            }
                        } catch (\Throwable $e) {
                            $venueNames = [];
                        }
                    }
                @endphp
                {{ count($venueNames) ? implode(', ', $venueNames) : (count($venues) ? implode(', ', $venues) : '—') }}
            </div>
        </div>

        <div class="row">
            <div class="label">Equipment / Facilities Needed</div>
            <div class="value small">
                @php
                    $equip = $request->equipment ?? [];
                    if (!is_array($equip) && $equip) $equip = [$equip];
                    $qtys = $request->equipment_quantities ?? [];
                    if (!is_array($qtys)) {
                        $qtys = $qtys ? json_decode($qtys, true) ?? [] : [];
                    }
                    $lines = [];
                    foreach ($equip as $e) {
                        $q = $qtys[$e] ?? null;
                        $lines[] = $q ? "$e ($q pcs)" : $e;
                    }
                @endphp
                {{ count($lines) ? implode(', ', $lines) : '—' }}
            </div>
        </div>
    </div>

    <div class="section">
        <strong>REMARKS:</strong>
        <div style="height:50px;"></div>
    </div>

    <div class="signatures">
        @php
            // Requestor signature: prefer requester relation electronic_signature, fallback to matching user by name
            $reqSig = null;
            try {
                $reqUser = $request->requester ?? \App\Models\User::where('name', $request->requestor)->first();
                if ($reqUser && !empty($reqUser->electronic_signature)) {
                    $reqSig = $reqUser->electronic_signature;
                }
            } catch (\Throwable $e) {
                $reqSig = null;
            }

            // Division chief signature: resolve via requester->division, or fallback to matching unit name
            $dcName = null;
            $dcSig = null;
            try {
                $reqDiv = $request->requester?->division ?? null;
                if ($reqDiv) {
                    $dc = $reqDiv->divisionchief ?? null;
                    $dcName = $dc->name ?? $reqDiv->division_name ?? null;
                    if ($dc && !empty($dc->electronic_signature)) {
                        $dcSig = $dc->electronic_signature;
                    } elseif (!empty($reqDiv->signature_path)) {
                        $dcSig = $reqDiv->signature_path;
                    }
                } elseif (!empty($request->unit)) {
                    // fallback: try to match request unit to Division and use its signature
                    $divByName = \App\Models\Division::where('division_name', $request->unit)->first();
                    if ($divByName) {
                        $dcName = $divByName->divisionchief?->name ?? $divByName->division_name ?? null;
                        if (!empty($divByName->signature_path)) {
                            $dcSig = $divByName->signature_path;
                        } elseif ($divByName->divisionchief && !empty($divByName->divisionchief->electronic_signature)) {
                            $dcSig = $divByName->divisionchief->electronic_signature;
                        }
                    }
                }
            } catch (\Throwable $e) {
                $dcName = null; $dcSig = null;
            }

            // FAD chief / division signature: prefer Division.signature_path, then division chief electronic_signature
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

        <div class="sign {{ ($reqSig || isset($sigs['submission'])) ? 'with-image' : '' }}">
            @if(isset($sigs['submission']['uri']))
                <img src="{{ $sigs['submission']['uri'] }}" alt="requestor signature" style="max-height:70px; display:block; margin:0 auto 6px;" />
            @elseif($reqSig)
                <img src="{{ asset('storage/' . $reqSig) }}" alt="requestor signature" style="max-height:70px; display:block; margin:0 auto 6px;" />
            @endif
            <div class="line"></div>
            @if(isset($sigs['submission'])) <div class="dig-badge">✓ Digitally Signed · {{ optional($sigs['submission']['signed_at'])->format('M d, Y H:i') }}</div> @endif
            <div class="small"><strong>{{ $request->requester?->name ?? $request->requestor ?? '—' }}</strong></div>
            Requestor
        </div>

        <div class="sign {{ ($dcSig || isset($sigs['dc_approval'])) ? 'with-image' : '' }}">
            @if(isset($sigs['dc_approval']['uri']))
                <img src="{{ $sigs['dc_approval']['uri'] }}" alt="division chief signature" style="max-height:70px; display:block; margin:0 auto 6px;" />
            @elseif($dcSig)
                <img src="{{ asset('storage/' . $dcSig) }}" alt="division chief signature" style="max-height:70px; display:block; margin:0 auto 6px;" />
            @endif
            <div class="line"></div>
            @if(isset($sigs['dc_approval'])) <div class="dig-badge">✓ Digitally Signed · {{ optional($sigs['dc_approval']['signed_at'])->format('M d, Y H:i') }}</div> @endif
            <div class="small"><strong>{{ $dcName ?? '—' }}</strong></div>
            Division Head Concerned
        </div>

        <div class="sign {{ ($fadSig || isset($sigs['fad_approval'])) ? 'with-image' : '' }}">
            @if(isset($sigs['fad_approval']['uri']))
                <img src="{{ $sigs['fad_approval']['uri'] }}" alt="fad signature" style="max-height:70px; display:block; margin:0 auto 6px;" />
            @elseif($fadSig)
                <img src="{{ asset('storage/' . $fadSig) }}" alt="fad signature" style="max-height:70px; display:block; margin:0 auto 6px;" />
            @endif
            <div class="line"></div>
            @if(isset($sigs['fad_approval'])) <div class="dig-badge">✓ Digitally Signed · {{ $sigs['fad_approval']['name'] }} · {{ optional($sigs['fad_approval']['signed_at'])->format('M d, Y H:i') }}</div> @endif
            <div class="small"><strong>{{ $fadName ?? '—' }}</strong></div>
            FAD Chief / Approving Authority
        </div>
    </div>

    <div class="small" style="margin-top:15px;">
        PSHS-00-F-GSM-02-Ver02-Rev0
    </div>

</div>
</body>
</html>
