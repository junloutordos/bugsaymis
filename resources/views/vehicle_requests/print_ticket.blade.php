<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Trip Ticket - {{ $request->id }}</title>
  <style>
    body{font-family:Arial, Helvetica, sans-serif; color:#0f172a; padding:8px; font-size:12px}
    .container{max-width:900px;margin:0 auto}
    .header{text-align:center;margin-bottom:8px}
    .card{border:1px solid #ccc;padding:8px;border-radius:4px}
    table{width:100%;border-collapse:collapse}
    td,th{padding:6px;border:1px solid #ddd;font-size:13px}
    .no-border td{border:0;padding:2px}
    .center{text-align:center}
    /* A4 two-up print layout */
    @page { size: A4 portrait; margin: 8mm; }
    .two-up { display:flex; flex-direction:column; gap:4mm; }
    .copy { width:100%; }
    @media print{
      body{padding:6mm}
      .no-print{display:none}
      .container{max-width:210mm}
      .card{border:none;padding:4px;border-radius:0}
      .header{margin-bottom:6px}
      /* Smaller print font to fit two copies on one A4 */
      body, td, th { font-size:10px }
    }
    .dig-badge { font-size:9px; color:#166534; background:#f0fdf4; border:1px solid #86efac; border-radius:3px; padding:2px 6px; margin-top:3px; display:inline-block; }
  </style>
</head>
<body onload="window.print()">
  <div class="container">
    <div class="header">
      <h2>PHILIPPINE SCIENCE HIGH SCHOOL SYSTEM</h2>
      <div style="margin-top:6px;">Campus: CARAGA REGION CAMPUS IN BUTUAN CITY</div>
      <h3 style="margin-top:10px;">PERMIT TO USE SCHOOL VEHICLE</h3>
      <div class="no-print" style="margin-top:8px; text-align:center">
        <button onclick="window.print()">Print</button>
      </div>
    </div>

    @php
      // Resolve FAD chief name and signature (similar to facility print logic)
      $fadName = null; $fadSig = null;
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

      // Director resolved in controller via User::havingRole('OCD')->first()
      $directorName = $director?->name;
    @endphp
    <div class="two-up">
      <div class="copy">
        <div class="card" style="padding:12px">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px">
        <div style="flex:1;">
          <div style="font-weight:bold">Vehicle Requested:</div>
          <div style="border-bottom:1px solid #000;padding:6px 4px;width:70%">{{ $request->vehicle_type ?? '_________________________' }}</div>
        </div>
        <div style="width:260px;text-align:right">
          <div style="display:flex;justify-content:space-between;gap:8px">
            <div style="min-width:50px">No:</div>
            <div style="border-bottom:1px solid #000;width:150px;text-align:left">{{ $request->id }}</div>
          </div>
          <div style="height:8px"></div>
          <div style="display:flex;justify-content:space-between;gap:8px">
            <div style="min-width:50px">Date:</div>
            <div style="border-bottom:1px solid #000;width:150px;text-align:left">{{ optional($request->created_at)->format('F d, Y') }}</div>
          </div>
        </div>
      </div>

      <div style="display:flex;gap:18px">
        <div style="flex:1">
          <div style="display:flex;justify-content:space-between;margin-bottom:8px">
            <div style="width:45%"><strong>Date of Trip:</strong></div>
            <div style="width:50%;border-bottom:1px solid #000">
              @php
                $allDates = [];
                if (!empty($request->date_needed_multiple) && is_array($request->date_needed_multiple)) {
                    foreach ($request->date_needed_multiple as $d) {
                        try { $allDates[] = \Carbon\Carbon::parse($d)->format('M d, Y'); } catch (\Throwable $e) { $allDates[] = $d; }
                    }
                } elseif (!empty($request->date_needed)) {
                    try { $allDates[] = \Carbon\Carbon::parse($request->date_needed)->format('M d, Y'); } catch (\Throwable $e) { $allDates[] = $request->date_needed; }
                }
              @endphp
              @if(count($allDates) > 0)
                {{ implode(', ', $allDates) }}
              @else — @endif
            </div>
          </div>
          <div style="display:flex;justify-content:space-between;margin-bottom:8px">
            <div style="width:45%"><strong>No. of Passengers:</strong></div>
            <div style="width:50%;border-bottom:1px solid #000">{{ $request->passengers ?? '—' }}</div>
          </div>

          <div style="margin-top:6px"><strong>Destination/s:</strong></div>
          @php
            $destLines = [];
            if (!empty($request->destination)) {
              // split by newlines or commas
              $raw = is_array($request->destination) ? implode(', ', $request->destination) : $request->destination;
              $parts = preg_split('/[\r\n,]+/', $raw);
              foreach ($parts as $p) {
                $t = trim($p);
                if ($t !== '') $destLines[] = $t;
              }
            }
            if (empty($destLines)) $destLines = ['—','—','—'];
          @endphp
          @for ($i=0; $i<3; $i++)
            <div style="border-bottom:1px solid #000;height:14px;margin-bottom:6px">{{ $destLines[$i] ?? '—' }}</div>
          @endfor
        </div>

        <div style="flex:1">
          <div style="display:flex;justify-content:space-between;margin-bottom:8px">
            <div style="width:45%"><strong>Time of Departure:</strong></div>
            <div style="width:50%;border-bottom:1px solid #000">{{ $request->time_of_departure ?? '—' }}</div>
          </div>
          <div style="display:flex;justify-content:space-between;margin-bottom:8px">
            <div style="width:45%"><strong>Time of Arrival:</strong></div>
            <div style="width:50%;border-bottom:1px solid #000">{{ $request->eta ?? '—' }}</div>
          </div>

          <div style="margin-top:6px"><strong>Purpose/s:</strong></div>
          @php
            $purposeLines = [];
            if (!empty($request->purpose)) {
              $rawp = is_array($request->purpose) ? implode(', ', $request->purpose) : $request->purpose;
              $pparts = preg_split('/[\r\n,]+/', $rawp);
              foreach ($pparts as $p) {
                $t = trim($p);
                if ($t !== '') $purposeLines[] = $t;
              }
            }
            if (empty($purposeLines)) $purposeLines = ['—','—','—'];
          @endphp
          @for ($i=0; $i<3; $i++)
            <div style="border-bottom:1px solid #000;height:14px;margin-bottom:6px">{{ $purposeLines[$i] ?? '—' }}</div>
          @endfor
        </div>
      </div>

          <div style="display:flex;justify-content:space-between;margin-top:14px">
        <div style="width:48%">
          <div style="font-style:italic">Requested by:</div>
          @if(isset($sigs['submission']['uri']))
            <img src="{{ $sigs['submission']['uri'] }}" alt="requestor signature" style="max-height:48px; display:block; margin:0 auto 4px;" />
          @elseif(!empty($request->user->electronic_signature))
            <img src="{{ asset('storage/' . $request->user->electronic_signature) }}" alt="requestor signature" style="max-height:48px; display:block; margin:0 auto 4px;" />
          @endif
          <div style="border-bottom:1px solid #000;width:78%;margin:0 auto"></div>
          @if(isset($sigs['submission'])) <div class="dig-badge">✓ Digitally Signed · {{ optional($sigs['submission']['signed_at'])->format('M d, Y H:i') }}</div> @endif
          <div style="font-weight:bold;margin-top:4px;text-align:center">{{ $request->user->name ?? '—' }}</div>
          <div style="font-size:12px;margin-top:6px">Name & Signature of Requisitioner</div>
          <div style="display:flex;align-items:center;gap:8px;margin-top:6px">
            <div style="font-size:12px">Position:</div>
            <div style="border-bottom:1px solid #000;width:60%">{{ $request->user->position ?? '—' }}</div>
          </div>
        </div>

        <div style="width:48%">
          @php
            $dcSig = null;
            $dcName = $request->divisionChief->name ?? '—';
            // show division chief signature only if request was approved by division chief
            if (in_array($request->status, ['Approved', 'OCD Approved'])) {
                if (!empty($request->divisionChief->electronic_signature)) {
                    $dcSig = $request->divisionChief->electronic_signature;
                } else {
                    try {
                        $divByChief = \App\Models\Division::where('division_chief_id', $request->division_chief_id)->first();
                        if ($divByChief && !empty($divByChief->signature_path)) {
                            $dcSig = $divByChief->signature_path;
                        }
                    } catch (\Throwable $e) {
                        $dcSig = null;
                    }
                }
            }
          @endphp
          <div style="font-style:italic">Noted:</div>
          @if(isset($sigs['dc_approval']['uri']))
            <img src="{{ $sigs['dc_approval']['uri'] }}" alt="division chief signature" style="max-height:48px; display:block; margin:0 auto 4px;" />
          @elseif($dcSig)
            <img src="{{ asset('storage/' . $dcSig) }}" alt="division chief signature" style="max-height:48px; display:block; margin:0 auto 4px;" />
          @else
            <div style="height:48px"></div>
          @endif
          <div style="border-bottom:1px solid #000;width:78%;margin:0 auto"></div>
          @if(isset($sigs['dc_approval'])) <div class="dig-badge">✓ Digitally Signed · {{ optional($sigs['dc_approval']['signed_at'])->format('M d, Y H:i') }}</div> @endif
          <div style="font-weight:bold;margin-top:4px;text-align:center">{{ $dcName }}</div>
          <div style="font-size:12px;margin-top:6px">Name & Signature of Division Chief</div>
        </div>
      </div>

      <div style="margin-top:18px;display:flex;justify-content:space-between;align-items:flex-start">
        <div style="width:48%">
          <div style="font-weight:bold">Recommending _____ Approval / _____ Disapproval:</div>
        </div>
        <div style="width:48%;text-align:right">
          <div style="font-weight:bold">_____ Approved / _____ Disapproved:</div>
        </div>
      </div>

      <div style="margin-top:18px;display:flex;justify-content:space-between">
        <div style="width:48%">
          <div style="text-align:center">
            @if(isset($sigs['fad_approval']['uri']))
              <img src="{{ $sigs['fad_approval']['uri'] }}" alt="FAD chief signature" style="max-height:48px; display:block; margin:0 auto 4px;" />
            @elseif(!empty($fadSig))
              <img src="{{ asset('storage/' . $fadSig) }}" alt="FAD chief signature" style="max-height:48px; display:block; margin:0 auto 4px;" />
            @else
              <div style="height:48px"></div>
            @endif
            @if(isset($sigs['fad_approval'])) <div class="dig-badge">✓ Digitally Signed · {{ $sigs['fad_approval']['name'] }} · {{ optional($sigs['fad_approval']['signed_at'])->format('M d, Y H:i') }}</div> @endif
            <div style="font-weight:bold">{{ $fadName ?? '—' }}</div>
            <div>FAD Chief</div>
          </div>
        </div>
        <div style="width:48%;text-align:center">
          @if(isset($sigs['ocd_approval']['uri']))
            <img src="{{ $sigs['ocd_approval']['uri'] }}" alt="Director signature" style="max-height:48px; display:block; margin:0 auto 4px;" />
          @elseif(!empty($directorSig))
            <img src="{{ $directorSig }}" alt="Director signature" style="max-height:48px; display:block; margin:0 auto 4px;" />
          @else
            <div style="height:40px"></div>
          @endif
          @if(isset($sigs['ocd_approval'])) <div class="dig-badge">✓ Digitally Signed · {{ $sigs['ocd_approval']['name'] }} · {{ optional($sigs['ocd_approval']['signed_at'])->format('M d, Y H:i') }}</div> @endif
          <div style="font-weight:bold;text-align:center">{{ $directorName ?? 'Office of the Campus Director' }}</div>
          <div>Executive/Campus Director</div>
        </div>
      </div>

      <div class="no-print" style="margin-top:18px;text-align:right">
        </div>
      </div>

      <div class="copy">

    <div class="header">
      <h2>PHILIPPINE SCIENCE HIGH SCHOOL SYSTEM</h2>
      <div style="margin-top:6px;">Campus: CARAGA REGION CAMPUS IN BUTUAN CITY</div>
      <h3 style="margin-top:10px;">PERMIT TO USE SCHOOL VEHICLE</h3>
    </div>


        <div class="card" style="padding:12px">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px">
            <div style="flex:1;">
              <div style="font-weight:bold">Vehicle Requested:</div>
              <div style="border-bottom:1px solid #000;padding:6px 4px;width:70%">{{ $request->vehicle_type ?? '_________________________' }}</div>
            </div>
            <div style="width:260px;text-align:right">
              <div style="display:flex;justify-content:space-between;gap:8px">
                <div style="min-width:50px">No:</div>
                <div style="border-bottom:1px solid #000;width:150px;text-align:left">{{ $request->id }}</div>
              </div>
              <div style="height:8px"></div>
              <div style="display:flex;justify-content:space-between;gap:8px">
                <div style="min-width:50px">Date:</div>
                <div style="border-bottom:1px solid #000;width:150px;text-align:left">{{ optional($request->created_at)->format('F d, Y') }}</div>
              </div>
            </div>
          </div>

          <div style="display:flex;gap:18px">
            <div style="flex:1">
              <div style="display:flex;justify-content:space-between;margin-bottom:8px">
                <div style="width:45%"><strong>Date of Trip:</strong></div>
                <div style="width:50%;border-bottom:1px solid #000">
                  @php
                    $allDates2 = [];
                    if (!empty($request->date_needed_multiple) && is_array($request->date_needed_multiple)) {
                        foreach ($request->date_needed_multiple as $d) {
                            try { $allDates2[] = \Carbon\Carbon::parse($d)->format('M d, Y'); } catch (\Throwable $e) { $allDates2[] = $d; }
                        }
                    } elseif (!empty($request->date_needed)) {
                        try { $allDates2[] = \Carbon\Carbon::parse($request->date_needed)->format('M d, Y'); } catch (\Throwable $e) { $allDates2[] = $request->date_needed; }
                    }
                  @endphp
                  @if(count($allDates2) > 0) {{ implode(', ', $allDates2) }} @else — @endif
                </div>
              </div>
              <div style="display:flex;justify-content:space-between;margin-bottom:8px">
                <div style="width:45%"><strong>No. of Passengers:</strong></div>
                <div style="width:50%;border-bottom:1px solid #000">{{ $request->passengers ?? '—' }}</div>
              </div>

              <div style="margin-top:6px"><strong>Destination/s:</strong></div>
              @for ($i=0; $i<3; $i++)
                <div style="border-bottom:1px solid #000;height:14px;margin-bottom:6px">{{ $destLines[$i] ?? '—' }}</div>
              @endfor
            </div>

            <div style="flex:1">
              <div style="display:flex;justify-content:space-between;margin-bottom:8px">
                <div style="width:45%"><strong>Time of Departure:</strong></div>
                <div style="width:50%;border-bottom:1px solid #000">{{ $request->time_of_departure ?? '—' }}</div>
              </div>
              <div style="display:flex;justify-content:space-between;margin-bottom:8px">
                <div style="width:45%"><strong>Time of Arrival:</strong></div>
                <div style="width:50%;border-bottom:1px solid #000">{{ $request->eta ?? '—' }}</div>
              </div>

              <div style="margin-top:6px"><strong>Purpose/s:</strong></div>
              @for ($i=0; $i<3; $i++)
                <div style="border-bottom:1px solid #000;height:14px;margin-bottom:6px">{{ $purposeLines[$i] ?? '—' }}</div>
              @endfor
            </div>
          </div>

          <div style="display:flex;justify-content:space-between;margin-top:14px">
            <div style="width:48%">
              <div style="font-style:italic">Requested by:</div>
              @if(isset($sigs['submission']['uri']))
                <img src="{{ $sigs['submission']['uri'] }}" alt="requestor signature" style="max-height:48px; display:block; margin:0 auto 4px;" />
              @elseif(!empty($request->user->electronic_signature))
                <img src="{{ asset('storage/' . $request->user->electronic_signature) }}" alt="requestor signature" style="max-height:48px; display:block; margin:0 auto 4px;" />
              @endif
              <div style="border-bottom:1px solid #000;width:78%;margin:0 auto"></div>
              @if(isset($sigs['submission'])) <div class="dig-badge">✓ Digitally Signed · {{ optional($sigs['submission']['signed_at'])->format('M d, Y H:i') }}</div> @endif
              <div style="font-weight:bold;margin-top:4px;text-align:center">{{ $request->user->name ?? '—' }}</div>
              <div style="font-size:12px;margin-top:6px">Name & Signature of Requisitioner</div>
              <div style="display:flex;align-items:center;gap:8px;margin-top:6px">
                <div style="font-size:12px">Position:</div>
                <div style="border-bottom:1px solid #000;width:60%">{{ $request->user->position ?? '—' }}</div>
              </div>
            </div>

            <div style="width:48%">
              <div style="font-style:italic">Noted:</div>
              @if(isset($sigs['dc_approval']['uri']))
                <img src="{{ $sigs['dc_approval']['uri'] }}" alt="division chief signature" style="max-height:48px; display:block; margin:0 auto 4px;" />
              @elseif($dcSig)
                <img src="{{ asset('storage/' . $dcSig) }}" alt="division chief signature" style="max-height:48px; display:block; margin:0 auto 4px;" />
              @else
                <div style="height:48px"></div>
              @endif
              <div style="border-bottom:1px solid #000;width:78%;margin:0 auto"></div>
              @if(isset($sigs['dc_approval'])) <div class="dig-badge">✓ Digitally Signed · {{ optional($sigs['dc_approval']['signed_at'])->format('M d, Y H:i') }}</div> @endif
              <div style="font-weight:bold;margin-top:4px;text-align:center">{{ $dcName }}</div>
              <div style="font-size:12px;margin-top:6px">Name & Signature of Division Chief</div>
            </div>
          </div>

          <div style="margin-top:18px;display:flex;justify-content:space-between;align-items:flex-start">
            <div style="width:48%">
              <div style="font-weight:bold">Recommending _____ Approval / _____ Disapproval:</div>
            </div>
            <div style="width:48%;text-align:right">
              <div style="font-weight:bold">_____ Approved / _____ Disapproved:</div>
            </div>
          </div>

          <div style="margin-top:18px;display:flex;justify-content:space-between">
            <div style="width:48%">
              <div style="text-align:center">
                @if(isset($sigs['fad_approval']['uri']))
                  <img src="{{ $sigs['fad_approval']['uri'] }}" alt="FAD chief signature" style="max-height:48px; display:block; margin:0 auto 4px;" />
                @elseif(!empty($fadSig))
                  <img src="{{ asset('storage/' . $fadSig) }}" alt="FAD chief signature" style="max-height:48px; display:block; margin:0 auto 4px;" />
                @else
                  <div style="height:40px"></div>
                @endif
                @if(isset($sigs['fad_approval'])) <div class="dig-badge">✓ Digitally Signed · {{ $sigs['fad_approval']['name'] }} · {{ optional($sigs['fad_approval']['signed_at'])->format('M d, Y H:i') }}</div> @endif
                <div style="font-weight:bold">{{ $fadName ?? '—' }}</div>
                <div>FAD Chief</div>
              </div>
            </div>
            <div style="width:48%;text-align:center">
              @if(isset($sigs['ocd_approval']['uri']))
                <img src="{{ $sigs['ocd_approval']['uri'] }}" alt="Director signature" style="max-height:48px; display:block; margin:0 auto 4px;" />
              @elseif(!empty($directorSig))
                <img src="{{ $directorSig }}" alt="Director signature" style="max-height:48px; display:block; margin:0 auto 4px;" />
              @else
                <div style="height:40px"></div>
              @endif
              @if(isset($sigs['ocd_approval'])) <div class="dig-badge">✓ Digitally Signed · {{ $sigs['ocd_approval']['name'] }} · {{ optional($sigs['ocd_approval']['signed_at'])->format('M d, Y H:i') }}</div> @endif
              <div style="font-weight:bold;text-align:center">{{ $directorName ?? 'Office of the Campus Director' }}</div>
              <div>Executive/Campus Director</div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Attached Driver's Trip Ticket: starts on a new page -->
      <div class="attachment-page" style="page-break-before:always;margin-top:6mm">
        <style type="text/css">
        .tg  {border-collapse:collapse;border-spacing:0;}
        /* allow images to show fully and reduce heavy horizontal padding */
        .tg td{border-color:black;border-style:solid;border-width:1px;font-family:Arial, sans-serif;font-size:14px;
          overflow:visible;padding:4px 8px;word-break:normal;}
        .tg th{border-color:black;border-style:solid;border-width:1px;font-family:Arial, sans-serif;font-size:14px;
          font-weight:normal;overflow:visible;padding:4px 8px;word-break:normal;}
        .tg .tg-i1ya{border-color:#ffffff;font-size:11px;font-weight:bold;text-align:center;vertical-align:top}
        .tg .tg-zrzq{border-color:#ffffff;font-size:11px;font-weight:bold;text-align:left;text-decoration:underline;vertical-align:top}
        .tg .tg-dfg1{border-color:#ffffff;font-size:11px;text-align:center;vertical-align:top}
        .tg .tg-pmdb{border-color:#ffffff;font-size:11px;text-align:left;vertical-align:top}
        .tg .tg-d5m0{border-color:#ffffff;font-size:11px;font-weight:bold;text-align:center;text-decoration:underline;vertical-align:top}
        .tg .tg-igtm{border-color:#ffffff;font-size:11px;text-align:right;vertical-align:top}
        .tg .tg-04eo{border-color:#ffffff;font-size:11px;font-weight:bold;text-align:left;vertical-align:top}
        @media print{ .attachment-page{page-break-before:always} }
        </style>

        <table class="tg" style="table-layout: fixed; width: 692px"><colgroup>
        <col style="width: 90px">
        <col style="width: 283px">
        <col style="width: 180px">
        <col style="width: 111px">
        <col style="width: 75px">
        </colgroup>
        <thead>
          <tr>
            <th class="tg-pmdb" style="padding:4px 8px"><img src="{{ asset('images/pshslogo.png') }}" style="width:55px;height:auto;display:block;margin:0 auto;" alt="logo"/></th>
            <th class="tg-pmdb" colspan="4">Republic of the Philippines<br>Department of Science and Technology<br><span style="font-weight:bold">PHILIPPINE SCIENCE HIGH SCHOOL</span><br>CARAGA REGION SCHOOL IN BUTUAN CITY</th>
          </tr></thead>
        <tbody>
          <tr>
            <td class="tg-dfg1" colspan="5"><span style="font-weight:bold"><br><br><br>DRIVER'S TRIP TICKET<br><br><br></span></td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb"></td>
            <td class="tg-igtm"><span style="font-weight:400;font-style:normal">Locator Slip No.</span></td>
            <td class="tg-pmdb">___________</td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb"></td>
            <td class="tg-zrzq"></td>
            <td class="tg-igtm"><span style="font-weight:400;font-style:normal">Date:</span></td>
            <td class="tg-pmdb"><span style="font-weight:700;font-style:normal;text-decoration:underline">{{ optional($request->created_at)->format('m/d/Y') }}</span></td>
          </tr>
          <tr>
            <td class="tg-pmdb" colspan="5"><span style="font-weight:bold">A. To be filled up by the Administrative Official, authorizing to travel:</span></td>
          </tr>
          <tr>
            <td class="tg-pmdb" colspan="5"><span style="font-weight:bold">(Note: Item 3-6 to be filled up by the Passenger)</span></td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb"><span style="font-weight:400;font-style:normal">1. Name of Driver of Vehicle</span></td>
            <td class="tg-pmdb" colspan="2"><span style="font-weight:bold;text-decoration:underline">{{ strtoupper(optional($request->driver)->name ?? '') }}</span></td>
            <td class="tg-pmdb"></td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb">2. Gov't Vehicle Used and Plate No.</td>
            <td class="tg-pmdb" colspan="2"><span style="font-weight:bold;text-decoration:underline">{{ strtoupper($request->vehicle_type ?? '') }}</span></td>
            <td class="tg-pmdb"></td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb" rowspan="3">3. Name of Authorized Passenger</td>
            <td class="tg-pmdb" colspan="2"><span style="font-weight:bold;text-decoration:underline">{{ strtoupper($request->user->name ?? '') }}</span></td>
            <td class="tg-pmdb"></td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb" colspan="2"><span style="font-weight:400;font-style:normal">________________________________</span></td>
            <td class="tg-pmdb"></td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb" colspan="2"><span style="font-weight:400;font-style:normal">________________________________</span></td>
            <td class="tg-pmdb"></td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb">4. Destination</td>
            <td class="tg-pmdb" colspan="2"><span style="font-weight:bold;text-decoration:underline">{{ strtoupper(is_array($request->destination) ? implode(', ', $request->destination) : ($request->destination ?? '')) }}</span></td>
            <td class="tg-pmdb"></td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb">5. Purpose</td>
            <td class="tg-pmdb" colspan="2"><span style="font-weight:bold;text-decoration:underline">{{ strtoupper($request->purpose ?? '') }}</span></td>
            <td class="tg-pmdb"></td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb">6. Date(s) of Travel</td>
            <td class="tg-pmdb" colspan="2"><span style="font-weight:bold;text-decoration:underline">@php
                $allDates3 = [];
                if (!empty($request->date_needed_multiple) && is_array($request->date_needed_multiple)) {
                    foreach ($request->date_needed_multiple as $d) {
                        try { $allDates3[] = \Carbon\Carbon::parse($d)->format('M d, Y'); } catch (\Throwable $e) { $allDates3[] = $d; }
                    }
                } elseif (!empty($request->date_needed)) {
                    try { $allDates3[] = \Carbon\Carbon::parse($request->date_needed)->format('M d, Y'); } catch (\Throwable $e) { $allDates3[] = $request->date_needed; }
                }
                echo count($allDates3) > 0 ? implode(', ', $allDates3) : '—';
            @endphp</span></td>
            <td class="tg-pmdb"></td>
          </tr>
          <!-- Recommending Approval and Approval sections -->
          <tr>
            <td class="tg-pmdb" colspan="5" style="padding:10px; background:#f8fafc">
              <div style="display:flex;gap:12px;align-items:stretch">
                <div style="flex:1;padding:8px">
                  <div style="font-weight:bold;margin-bottom:6px">Recommending Approval</div>
                  <div style="text-align:center">
                    @if(isset($sigs['fad_approval']['uri']))
                      <img src="{{ $sigs['fad_approval']['uri'] }}" alt="FAD signature" style="max-height:48px; display:block; margin:0 auto 4px;" />
                    @elseif(!empty($fadSig))
                      <img src="{{ asset('storage/' . $fadSig) }}" alt="FAD signature" style="max-height:48px; display:block; margin:0 auto 4px;" />
                    @else
                      <div style="height:48px"></div>
                    @endif
                    @if(isset($sigs['fad_approval'])) <div class="dig-badge">✓ Digitally Signed · {{ $sigs['fad_approval']['name'] }} · {{ optional($sigs['fad_approval']['signed_at'])->format('M d, Y H:i') }}</div> @endif
                    <div style="font-weight:bold">{{ $fadName ?? '—' }}</div>
                    <div>FAD Chief</div>
                  </div>
                </div>
                <div style="flex:1;padding:8px">
                  <div style="font-weight:bold;margin-bottom:6px">Approval</div>
                  <div style="text-align:center">
                    @if(isset($sigs['ocd_approval']['uri']))
                      <img src="{{ $sigs['ocd_approval']['uri'] }}" alt="Director signature" style="max-height:48px; display:block; margin:0 auto 4px;" />
                    @elseif(!empty($directorSig))
                      <img src="{{ $directorSig }}" alt="Director signature" style="max-height:48px; display:block; margin:0 auto 4px;" />
                    @else
                      <div style="height:48px"></div>
                    @endif
                    @if(isset($sigs['ocd_approval'])) <div class="dig-badge">✓ Digitally Signed · {{ $sigs['ocd_approval']['name'] }} · {{ optional($sigs['ocd_approval']['signed_at'])->format('M d, Y H:i') }}</div> @endif
                    <div style="font-weight:bold">{{ $directorName ?? '—' }}</div>
                    <div>Campus Director</div>
                  </div>
                </div>
              </div>
            </td>
          </tr>
          <!-- The rest of the driver's trip ticket fields (driver to complete) -->
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb" colspan="4">B. To be filled by the Driver:</td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb" colspan="2">1. Time of Departure from Office/Garage</td>
            <td class="tg-pmdb">_________</td>
            <td class="tg-pmdb">a.m./p.m.</td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb" colspan="2">2. Time of Arrival at (per item no. 4 above)</td>
            <td class="tg-pmdb">_________</td>
            <td class="tg-pmdb">a.m./p.m.</td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb" colspan="2">3. Time of Departure from (per item no. 4 above)</td>
            <td class="tg-pmdb">_________</td>
            <td class="tg-pmdb">a.m./p.m.</td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb" colspan="2">4. Time of Arrival Back to Office/Garage</td>
            <td class="tg-pmdb">_________</td>
            <td class="tg-pmdb">a.m./p.m.</td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb" colspan="2">5. Approximate Distance Travelled (to &amp; from)</td>
            <td class="tg-pmdb">_________</td>
            <td class="tg-pmdb">kms./miles</td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb" colspan="2">6. GOL Issued, Purchased and Consumed:</td>
            <td class="tg-pmdb">_________</td>
            <td class="tg-pmdb">liters</td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb" colspan="2">a. Balance in Tank</td>
            <td class="tg-pmdb">_________</td>
            <td class="tg-pmdb">liters</td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb" colspan="2">b. Issued by Office from Stock</td>
            <td class="tg-pmdb">_________</td>
            <td class="tg-pmdb">liters</td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb" colspan="2">c. Add: Purchased during Trip</td>
            <td class="tg-pmdb">_________</td>
            <td class="tg-pmdb">liters</td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-dfg1" colspan="2"><span style="font-weight:bold;font-style:italic">T O T A L</span></td>
            <td class="tg-pmdb">_________</td>
            <td class="tg-pmdb">liters</td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb" colspan="2">d. Deduct: GOL used during the Trip (to &amp; from)</td>
            <td class="tg-pmdb">_________</td>
            <td class="tg-pmdb">liters</td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb" colspan="2">e. Balance in Tank at the End of Trip</td>
            <td class="tg-pmdb">_________</td>
            <td class="tg-pmdb">liters</td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb" colspan="2">7. Oil issued/purchased/consumed</td>
            <td class="tg-pmdb">_________</td>
            <td class="tg-pmdb">kms./miles</td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb" colspan="2">8. Speedometer readings:</td>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb"></td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb" colspan="2">At the beginning of trip</td>
            <td class="tg-pmdb">_________</td>
            <td class="tg-pmdb">kms./miles</td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb" colspan="2">At the end of the trip</td>
            <td class="tg-pmdb">_________</td>
            <td class="tg-pmdb">kms./miles</td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb" colspan="2">Distance travelled (per item no.5 above)</td>
            <td class="tg-pmdb">_________</td>
            <td class="tg-pmdb">kms./miles</td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb" colspan="4">9. R E M A R K S:</td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb" colspan="4">I hereby certify to the correctness of the above statement of records of travel:</td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb" colspan="4"></td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb"></td>
            <td class="tg-d5m0" colspan="3">{{ strtoupper(optional($request->driver)->name ?? '') }}</td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb"></td>
            <td class="tg-i1ya" colspan="3">DRIVER</td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb" colspan="4">I hereby certify that I/We used this vehicle on official business as stated above:</td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-i1ya" colspan="4">Signature of Passenger/s</td>
          </tr>
          <tr>
            <td class="tg-pmdb"></td>
            <td class="tg-pmdb" colspan="4">______________________________________________________________________________________________</td>
          </tr>
        </tbody></table>
      </div>
    </div>
  </div>
</body>
</html>
