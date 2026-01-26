<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Clinic Admission Slip - {{ $consultation->id }}</title>
  <style>
    body { font-family: Arial, Helvetica, sans-serif; color: #000; }
    .page { width: 800px; margin: 20px auto; border: 1px solid #000; padding: 18px; }
    .header { text-align: center; margin-bottom: 6px; }
    .grid { display: flex; gap: 12px; }
    .col { flex: 1; }
    .box { border: 1px solid #000; padding: 8px; }
    .small { font-size: 13px; }
    .label { font-weight: bold; }
    .checks { margin-top: 6px; }
    .checks div { margin-bottom: 4px; }
    .footer { margin-top: 12px; }
    @media print { .page { border: none; margin: 0; } button { display:none; } }
    .right { text-align: right; }
  </style>
</head>
<body>
  <div class="page">
    <div class="header">
      <h2>PHILIPPINE SCIENCE HIGH SCHOOL SYSTEM
      <br><u>CARAGA REGION CAMPUS IN BUTUAN CITY</u></h2>
      <h3>CLINIC ADMISSION SLIP</h3>
    </div>

    <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
      <div><span class="label">Date:</span> <u>{{ optional($consultation->created_at)->format('F j, Y') }}</u></div>
      <div><span class="label">Time In:</span> <u>{{ $consultation->time_start ?? '—' }}</u></div>
      <div><span class="label">Time Out:</span> <u>{{ $consultation->time_out ?? '—' }}</u></div>
    </div>
<div style="display:flex; justify-content:space-between; margin-bottom:8px;">
    <div><span class="label">Student:</span> <u>{{ $requestor->name ?? ($consultation->requestor ?? '—') }}</u></div>
    <div><span class="label">Sex:</span> <u>{{ $requestor->sex ?? ($consultation->requestor ?? '—') }}</u></div>
</div>
    <div style="margin-bottom:8px;">

      <div><span class="label">Grade & Section: </span> <u>{{ $requestor->grade_level ?? '—' }} {{ $requestor->section ? '· ' . $requestor->section : '' }}</u></div>
    </div>

    <div class="grid">
      <div class="col box small">
        <div class="label">Nature of Complaint:</div>
        <div class="checks">
          @php
            $concern = $consultation->concern ?? $consultation->reason ?? '';
            $parts = array_map('trim', array_filter(preg_split('/,\\s*/', $concern)));
            $hasConcern = function($needle) use ($concern, $parts) {
                if (! $concern) return false;
                foreach ($parts as $p) {
                    if (stripos($p, $needle) !== false) return true;
                }
                return false;
            };

            $injuryText = '';
            foreach ($parts as $p) {
                if (stripos($p, 'Injury') !== false) {
                    $pos = strpos($p, ':');
                    if ($pos !== false) $injuryText = trim(substr($p, $pos + 1));
                    break;
                }
            }

            $otherComplaint = '';
            foreach ($parts as $p) {
                if (stripos($p, 'Others') !== false) {
                    $pos = strpos($p, ':');
                    if ($pos !== false) $otherComplaint = trim(substr($p, $pos + 1));
                    else $otherComplaint = trim(str_ireplace('Others', '', $p));
                    break;
                }
            }
          @endphp

          <div>[{{ $hasConcern('Not feeling well') ? 'x' : ' ' }}] Not feeling well</div>
          <div>[{{ $hasConcern('Stomachache') ? 'x' : ' ' }}] Stomachache</div>
          <div>[{{ $hasConcern('Headache') ? 'x' : ' ' }}] Headache</div>
          <div>[{{ $hasConcern('Toothache') ? 'x' : ' ' }}] Toothache</div>
          <div>[{{ $hasConcern('Injury') ? 'x' : ' ' }}] Injury{{ $injuryText ? ' - ' . $injuryText : '' }}</div>
          <div>[{{ $otherComplaint ? 'x' : ' ' }}] Other: {{ $otherComplaint }}</div>
        </div>
      </div>

      <div class="col box small">
        <div class="label">Action Taken:</div>
        <div class="checks">
          @php
          $acts = $consultation->action_taken ?? '';
          $parseParts = array_map('trim', array_filter(array_map('trim', explode(';', $acts))));
          $has = function($needle) use ($acts, $parseParts) {
            if (!$acts) return false;
            foreach ($parseParts as $p) {
              if (str_starts_with($p, $needle) || str_contains($p, $needle)) return true;
            }
            return false;
          };

          // attempt to extract details
          $headReason = '';
          foreach ($parseParts as $p) {
            if (str_starts_with($p, 'Head checkup')) {
              $pos = strpos($p, ':');
              if ($pos !== false) $headReason = trim(substr($p, $pos + 1));
            }
          }

          $parentNotified = '';
          foreach ($parseParts as $p) {
            if (str_starts_with($p, 'Parent/Guardian Notified')) {
              $pos = strpos($p, ':');
              if ($pos !== false) $parentNotified = trim(substr($p, $pos + 1));
            }
          }

          $medsText = $consultation->medicine_given ?? '';
          // extract Others in actions
          $otherAction = '';
          foreach ($parseParts as $p) {
            if (str_starts_with($p, 'Others')) {
              $pos = strpos($p, ':');
              if ($pos !== false) $otherAction = trim(substr($p, $pos + 1));
              else $otherAction = trim(str_replace('Others', '', $p));
            }
          }
          @endphp

          <div>[{{ $has('Student laid/sat in clinic for 20 minutes or less') ? 'x' : ' ' }}] Student laid/sat in clinic for 20 minutes or less</div>
          <div>[{{ $has('Student laid/sat in clinic for 20 minutes or more') ? 'x' : ' ' }}] Student laid/sat in clinic for 20 minutes or more</div>
          <div>[{{ $has('Temperature Taken') ? 'x' : ' ' }}] Temperature Taken</div>
          <div>[{{ $has('Ice Pack applied to the affected Area') ? 'x' : ' ' }}] Ice Pack applied to the affected Area</div>
          <div>[{{ $has('Affected area cleaned') ? 'x' : ' ' }}] Affected area cleaned</div>
          <div>[{{ $has('Band aid applied to affected area') ? 'x' : ' ' }}] Band aid applied to affected area</div>
          <div>[{{ $has('Head checkup') ? 'x' : ' ' }}] Head checkup{{ $headReason ? ': ' . $headReason : '' }}</div>
          <div>[{{ $has('Parent/Guardian Notified') ? 'x' : ' ' }}] Parent/Guardian Notified{{ $parentNotified ? ': ' . $parentNotified : '' }}</div>
          <div>[{{ $has('Medications Given') ? 'x' : ' ' }}] Medications given{{ $medsText ? ': ' . $medsText : '' }}</div>
          <div>[{{ $has('Others') || $otherAction ? 'x' : ' ' }}] Other: {{ $otherAction }}</div>
          <div>@if(empty($acts)) &nbsp; @endif</div>
        </div>
      </div>

      <div class="col box small">
        <div class="label">Disposition of student:</div>
        <div class="checks">
          @php $disp = $consultation->disposition ?? ''; @endphp
          <div>[{{ str_contains($disp, 'Returned to class, feeling better') ? 'x' : ' ' }}] Returned to class, feeling better</div>
          <div>[{{ str_contains($disp, "Returned to class at parent's/guardian's request") ? 'x' : ' ' }}] Returned to class at parent's/guardian's request</div>
          <div>[{{ str_contains($disp, 'Returned to class, unable to contact parent/guardian') ? 'x' : ' ' }}] Returned to class, unable to contact parent/guardian</div>
          <div>[{{ str_contains($disp, 'Sent home') ? 'x' : ' ' }}] Sent home</div>
          <div>[{{ str_contains($disp, 'Teacher Notified') || str_contains($disp, 'Teacher notified') ? 'x' : ' ' }}] Teacher notified</div>
          <div>[{{ str_contains($disp, 'Referral was made to Health Care Provider') ? 'x' : ' ' }}] Referral was made to Health Care Provider</div>
          <div>[{{ str_contains($disp, 'Transported to Hospital') ? 'x' : ' ' }}] Transported to Hospital</div>
          <div>[{{ str_contains($disp, 'Copy of clinic pass sent home') ? 'x' : ' ' }}] Copy of clinic pass sent home</div>
          @php
            $otherText = '';
            if (str_contains($disp, 'Others')) {
                // look for 'Others' followed by ':' or 'Others' entry
                $parts = preg_split('/;\\s*/', $disp);
                foreach ($parts as $p) {
                    if (str_starts_with(trim($p), 'Others')) {
                        $pos = strpos($p, ':');
                        if ($pos !== false) {
                            $otherText = trim(substr($p, $pos + 1));
                        } else {
                            $otherText = trim(str_replace('Others', '', $p));
                        }
                        break;
                    }
                }
            }
          @endphp
          <div>[{{ $otherText ? 'x' : ' ' }}] Other: {{ $otherText }}</div>
        </div>
        <div style="margin-top:8px;"><span class="label">Remarks:</span> {{ $consultation->notes ?? '' }}</div>
      </div>
    </div>

    <div class="footer">
      <div style="margin-top:18px;">
        <div style="display:flex; justify-content:space-between;">
          <div>
            <div class="label">Handled by:</div>
            <div style="margin-top:18px;"><b><u>{{ $nurse ?? 'School Nurse' }}</u></b><br>School Nurse</div>
            <hr>
            PSHS-00-F-HSU-04-Ver02-Rev0-02/01/20
          </div>
          <div class="right">
            <button onclick="window.print()">Print</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
