<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Personal Data Sheet - Print</title>
    <style>
        @page { size: A4; margin: 12mm; }
        body { font-family: Arial, Helvetica, sans-serif; color:#000; margin:0; }
        .page { width:210mm; min-height:297mm; padding:12mm; box-sizing:border-box; }
        .page-break { page-break-after: always; }
        h1 { text-align:center; font-size:16px; margin:6px 0; }
        h2 { font-size:13px; margin:8px 0; }
        .label { font-weight:700; font-size:11px; }
        .field { font-size:12px; padding:6px; border:1px solid #ccc; background:#fff; min-height:20px; }
        table.form { width:100%; border-collapse:collapse; font-size:12px; }
        table.form th, table.form td { border:1px solid #ccc; padding:6px; vertical-align:top; }
        .meta { font-size:11px; text-align:right; margin-top:8px; }
        @media print { #controls { display:none; } }
        .photo { max-width:90px; max-height:110px; }
    </style>
</head>
<body>

<div id="controls" style="padding:8px; background:#f6f6f6; border-bottom:1px solid #e5e5e5; text-align:right;">
    <button onclick="window.print()">Print</button>
    <a href="{{ url()->current() }}" style="margin-left:8px;">Refresh</a>
</div>

<!-- PAGE 1 -->
<div class="page">
    <h1>PERSONAL DATA SHEET</h1>
    <h2>I. Personal Information</h2>
    <table class="form" style="margin-bottom:8px;">
        <tr>
            <td style="width:25%"><div class="label">SURNAME</div><div class="field">{{ strtoupper($pds->personalInfo->surname ?? '') }}</div></td>
            <td style="width:25%"><div class="label">FIRST NAME</div><div class="field">{{ strtoupper($pds->personalInfo->first_name ?? '') }}</div></td>
            <td style="width:25%"><div class="label">MIDDLE NAME</div><div class="field">{{ strtoupper($pds->personalInfo->middle_name ?? '') }}</div></td>
            <td style="width:25%"><div class="label">NAME EXT.</div><div class="field">{{ $pds->personalInfo->name_ext ?? '' }}</div></td>
        </tr>
        <tr>
            <td><div class="label">DATE OF BIRTH</div><div class="field">{{ $pds->personalInfo->date_of_birth ?? '' }}</div></td>
            <td><div class="label">PLACE OF BIRTH</div><div class="field">{{ $pds->personalInfo->place_of_birth ?? '' }}</div></td>
            <td><div class="label">SEX</div><div class="field">{{ $pds->personalInfo->sex ?? '' }}</div></td>
            <td><div class="label">CIVIL STATUS</div><div class="field">{{ $pds->personalInfo->civil_status ?? '' }}</div></td>
        </tr>
        <tr>
            <td colspan="3"><div class="label">RESIDENTIAL ADDRESS</div><div class="field">{{ trim(($pds->personalInfo->residential_house ?? '') . ' ' . ($pds->personalInfo->residential_street ?? '')) }}, {{ $pds->personalInfo->residential_city ?? '' }}</div></td>
            <td><div class="label">ZIP CODE</div><div class="field">{{ $pds->personalInfo->residential_zip ?? '' }}</div></td>
        </tr>
        <tr>
            <td colspan="3"><div class="label">PERMANENT ADDRESS</div><div class="field">{{ trim(($pds->personalInfo->permanent_house ?? '') . ' ' . ($pds->personalInfo->permanent_street ?? '')) }}, {{ $pds->personalInfo->permanent_city ?? '' }}</div></td>
            <td><div class="label">ZIP CODE</div><div class="field">{{ $pds->personalInfo->permanent_zip ?? '' }}</div></td>
        </tr>
        <tr>
            <td><div class="label">TELEPHONE / MOBILE</div><div class="field">{{ ($pds->personalInfo->telephone_no ?? '') }} / {{ ($pds->personalInfo->mobile_no ?? '') }}</div></td>
            <td colspan="2"><div class="label">EMAIL</div><div class="field">{{ $pds->personalInfo->email_address ?? '' }}</div></td>
            <td><div class="label">CITIZENSHIP</div><div class="field">
                @if(!empty($pds->personalInfo->citizenship_filipino))
                    Filipino
                @elseif(!empty($pds->personalInfo->citizenship_dual))
                    Dual ({{ $pds->personalInfo->citizenship_dual_type ?? '' }} - {{ $pds->personalInfo->citizenship_dual_country ?? '' }})
                @else
                    {{ $pds->personalInfo->citizenship ?? '' }}
                @endif
            </div></td>
        </tr>
    </table>
    <div class="meta">Page 1 of 4</div>
</div>
<div class="page-break"></div>

<!-- PAGE 2 -->
<div class="page">
    <h2>II. Family Background</h2>
    <table class="form" style="margin-bottom:8px;">
        <tr>
            <td style="width:25%"><div class="label">SPOUSE'S SURNAME</div><div class="field">{{ $pds->familyBackground->spouse_surname ?? '' }}</div></td>
            <td style="width:25%"><div class="label">SPOUSE'S FIRST NAME</div><div class="field">{{ $pds->familyBackground->spouse_first_name ?? '' }}</div></td>
            <td style="width:25%"><div class="label">SPOUSE'S MIDDLE NAME</div><div class="field">{{ $pds->familyBackground->spouse_middle_name ?? '' }}</div></td>
            <td style="width:25%"><div class="label">SPOUSE'S EXT</div><div class="field">{{ $pds->familyBackground->spouse_name_ext ?? '' }}</div></td>
        </tr>
        <tr>
            <td><div class="label">OCCUPATION</div><div class="field">{{ $pds->familyBackground->spouse_occupation ?? '' }}</div></td>
            <td colspan="2"><div class="label">EMPLOYER/BUSINESS NAME</div><div class="field">{{ $pds->familyBackground->spouse_employer ?? '' }}</div></td>
            <td><div class="label">TEL. NO.</div><div class="field">{{ $pds->familyBackground->spouse_telephone_no ?? '' }}</div></td>
        </tr>
        <tr>
            <td colspan="4"><div class="label">FATHER</div><div class="field">{{ ($pds->familyBackground->father_surname ?? '') ? ($pds->familyBackground->father_surname . ', ' . ($pds->familyBackground->father_first_name ?? '')) : '' }} {{ $pds->familyBackground->father_middle_name ?? '' }}</div></td>
        </tr>
        <tr>
            <td colspan="4"><div class="label">MOTHER (MAIDEN NAME)</div><div class="field">{{ ($pds->familyBackground->mother_maiden_surname ?? '') ? ($pds->familyBackground->mother_maiden_surname . ', ' . ($pds->familyBackground->mother_maiden_first_name ?? '')) : '' }} {{ $pds->familyBackground->mother_maiden_middle_name ?? '' }}</div></td>
        </tr>
    </table>

    <h2 style="margin-top:10px">III. Children</h2>
    <table class="form" style="margin-bottom:8px;">
        <tr><th style="width:70%">NAME</th><th style="width:30%">DATE OF BIRTH</th></tr>
        @foreach($pds->children ?? [] as $child)
            <tr>
                <td>{{ $child->child_name ?? '' }}</td>
                <td>{{ $child->child_date_of_birth ?? '' }}</td>
            </tr>
        @endforeach
        @for($i = count($pds->children ?? []); $i < 6; $i++)
            <tr><td>&nbsp;</td><td>&nbsp;</td></tr>
        @endfor
    </table>

    <h2 style="margin-top:10px">IV. Educational Background</h2>
    <table class="form">
        <tr><th>LEVEL</th><th>SCHOOL NAME</th><th>DEGREE/COURSE</th><th>YEAR GRADUATED</th></tr>
        @foreach($pds->education ?? [] as $edu)
            <tr>
                <td>{{ $edu->level ?? '' }}</td>
                <td>{{ $edu->school_name ?? '' }}</td>
                <td>{{ $edu->degree ?? $edu->degree_course ?? '' }}</td>
                <td>{{ $edu->year_graduated ?? '' }}</td>
            </tr>
        @endforeach
        @for($i = count($pds->education ?? []); $i < 6; $i++)
            <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
        @endfor
    </table>

    <div class="meta">Page 2 of 4</div>
</div>
<div class="page-break"></div>

<!-- PAGE 3 -->
<div class="page">
    <h2>V. Civil Service Eligibility</h2>
    <table class="form" style="margin-bottom:8px;">
        <tr><th>ELIGIBILITY</th><th>RATING</th><th>DATE OF EXAM</th><th>PLACE</th><th>LICENSE NO.</th><th>VALIDITY</th></tr>
        @foreach($pds->eligibility ?? [] as $el)
            <tr>
                <td>{{ $el->eligibility ?? '' }}</td>
                <td>{{ $el->rating ?? '' }}</td>
                <td>{{ $el->exam_date ?? '' }}</td>
                <td>{{ $el->place_taken ?? '' }}</td>
                <td>{{ $el->license_no ?? '' }}</td>
                <td>{{ $el->license_validity ?? '' }}</td>
            </tr>
        @endforeach
        @for($i = count($pds->eligibility ?? []); $i < 6; $i++)
            <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
        @endfor
    </table>

    <h2 style="margin-top:10px">VI. Work Experience</h2>
    <table class="form">
        <tr><th>INCLUSIVE DATES</th><th>POSITION</th><th>DEPARTMENT/AGENCY</th><th>SALARY</th><th>STATUS</th></tr>
        @foreach($pds->workExperience ?? [] as $w)
            <tr>
                <td>{{ $w->date_from ?? '' }} - {{ $w->date_to ?? '' }}</td>
                <td>{{ $w->position ?? '' }}</td>
                <td>{{ $w->agency ?? '' }}</td>
                <td>{{ $w->salary ?? '' }}</td>
                <td>{{ $w->status_of_appointment ?? '' }}</td>
            </tr>
        @endforeach
        @for($i = count($pds->workExperience ?? []); $i < 6; $i++)
            <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
        @endfor
    </table>

    <div class="meta">Page 3 of 4</div>
</div>
<div class="page-break"></div>

<!-- PAGE 4 -->
<div class="page">
    <h2>VII. Voluntary Work, Trainings, Skills & Other Information</h2>
    <table class="form" style="margin-bottom:8px;">
        <tr><th>VOLUNTARY WORK/ORG</th><th>POSITION</th><th>INCLUSIVE DATES</th><th>HOURS</th></tr>
        @foreach($pds->voluntaryWork ?? [] as $v)
            <tr>
                <td>{{ $v->organization ?? '' }}</td>
                <td>{{ $v->position ?? '' }}</td>
                <td>{{ $v->date_from ?? '' }} - {{ $v->date_to ?? '' }}</td>
                <td>{{ $v->hours ?? '' }}</td>
            </tr>
        @endforeach
        @for($i = count($pds->voluntaryWork ?? []); $i < 4; $i++)
            <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
        @endfor
    </table>

    <h2 style="margin-top:8px">TRAININGS</h2>
    <table class="form" style="margin-bottom:8px;">
        <tr><th>TITLE</th><th>INCLUSIVE DATES</th><th>HOURS</th><th>CONDUCTED BY</th></tr>
        @foreach($pds->trainings ?? [] as $t)
            <tr>
                <td>{{ $t->training_title ?? '' }}</td>
                <td>{{ $t->date_from ?? '' }} - {{ $t->date_to ?? '' }}</td>
                <td>{{ $t->hours ?? '' }}</td>
                <td>{{ $t->conducted_by ?? '' }}</td>
            </tr>
        @endforeach
        @for($i = count($pds->trainings ?? []); $i < 4; $i++)
            <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
        @endfor
    </table>

    <h2 style="margin-top:8px">OTHER INFORMATION / REFERENCES</h2>
    <table class="form" style="margin-bottom:8px;">
        <tr><th>GOV'T ID</th><th>ID NO.</th><th>DATE & PLACE OF ISSUANCE</th><th>PHOTO</th></tr>
        <tr>
            <td>{{ $pds->otherInfo->government_id ?? '' }}</td>
            <td>{{ $pds->otherInfo->id_no ?? '' }}</td>
            <td>{{ $pds->otherInfo->date_place_issuance ?? '' }}</td>
            <td>@if(!empty($pds->otherInfo->path_passport_photo))<img src="/storage/{{ $pds->otherInfo->path_passport_photo }}" class="photo" />@endif</td>
        </tr>
    </table>

    <h2 style="margin-top:8px">REFERENCES</h2>
    <table class="form">
        <tr><th>NAME</th><th>OFFICE ADDRESS</th><th>CONTACT</th></tr>
        @foreach($pds->references ?? [] as $r)
            <tr>
                <td>{{ $r->name ?? '' }}</td>
                <td>{{ $r->office_address ?? '' }}</td>
                <td>{{ $r->contact_no_email ?? '' }}</td>
            </tr>
        @endforeach
        @for($i = count($pds->references ?? []); $i < 3; $i++)
            <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
        @endfor
    </table>

    <div style="margin-top:14px;">
        <div class="label">I HEREBY CERTIFY that the foregoing information given by me in this Personal Data Sheet is true and correct to the best of my knowledge and belief.</div>
        <div style="margin-top:18px;"><span class="label">Signature: </span> ____________________________ <span style="margin-left:20px" class="label">Date: </span> __________________</div>
    </div>

    <div class="meta">Page 4 of 4</div>
</div>

</body>
</html>
