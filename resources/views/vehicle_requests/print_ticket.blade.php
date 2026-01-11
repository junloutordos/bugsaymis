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

      // Resolve Office/Campus Director name: prefer a Division record for the Campus Director,
      // then fallback to user position or role.
      $directorName = null; $directorSig = null;
      try {
        $divDirector = \App\Models\Division::where('division_name', 'Office of the Campus Director')
            ->orWhere('division_name', 'like', '%Campus Director%')
            ->first();
        if ($divDirector && $divDirector->divisionchief) {
          $directorName = $divDirector->divisionchief->name;
          if (!empty($divDirector->signature_path)) {
            $directorSig = $divDirector->signature_path;
          } elseif (!empty($divDirector->divisionchief->electronic_signature)) {
            $directorSig = $divDirector->divisionchief->electronic_signature;
          }
        } else {
          $director = \App\Models\User::where('position', 'like', '%Campus Director%')->first();
          if (! $director) {
            $director = \App\Models\User::whereHas('role', function($q){ $q->where('name', 'like', '%Director%'); })->first();
          }
          if ($director) {
            $directorName = $director->name;
            if (!empty($director->electronic_signature)) $directorSig = $director->electronic_signature;
          }
        }
      } catch (\Throwable $e) {
        $directorName = null; $directorSig = null;
      }
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
            <div style="width:50%;border-bottom:1px solid #000">@if(!empty($request->date_needed)) {{ $request->date_needed }} @else — @endif</div>
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
          @if(!empty($request->user->electronic_signature))
            <img src="{{ asset('storage/' . $request->user->electronic_signature) }}" alt="requestor signature" style="max-height:48px; display:block; margin:0 auto 4px;" />
          @endif
          <div style="border-bottom:1px solid #000;width:78%;margin:0 auto"></div>
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
          @if($dcSig)
            <img src="{{ asset('storage/' . $dcSig) }}" alt="division chief signature" style="max-height:48px; display:block; margin:0 auto 4px;" />
          @else
            <div style="height:48px"></div>
          @endif
          <div style="border-bottom:1px solid #000;width:78%;margin:0 auto"></div>
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
            @if(!empty($fadSig))
              <img src="{{ asset('storage/' . $fadSig) }}" alt="FAD chief signature" style="max-height:48px; display:block; margin:0 auto 4px;" />
            @else
              <div style="height:48px"></div>
            @endif
            <div style="font-weight:bold">{{ $fadName ?? '—' }}</div>
            <div>FAD Chief</div>
          </div>
        </div>
        <div style="width:48%;text-align:center">
          @if(!empty($directorSig))
            <img src="{{ asset('storage/' . $directorSig) }}" alt="Director signature" style="max-height:48px; display:block; margin:0 auto 4px;" />
          @else
            <div style="height:40px"></div>
          @endif
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
                <div style="width:50%;border-bottom:1px solid #000">@if(!empty($request->date_needed)) {{ $request->date_needed }} @else — @endif</div>
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
          @if(!empty($request->user->electronic_signature))
            <img src="{{ asset('storage/' . $request->user->electronic_signature) }}" alt="requestor signature" style="max-height:48px; display:block; margin:0 auto 4px;" />
                  @endif
          <div style="border-bottom:1px solid #000;width:78%;margin:0 auto"></div>
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
              @if($dcSig)
                <img src="{{ asset('storage/' . $dcSig) }}" alt="division chief signature" style="max-height:48px; display:block; margin:0 auto 4px;" />
              @else
                <div style="height:48px"></div>
              @endif
              <div style="border-bottom:1px solid #000;width:78%;margin:0 auto"></div>
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
                @if(!empty($fadSig))
                  <img src="{{ asset('storage/' . $fadSig) }}" alt="FAD chief signature" style="max-height:48px; display:block; margin:0 auto 4px;" />
                @else
                  <div style="height:40px"></div>
                @endif
                <div style="font-weight:bold">{{ $fadName ?? '—' }}</div>
                <div>FAD Chief</div>
              </div>
            </div>
            <div style="width:48%;text-align:center">
              @if(!empty($directorSig))
                <img src="{{ asset('storage/' . $directorSig) }}" alt="Director signature" style="max-height:48px; display:block; margin:0 auto 4px;" />
              @else
                <div style="height:40px"></div>
              @endif
              <div style="font-weight:bold;text-align:center">{{ $directorName ?? 'Office of the Campus Director' }}</div>
              <div>Executive/Campus Director</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
