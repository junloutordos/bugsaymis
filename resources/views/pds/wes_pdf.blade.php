<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: Arial, Helvetica, sans-serif;
      font-size: 10pt;
      color: #000;
      background: #fff;
    }

    .attachment-label {
      font-style: italic;
      font-size: 10pt;
      margin-bottom: 6px;
    }

    .header-box {
      border: 2px solid #000;
      text-align: center;
      padding: 8px 10px;
      background: #e0e0e0;
      font-size: 14pt;
      font-weight: bold;
      font-style: italic;
      letter-spacing: 1px;
      margin-bottom: 0;
    }

    .instructions-box {
      border: 1px solid #000;
      border-top: none;
      padding: 8px 12px;
      font-size: 10pt;
    }
    .instructions-box strong { font-size: 10pt; }
    .instructions-box p { margin-bottom: 4px; }

    .entries-box {
      border: 1px solid #000;
      border-top: none;
      padding: 10px 14px;
    }

    .entry {
      margin-bottom: 18px;
      padding-bottom: 14px;
      border-bottom: 1px dashed #aaa;
    }
    .entry:last-child { border-bottom: none; margin-bottom: 0; }

    .entry ul {
      list-style: disc;
      margin-left: 24px;
      margin-top: 4px;
    }
    .entry ul li { margin-bottom: 2px; line-height: 1.5; }

    .entry ul.sub {
      list-style: circle;
      margin-left: 48px;
    }

    .field-row {
      margin-bottom: 3px;
      line-height: 1.6;
    }
    .field-label { font-weight: bold; }

    .signature-block {
      margin-top: 36px;
      text-align: right;
      padding-right: 40px;
    }
    .signature-line {
      border-bottom: 1px solid #000;
      width: 260px;
      margin-left: auto;
      margin-bottom: 4px;
      height: 0;
    }
    .signature-caption {
      font-size: 10pt;
      text-align: center;
      width: 260px;
      margin-left: auto;
      font-style: italic;
    }
    .date-line {
      margin-top: 16px;
      font-size: 10pt;
    }

    .no-entries {
      padding: 20px;
      text-align: center;
      font-style: italic;
      color: #555;
      font-size: 10pt;
    }
  </style>
</head>
<body>

  <div class="attachment-label">Attachment to CS Form No. 212</div>

  <div class="header-box">WORK EXPERIENCE SHEET</div>

  <div class="instructions-box">
    <p ><strong>Instructions:</strong>&nbsp;&nbsp;
      <strong>1.</strong> <span style="font-style: italic;">Include only the work experiences relevant to the position being applied to.</span>
    </p>
    <p style="margin-left: 88px; font-style: italic;">
      <strong>2.</strong> The duration should include start and finish dates, if known, month in abbreviated form,
      if known, and year in full. For the current position, use the word <em>Present</em>, e.g., 1998-Present.
      Work experience should be listed from most recent first.
    </p>
  </div>

  <div class="entries-box">
    @if($pds->workExperienceSheets->isEmpty())
      <div class="no-entries">No work experience entries have been recorded.</div>
    @else
      @foreach($pds->workExperienceSheets as $entry)
        <div class="entry">
          <ul>
            <li class="field-row">
              <span class="field-label">Duration:</span>
              {{ $entry->from_date ?? '' }}
              @if($entry->from_date || $entry->to_date) – @endif
              {{ $entry->to_date ?? '' }}
            </li>
            <li class="field-row">
              <span class="field-label">Position:</span>
              {{ $entry->position ?? '' }}
            </li>
            <li class="field-row">
              <span class="field-label">Name of Office/Unit:</span>
              {{ $entry->office_unit ?? '' }}
            </li>
            <li class="field-row">
              <span class="field-label">Immediate Supervisor:</span>
              {{ $entry->immediate_supervisor ?? '' }}
            </li>
            <li class="field-row">
              <span class="field-label">Name of Agency/Organization and Location:</span>
              {{ $entry->agency_organization_location ?? '' }}
            </li>
          </ul>

          @php $accomplishments = $entry->accomplishments ?? []; @endphp
          <ul style="margin-left: 40px; margin-top: 6px;">
            <li class="field-label">List of Accomplishments and Contributions
              @if(empty(array_filter($accomplishments))) (if any) @endif
            </li>
            @if(!empty(array_filter($accomplishments)))
              <ul class="sub">
                @foreach($accomplishments as $item)
                  @if(trim($item ?? ''))
                    <li>{{ trim($item) }}</li>
                  @endif
                @endforeach
              </ul>
            @endif
          </ul>

          @php $duties = $entry->summary_of_duties ?? []; @endphp
          <ul style="margin-left: 40px; margin-top: 4px;">
            <li class="field-label">Summary of Actual Duties</li>
            @if(!empty(array_filter($duties)))
              <ul class="sub">
                @foreach($duties as $item)
                  @if(trim($item ?? ''))
                    <li>{{ trim($item) }}</li>
                  @endif
                @endforeach
              </ul>
            @endif
          </ul>
        </div>
      @endforeach
    @endif
  </div>

  <div class="signature-block">
    
    <div class="signature-caption">
      @php
        $fn  = strtoupper($pds->personalInfo?->first_name ?? '');
        $mi  = $pds->personalInfo?->middle_name ? strtoupper(substr($pds->personalInfo->middle_name, 0, 1)) . '.' : '';
        $sn  = strtoupper($pds->personalInfo?->surname ?? '');
        $fullName = trim("$fn $mi $sn");
      @endphp
      <strong>{{ $fullName }}</strong><div class="signature-line" style="margin-top:0;"></div>
      (Signature over Printed Name<br>of Employee/Applicant)
    </div>
    <div class="date-line">Date: <strong><u>{{ now()->format('F d, Y') }}</u></strong></div>
  </div>

</body>
</html>
