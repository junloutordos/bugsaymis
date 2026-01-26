<?php

namespace App\Http\Controllers;

use App\Models\Pds;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf as PdfWriter;
use Carbon\Carbon;

use DateTime;
class PDSController extends Controller
{
    /* =====================================================
     | USER: Redirect to own PDS
     ===================================================== */
    public function myPds()
    {
        $pds = Pds::where('user_id', auth()->id())->first();

        return $pds
            ? redirect()->route('pds.edit', $pds)
            : redirect()->route('pds.create');
    }

    /* =====================================================
     | ADMIN: List all PDS
     ===================================================== */
    public function index()
    {
        abort_unless(auth()->user()->role_id === 1, 403);

        return Inertia::render('PDS/Index', [
            'pdsList' => Pds::with('user:id,name,email')
                ->latest()
                ->paginate(10),
        ]);
    }

    /* =====================================================
     | CREATE
     ===================================================== */
    public function create()
    {
        $existing = Pds::where('user_id', auth()->id())->first();

        return $existing
            ? redirect()->route('pds.edit', $existing)
            : Inertia::render('PDS/Form');
    }

    /* =====================================================
     | STORE
     ===================================================== */
    public function store(Request $request)
    {
        try {
            DB::transaction(function () use ($request) {
                $pds = Pds::create([
                    'user_id' => auth()->id(),
                ]);

                $this->saveRelations($pds, $request);
            });

            return redirect()
                ->route('pds.my')
                ->with('success', 'Personal Data Sheet saved successfully!');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Failed to save PDS. Please try again.');
        }
    }

    /* =====================================================
     | EDIT
     ===================================================== */
    public function edit(Pds $pds)
    {
        $this->authorizeAccess($pds);

        return Inertia::render('PDS/Form', [
            'pds' => $this->loadFullPds($pds),
        ]);
    }

    /* =====================================================
     | UPDATE
     ===================================================== */
    public function update(Request $request, Pds $pds)
    {
        $this->authorizeAccess($pds);

        try {
            DB::transaction(function () use ($request, $pds) {
                $this->saveRelations($pds, $request, true);
            });

            return back()->with('success', 'Personal Data Sheet updated successfully!');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Failed to update PDS. Please try again.');
        }
    }

    /* =====================================================
     | SHOW (API-style)
     ===================================================== */
    public function show(Pds $pds)
    {
        $this->authorizeAccess($pds);
        return $this->loadFullPds($pds);
    }

    /* =====================================================
     | CENTRALIZED SAVE LOGIC
     ===================================================== */
    private function saveRelations(Pds $pds, Request $request, bool $updating = false): void
{
    logger()->info('PDS PERSONAL INFO PAYLOAD', $request->input('personal_info'));

    /* ---------- PERSONAL INFO (handle citizenship) ---------- */
    /* ---------- PERSONAL INFO (handle citizenship) ---------- */
    $personalInfo = $request->input('personal_info', []);

    $isFilipino = filter_var($personalInfo['citizenship_filipino'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if ($isFilipino) {
        $personalInfo['citizenship'] = 'Filipino';
        $personalInfo['citizenship_filipino'] = 'Yes'; // store as string
        $personalInfo['citizenship_dual'] = 'No';      // store as string
        $personalInfo['citizenship_dual_type'] = null;
        $personalInfo['citizenship_dual_country'] = null;
    } else {
        $personalInfo['citizenship'] = 'Dual/Other';
        $personalInfo['citizenship_filipino'] = 'No'; // store as string
        $personalInfo['citizenship_dual'] = 'Yes';    // store as string
        $personalInfo['citizenship_dual_type'] = $personalInfo['citizenship_dual_type'] ?? null;
        $personalInfo['citizenship_dual_country'] = $personalInfo['citizenship_dual_country'] ?? null;
    }

    $pds->personalInfo()->updateOrCreate(
        ['pds_id' => $pds->id],
        $personalInfo
    );


    /* ---------- ONE TO ONE RELATIONS ---------- */
    $this->syncOne($pds, 'familyBackground', $request->input('family_background', []));
    $this->syncOne($pds, 'questions', $request->input('questions', []));
    $this->syncOne($pds, 'otherInfo', $request->input('other_info', []));

    /* ---------- ONE TO MANY RELATIONS ---------- */
    $this->syncMany($pds, 'children', $request->input('children', []), $updating);
    $this->syncMany($pds, 'education', $request->input('education', []), $updating);
    $this->syncMany($pds, 'eligibility', $request->input('eligibility', []), $updating);
    $this->syncMany($pds, 'workExperience', $request->input('work_experience', []), $updating);
    $this->syncMany($pds, 'voluntaryWork', $request->input('voluntary_work', []), $updating);
    $this->syncMany($pds, 'trainings', $request->input('trainings', []), $updating);
    $this->syncMany($pds, 'skillsHobbies', $request->input('skills_hobbies', []), $updating);
    $this->syncMany($pds, 'nonAcademicRecognition', $request->input('non_academic_recognition', []), $updating);
    $this->syncMany($pds, 'membershipOrganizations', $request->input('membership_organizations', []), $updating);
    $this->syncMany($pds, 'references', $request->input('references', []), $updating);
}

    /* =====================================================
     | RELATION HELPERS
     ===================================================== */
    private function syncOne(Pds $pds, string $relation, array $data): void
    {
        $pds->{$relation}()->updateOrCreate(
            ['pds_id' => $pds->id],
            $data
        );
    }

    private function syncMany(Pds $pds, string $relation, array $data, bool $updating): void
    {
        if ($updating) {
            $pds->{$relation}()->delete();
        }

        if (!empty($data)) {
            $pds->{$relation}()->createMany($data);
        }
    }

    /* =====================================================
     | ACCESS CONTROL
     ===================================================== */
    private function authorizeAccess(Pds $pds): void
    {
        abort_if(
            auth()->id() !== $pds->user_id && auth()->user()->role_id !== 1,
            403
        );
    }

    /* =====================================================
     | LOAD FULL PDS
     ===================================================== */
    private function loadFullPds(Pds $pds): Pds
    {
        return $pds->load([
            'personalInfo',
            'familyBackground',
            'children',
            'education',
            'eligibility',
            'workExperience',
            'voluntaryWork',
            'trainings',
            'skillsHobbies',
            'nonAcademicRecognition',
            'membershipOrganizations',
            'questions',
            'references',
            'otherInfo',
        ]);
    }

    /* =====================================================
     | EXPORT TO EXCEL
     ===================================================== */
public function exportPDS(Pds $pds)
{
    $this->authorizeAccess($pds);
    $pds = $this->loadFullPds($pds);

    $templatePath = storage_path('app/public/templates/pds_template2025.xlsx');
    $spreadsheet = IOFactory::load($templatePath);

    /* =====================================================
     | C1 – EXISTING WORKING CODE (UNCHANGED)
     ===================================================== */
    $sheet = $spreadsheet->getSheetByName('C1') ?? $spreadsheet->getActiveSheet();

    /* ---------- Personal Info ---------- */
    $sheet->setCellValue('D10', $pds->personalInfo->surname ?? '');
    $sheet->setCellValue('D11', $pds->personalInfo->first_name ?? '');
    $sheet->setCellValue('D12', $pds->personalInfo->middle_name ?? '');
    $sheet->setCellValue('N11', $pds->personalInfo->name_ext ?? '');

    $dateOfBirth = $pds->personalInfo->date_of_birth ?? null;
    $formattedDob = $dateOfBirth ? (new \DateTime($dateOfBirth))->format('m-d-Y') : '';
    $sheet->setCellValue('D13', $formattedDob);

    $sheet->setCellValue('D15', $pds->personalInfo->place_of_birth ?? '');
    /*
    |--------------------------------------------------------------------------
    | Sex at Birth (C4-style checkmarks)
    |--------------------------------------------------------------------------
    */

    $sex = $pds->personalInfo->sex_at_birth ?? '';

    // D16 = Male, D17 = Female
    $sheet->setCellValue('D16', $sex === 'Male' ? '☑ Male' : '☐ Male');
    $sheet->setCellValue('E16', $sex === 'Female' ? '☑ Female' : '☐ Female');

    /*
    |--------------------------------------------------------------------------
    | Civil Status (C4-style checkmarks)
    |--------------------------------------------------------------------------
    */

    $civilStatus = $pds->personalInfo->civil_status ?? '';

    // D17 = Single, E17 = Married
    $sheet->setCellValue('D17', $civilStatus === 'Single' ? '☑ Single' : '☐ Single');
    $sheet->setCellValue('E17', $civilStatus === 'Married' ? '☑ Married' : '☐ Married');

    // D18 = Widowed, E18 = Separated
    $sheet->setCellValue('D18', $civilStatus === 'Widowed' ? '☑ Widowed' : '☐ Widowed');
    $sheet->setCellValue('E18', $civilStatus === 'Separated' ? '☑ Separated' : '☐ Separated');

    // D19 = Others
    $sheet->setCellValue('D20', $civilStatus === 'Others' ? '☑ Others' : '☐ Others');

    $sheet->setCellValue('D22', $pds->personalInfo->height ?? '');
    $sheet->setCellValue('D24', $pds->personalInfo->weight ?? '');
    $sheet->setCellValue('D25', $pds->personalInfo->blood_type ?? '');
    $sheet->setCellValue('D27', $pds->personalInfo->umid_id_no ?? '');
    $sheet->setCellValue('D29', $pds->personalInfo->pagibig_id_no ?? '');
    $sheet->setCellValue('D31', $pds->personalInfo->philhealth_no ?? '');
    $sheet->setCellValue('D32', $pds->personalInfo->philsys_no ?? '');
    $sheet->setCellValue('D33', $pds->personalInfo->tin_no ?? '');
    $sheet->setCellValue('D34', $pds->personalInfo->agency_employee_no ?? '');

    /*
|--------------------------------------------------------------------------
| Citizenship (C4-style checkmarks)
|--------------------------------------------------------------------------
*/

$citizenship = $pds->personalInfo;

// J13 = Filipino, check if citizenship_filipino is "yes"
$sheet->setCellValue(
    'J13',
    strtolower($citizenship->citizenship_filipino ?? 'no') === 'yes' ? '☑ Filipino' : '☐ Filipino'
);

// L13 = Dual, check if citizenship_dual is "yes"
$sheet->setCellValue(
    'L13',
    strtolower($citizenship->citizenship_dual ?? 'no') === 'yes' ? '☑ Dual Citizenship' : '☐ Dual Citizenship'
);

// K14 = By Birth, M14 = By Naturalization (only relevant if Dual citizenship is yes)
$dualType = $citizenship->citizenship_dual_type ?? '';
$sheet->setCellValue('K14', strtolower($dualType) === 'by birth' ? '☑ By Birth' : '☐ By Birth');
$sheet->setCellValue('M14', strtolower($dualType) === 'by naturalization' ? '☑ By Naturalization' : '☐ By Naturalization');

    $sheet->setCellValue('J16', $pds->personalInfo->citizenship_dual_country ?? '');

    $sheet->setCellValue('I17', $pds->personalInfo->residential_house ?? '');
    $sheet->setCellValue('L17', $pds->personalInfo->residential_street ?? '');
    $sheet->setCellValue('I19', $pds->personalInfo->residential_subdivision ?? '');
    $sheet->setCellValue('L19', $pds->personalInfo->residential_barangay ?? '');
    $sheet->setCellValue('I22', $pds->personalInfo->residential_city ?? '');
    $sheet->setCellValue('L22', $pds->personalInfo->residential_province ?? '');
    $sheet->setCellValue('I24', $pds->personalInfo->residential_zip_code ?? '');

    $sheet->setCellValue('I25', $pds->personalInfo->permanent_house ?? '');
    $sheet->setCellValue('L25', $pds->personalInfo->permanent_street ?? '');
    $sheet->setCellValue('I27', $pds->personalInfo->permanent_subdivision ?? '');
    $sheet->setCellValue('L27', $pds->personalInfo->permanent_barangay ?? '');
    $sheet->setCellValue('I29', $pds->personalInfo->permanent_city ?? '');
    $sheet->setCellValue('L29', $pds->personalInfo->permanent_province ?? '');
    $sheet->setCellValue('I31', $pds->personalInfo->permanent_zip_code ?? '');

    $sheet->setCellValue('I32', $pds->personalInfo->telephone_no ?? '');
    $sheet->setCellValue('I33', $pds->personalInfo->mobile_no ?? '');
    $sheet->setCellValue('I34', $pds->personalInfo->email_address ?? '');

    /* ---------- Family ---------- */
    $sheet->setCellValue('D36', $pds->familyBackground->spouse_surname ?? '');
    $sheet->setCellValue('D37', $pds->familyBackground->spouse_first_name ?? '');
    $sheet->setCellValue('D38', $pds->familyBackground->spouse_middle_name ?? '');
    $sheet->setCellValue('H37', $pds->familyBackground->spouse_name_ext ?? '');
    $sheet->setCellValue('D39', $pds->familyBackground->spouse_occupation ?? '');
    $sheet->setCellValue('D40', $pds->familyBackground->spouse_employer ?? '');
    $sheet->setCellValue('D41', $pds->familyBackground->spouse_business_address ?? '');
    $sheet->setCellValue('D42', $pds->familyBackground->spouse_telephone_no ?? '');

    $sheet->setCellValue('D43', $pds->familyBackground->father_surname ?? '');
    $sheet->setCellValue('D44', $pds->familyBackground->father_first_name ?? '');
    $sheet->setCellValue('D45', $pds->familyBackground->father_middle_name ?? '');
    $sheet->setCellValue('H44', $pds->familyBackground->father_name_ext ?? '');
    $sheet->setCellValue('D47', $pds->familyBackground->mother_maiden_surname ?? '');
    $sheet->setCellValue('D48', $pds->familyBackground->mother_maiden_first_name ?? '');
    $sheet->setCellValue('D49', $pds->familyBackground->mother_maiden_middle_name ?? '');

    /* ---------- Children ---------- */
    $childRow = 37;
    foreach ($pds->children ?? [] as $child) {
        $sheet->setCellValue("I{$childRow}", $child->child_name ?? '');
        $sheet->setCellValue(
            "M{$childRow}",
            $child->child_date_of_birth
                ? (new \DateTime($child->child_date_of_birth))->format('m-d-Y')
                : ''
        );
        $childRow++;
    }

    /* ---------- Education ---------- */
    $eduRow = 54;
    foreach ($pds->education ?? [] as $edu) {
        $sheet->setCellValue("A{$eduRow}", $edu->level ?? '');
        $sheet->setCellValue("D{$eduRow}", $edu->school_name ?? '');
        $sheet->setCellValue("G{$eduRow}", $edu->degree ?? '');
        $sheet->setCellValue("J{$eduRow}", $edu->from ?? '');
        $sheet->setCellValue("K{$eduRow}", $edu->to ?? '');
        $sheet->setCellValue("L{$eduRow}", $edu->highest_level ?? '');
        $sheet->setCellValue("M{$eduRow}", $edu->year_graduated ?? '');
        $sheet->setCellValue("N{$eduRow}", $edu->honors ?? '');
        $eduRow++;
    }

    /* =====================================================
     | C2 – ELIGIBILITY & WORK EXPERIENCE
     ===================================================== */
    $sheet = $spreadsheet->getSheetByName('C2');

    $row = 5;
    foreach ($pds->eligibility ?? [] as $e) {
        $sheet->setCellValue("A{$row}", $e->eligibility ?? '');
        $sheet->setCellValue("F{$row}", $e->rating ?? '');

        // Format exam_date as mm-dd-yyyy if not empty
        $sheet->setCellValue(
            "G{$row}",
            !empty($e->exam_date) ? (new DateTime($e->exam_date))->format('m-d-Y') : ''
        );

        $sheet->setCellValue("I{$row}", $e->place_taken ?? '');
        $sheet->setCellValue("J{$row}", $e->license_number ?? '');

        // Format license_validity as mm-dd-yyyy if not empty
        $sheet->setCellValue(
            "N{$row}",
            !empty($e->license_validity) ? (new DateTime($e->license_validity))->format('m-d-Y') : ''
        );

        $row++;
    }


    $row = 18;
    foreach ($pds->workExperience ?? [] as $w) {
    // Format from_date as mm-dd-yyyy
        $sheet->setCellValue(
            "A{$row}",
            !empty($w->from_date) ? (new DateTime($w->from_date))->format('m-d-Y') : ''
        );

        // Format to_date as mm-dd-yyyy
        $sheet->setCellValue(
            "C{$row}",
            !empty($w->to_date) ? (new DateTime($w->to_date))->format('m-d-Y') : ''
        );

        $sheet->setCellValue("D{$row}", $w->position ?? '');
        $sheet->setCellValue("G{$row}", $w->agency ?? '');
        $sheet->setCellValue("J{$row}", $w->appointment_status ?? '');
        $sheet->setCellValue("K{$row}", $w->government_service ?? '');

        $row++;
    }


    /* =====================================================
     | C3 – SKILLS / NON-ACADEMIC / MEMBERSHIP
     ===================================================== */
    $sheet = $spreadsheet->getSheetByName('C3');

    /* ---------- Voluntary Work ---------- */
    $row = 6;
    foreach ($pds->voluntaryWork ?? [] as $v) {
        $sheet->setCellValue("A{$row}", $v->organization ?? '');

        // Format from_date as mm-dd-yyyy
        $sheet->setCellValue(
            "D{$row}",
            !empty($v->from_date) ? (new DateTime($v->from_date))->format('m-d-Y') : ''
        );

        // Format to_date as mm-dd-yyyy
        $sheet->setCellValue(
            "G{$row}",
            !empty($v->to_date) ? (new DateTime($v->to_date))->format('m-d-Y') : ''
        );

        $sheet->setCellValue("J{$row}", $v->hours ?? '');
        $sheet->setCellValue("H{$row}", $v->nature_of_work ?? '');

        $row++;
    }


    /* ---------- Trainings / Seminars ---------- */
    $row = 18;
    foreach ($pds->trainings ?? [] as $t) {
        $sheet->setCellValue("A{$row}", $t->training_title ?? '');

        // Format date_from as mm-dd-yyyy
        $sheet->setCellValue(
            "E{$row}",
            !empty($t->date_from) ? (new DateTime($t->date_from))->format('m-d-Y') : ''
        );

        // Format date_to as mm-dd-yyyy
        $sheet->setCellValue(
            "F{$row}",
            !empty($t->date_to) ? (new DateTime($t->date_to))->format('m-d-Y') : ''
        );

        $sheet->setCellValue("G{$row}", $t->hours ?? '');
        $sheet->setCellValue("H{$row}", $t->training_type ?? '');
        $sheet->setCellValue("I{$row}", $t->conducted_by ?? '');

        $row++;
    }


    /* ---------- Skills / Hobbies ---------- */
    $row = 42;
    foreach ($pds->skillsHobbies ?? [] as $s) {
        $sheet->setCellValue("A{$row}", $s->skills_hobbies ?? '');
        $row++;
    }

    /* ---------- Non-Academic Recognition ---------- */
    $row = 42;
    foreach ($pds->nonAcademicRecognition ?? [] as $n) {
        $sheet->setCellValue("C{$row}", $n->recognition ?? '');
        $row++;
    }

    /* ---------- Membership in Organizations ---------- */
    $row = 42;
    foreach ($pds->membershipOrganizations ?? [] as $m) {
        $sheet->setCellValue("I{$row}", $m->organization_name ?? '');
        $row++;
    }


    /* =====================================================
     | C4 – YES / NO CHECKMARKS
     ===================================================== */
    /* =====================================================
 | C4 – YES / NO CHECKMARKS (1 = YES)
 ===================================================== */
$sheet = $spreadsheet->getSheetByName('C4');

function markYesNo(Worksheet $sheet, string $yesCell, string $noCell, $value): void
{
    $isYes = in_array($value, [1, '1', 'yes', 'Yes', true], true);

    $sheet->setCellValue($yesCell, $isYes ? '☑ Yes' : '☐ Yes');
    $sheet->setCellValue($noCell,  $isYes ? '☐ No'  : '☑ No');
}


/*
|--------------------------------------------------------------------------
| C4 Cell Mapping (CSC PDS – CORRECTED)
|--------------------------------------------------------------------------
*/

$questions = [
    // 34
    [
        'field'        => 'q34a_third_degree',
        'yesCell'      => 'H6',   // ✅ FIXED
        'noCell'       => 'K6',   // ✅ FIXED
        'detailsCell'  => 'N7',
        'detailsField' => 'q34a_details',
    ],
    [
        'field'        => 'q34b_fourth_degree',
        'yesCell'      => 'H8',   // ✅ FIXED
        'noCell'       => 'K8',   // ✅ FIXED
        'detailsCell'  => 'H11',
        'detailsField' => 'q34b_details',
    ],

    // 35
    [
        'field'        => 'q35a_admin_offense',
        'yesCell'      => 'H13',
        'noCell'       => 'K13',
        'detailsCell'  => 'H15',
        'detailsField' => 'q35a_details',
    ],
    [
        'field'        => 'q35b_criminal_charge',
        'yesCell'      => 'H18',
        'noCell'       => 'K18',
        
        'dateCell'     => 'K20',
        'dateField'    => 'q35b_date_filed',
        'statusCell'   => 'K21',
        'statusField'  => 'q35b_status',
    ],

    // 36
    [
        'field'        => 'q36_convicted',
        'yesCell'      => 'H23',
        'noCell'       => 'K23',
        'detailsCell'  => 'H25',
        'detailsField' => 'q36_details',
    ],

    // 37
    [
        'field'        => 'q37_separated_service',
        'yesCell'      => 'H27',
        'noCell'       => 'K27',
        'detailsCell'  => 'H29',
        'detailsField' => 'q37_details',
    ],

    // 38
    [
        'field'        => 'q38a_candidate',
        'yesCell'      => 'H31',
        'noCell'       => 'K31',
        'detailsCell'  => 'K32',
        'detailsField' => 'q38a_details',
    ],
    [
        'field'        => 'q38b_resigned_for_campaign',
        'yesCell'      => 'H34',
        'noCell'       => 'K34',
        'detailsCell'  => 'K35',
        'detailsField' => 'q38b_details',
    ],

    // 39
    [
        'field'        => 'q39_immigrant',
        'yesCell'      => 'H37',
        'noCell'       => 'K37',
        'detailsCell'  => 'H39',
        'detailsField' => 'q39_country',
    ],

    // 40
    [
        'field'        => 'q40a_indigenous',
        'yesCell'      => 'H43',
        'noCell'       => 'K43',
        'detailsCell'  => 'L44',
        'detailsField' => 'q40a_group',
    ],
    [
        'field'        => 'q40b_pwd',
        'yesCell'      => 'H45',
        'noCell'       => 'K45',
        'detailsCell'  => 'L46',
        'detailsField' => 'q40b_pwd_id',
    ],
    [
        'field'        => 'q40c_solo_parent',
        'yesCell'      => 'H47',
        'noCell'       => 'K47',
        'detailsCell'  => 'L48',
        'detailsField' => 'q40c_solo_parent_id',
    ],
];

/*
|--------------------------------------------------------------------------
| Populate C4
|--------------------------------------------------------------------------
*/

$c4 = $pds->questions;

foreach ($questions as $q) {
    $value = (int) ($c4?->{$q['field']} ?? 0);

    markYesNo($sheet, $q['yesCell'], $q['noCell'], $value);

    if ($value === 1 && isset($q['detailsCell'], $q['detailsField'])) {
        $sheet->setCellValue(
            $q['detailsCell'],
            $c4?->{$q['detailsField']} ?? ''
        );
    }

    if ($value === 1 && isset($q['dateCell'], $q['dateField']) && !empty($c4?->{$q['dateField']})) {
        $sheet->setCellValue(
            $q['dateCell'],
            (new DateTime($c4->{$q['dateField']}))->format('m-d-Y')
        );
    }

    if ($value === 1 && isset($q['statusCell'], $q['statusField'])) {
        $sheet->setCellValue(
            $q['statusCell'],
            $c4?->{$q['statusField']} ?? ''
        );
    }
}

    $row = 52;
    foreach ($pds->references ?? [] as $r) {
        $sheet->setCellValue("A{$row}", $r->name ?? '');
        $sheet->setCellValue("F{$row}", $r->office_address ?? '');
        $sheet->setCellValue("G{$row}", $r->contact_no_email?? '');
        $row++;
    }

    $sheet->setCellValue('D61', $pds->otherInfo->government_id ?? '');
    $sheet->setCellValue('D62', $pds->otherInfo->id_no ?? '');
    $sheet->setCellValue('D64', $pds->otherInfo->date_place_issuance ?? '');

    /* =====================================================
     | DOWNLOAD
     ===================================================== */
    $tempFile = tempnam(sys_get_temp_dir(), 'pds_');
    IOFactory::createWriter($spreadsheet, 'Xlsx')->save($tempFile);

    return response()
        ->download($tempFile, "PDS_CSCForm212_{$pds->id}.xlsx")
        ->deleteFileAfterSend(true);
}

private function formatDate($value): string
{
    if (empty($value)) {
        return '';
    }

    try {
        return Carbon::parse($value)->format('m-d-Y');
    } catch (\Exception $e) {
        return '';
    }
}

private function setCheck(Worksheet $sheet, string $cell, bool $checked, string $label): void
{
    $sheet->setCellValue($cell, $checked ? "☑ {$label}" : "☐ {$label}");
}

private function markYesNo(Worksheet $sheet, string $yesCell, string $noCell, $value): void
{
    $isYes = in_array($value, [1, '1', 'yes', 'Yes', true], true);
    $sheet->setCellValue($yesCell, $isYes ? '☑ Yes' : '☐ Yes');
    $sheet->setCellValue($noCell,  $isYes ? '☐ No'  : '☑ No');
}


public function exportPDSPdf(Pds $pds)
{
    $this->authorizeAccess($pds);
    $pds = $this->loadFullPds($pds);

    $templatePath = storage_path('app/public/templates/pds_template2025.xlsx');
    $spreadsheet = IOFactory::load($templatePath);

    /* =====================================================
     | C1 – EXISTING WORKING CODE (UNCHANGED)
     ===================================================== */
    $sheet = $spreadsheet->getSheetByName('C1') ?? $spreadsheet->getActiveSheet();

    /* ---------- Personal Info ---------- */
    $sheet->setCellValue('D10', $pds->personalInfo->surname ?? '');
    $sheet->setCellValue('D11', $pds->personalInfo->first_name ?? '');
    $sheet->setCellValue('D12', $pds->personalInfo->middle_name ?? '');
    $sheet->setCellValue('N11', $pds->personalInfo->name_ext ?? '');

    $dateOfBirth = $pds->personalInfo->date_of_birth ?? null;
    $formattedDob = $dateOfBirth ? (new \DateTime($dateOfBirth))->format('m-d-Y') : '';
    $sheet->setCellValue('D13', $formattedDob);

    $sheet->setCellValue('D15', $pds->personalInfo->place_of_birth ?? '');
    /*
    |--------------------------------------------------------------------------
    | Sex at Birth (C4-style checkmarks)
    |--------------------------------------------------------------------------
    */

    $sex = $pds->personalInfo->sex_at_birth ?? '';

    // D16 = Male, D17 = Female
    $sheet->setCellValue('D16', $sex === 'Male' ? '☑ Male' : '☐ Male');
    $sheet->setCellValue('E16', $sex === 'Female' ? '☑ Female' : '☐ Female');

    /*
    |--------------------------------------------------------------------------
    | Civil Status (C4-style checkmarks)
    |--------------------------------------------------------------------------
    */

    $civilStatus = $pds->personalInfo->civil_status ?? '';

    // D17 = Single, E17 = Married
    $sheet->setCellValue('D17', $civilStatus === 'Single' ? '☑ Single' : '☐ Single');
    $sheet->setCellValue('E17', $civilStatus === 'Married' ? '☑ Married' : '☐ Married');

    // D18 = Widowed, E18 = Separated
    $sheet->setCellValue('D18', $civilStatus === 'Widowed' ? '☑ Widowed' : '☐ Widowed');
    $sheet->setCellValue('E18', $civilStatus === 'Separated' ? '☑ Separated' : '☐ Separated');

    // D19 = Others
    $sheet->setCellValue('D20', $civilStatus === 'Others' ? '☑ Others' : '☐ Others');

    $sheet->setCellValue('D22', $pds->personalInfo->height ?? '');
    $sheet->setCellValue('D24', $pds->personalInfo->weight ?? '');
    $sheet->setCellValue('D25', $pds->personalInfo->blood_type ?? '');
    $sheet->setCellValue('D27', $pds->personalInfo->umid_id_no ?? '');
    $sheet->setCellValue('D29', $pds->personalInfo->pagibig_id_no ?? '');
    $sheet->setCellValue('D31', $pds->personalInfo->philhealth_no ?? '');
    $sheet->setCellValue('D32', $pds->personalInfo->philsys_no ?? '');
    $sheet->setCellValue('D33', $pds->personalInfo->tin_no ?? '');
    $sheet->setCellValue('D34', $pds->personalInfo->agency_employee_no ?? '');

    /*
|--------------------------------------------------------------------------
| Citizenship (C4-style checkmarks)
|--------------------------------------------------------------------------
*/

$citizenship = $pds->personalInfo;

// J13 = Filipino, check if citizenship_filipino is "yes"
$sheet->setCellValue(
    'J13',
    strtolower($citizenship->citizenship_filipino ?? 'no') === 'yes' ? '☑ Filipino' : '☐ Filipino'
);

// L13 = Dual, check if citizenship_dual is "yes"
$sheet->setCellValue(
    'L13',
    strtolower($citizenship->citizenship_dual ?? 'no') === 'yes' ? '☑ Dual Citizenship' : '☐ Dual Citizenship'
);

// K14 = By Birth, M14 = By Naturalization (only relevant if Dual citizenship is yes)
$dualType = $citizenship->citizenship_dual_type ?? '';
$sheet->setCellValue('K14', strtolower($dualType) === 'by birth' ? '☑ By Birth' : '☐ By Birth');
$sheet->setCellValue('M14', strtolower($dualType) === 'by naturalization' ? '☑ By Naturalization' : '☐ By Naturalization');

    $sheet->setCellValue('J16', $pds->personalInfo->citizenship_dual_country ?? '');

    $sheet->setCellValue('I17', $pds->personalInfo->residential_house ?? '');
    $sheet->setCellValue('L17', $pds->personalInfo->residential_street ?? '');
    $sheet->setCellValue('I19', $pds->personalInfo->residential_subdivision ?? '');
    $sheet->setCellValue('L19', $pds->personalInfo->residential_barangay ?? '');
    $sheet->setCellValue('I22', $pds->personalInfo->residential_city ?? '');
    $sheet->setCellValue('L22', $pds->personalInfo->residential_province ?? '');
    $sheet->setCellValue('I24', $pds->personalInfo->residential_zip_code ?? '');

    $sheet->setCellValue('I25', $pds->personalInfo->permanent_house ?? '');
    $sheet->setCellValue('L25', $pds->personalInfo->permanent_street ?? '');
    $sheet->setCellValue('I27', $pds->personalInfo->permanent_subdivision ?? '');
    $sheet->setCellValue('L27', $pds->personalInfo->permanent_barangay ?? '');
    $sheet->setCellValue('I29', $pds->personalInfo->permanent_city ?? '');
    $sheet->setCellValue('L29', $pds->personalInfo->permanent_province ?? '');
    $sheet->setCellValue('I31', $pds->personalInfo->permanent_zip_code ?? '');

    $sheet->setCellValue('I32', $pds->personalInfo->telephone_no ?? '');
    $sheet->setCellValue('I33', $pds->personalInfo->mobile_no ?? '');
    $sheet->setCellValue('I34', $pds->personalInfo->email_address ?? '');

    /* ---------- Family ---------- */
    $sheet->setCellValue('D36', $pds->familyBackground->spouse_surname ?? '');
    $sheet->setCellValue('D37', $pds->familyBackground->spouse_first_name ?? '');
    $sheet->setCellValue('D38', $pds->familyBackground->spouse_middle_name ?? '');
    $sheet->setCellValue('H37', $pds->familyBackground->spouse_name_ext ?? '');
    $sheet->setCellValue('D39', $pds->familyBackground->spouse_occupation ?? '');
    $sheet->setCellValue('D40', $pds->familyBackground->spouse_employer ?? '');
    $sheet->setCellValue('D41', $pds->familyBackground->spouse_business_address ?? '');
    $sheet->setCellValue('D42', $pds->familyBackground->spouse_telephone_no ?? '');

    $sheet->setCellValue('D43', $pds->familyBackground->father_surname ?? '');
    $sheet->setCellValue('D44', $pds->familyBackground->father_first_name ?? '');
    $sheet->setCellValue('D45', $pds->familyBackground->father_middle_name ?? '');
    $sheet->setCellValue('H44', $pds->familyBackground->father_name_ext ?? '');
    $sheet->setCellValue('D47', $pds->familyBackground->mother_maiden_surname ?? '');
    $sheet->setCellValue('D48', $pds->familyBackground->mother_maiden_first_name ?? '');
    $sheet->setCellValue('D49', $pds->familyBackground->mother_maiden_middle_name ?? '');

    /* ---------- Children ---------- */
    $childRow = 37;
    foreach ($pds->children ?? [] as $child) {
        $sheet->setCellValue("I{$childRow}", $child->child_name ?? '');
        $sheet->setCellValue(
            "M{$childRow}",
            $child->child_date_of_birth
                ? (new \DateTime($child->child_date_of_birth))->format('m-d-Y')
                : ''
        );
        $childRow++;
    }

    /* ---------- Education ---------- */
    $eduRow = 54;
    foreach ($pds->education ?? [] as $edu) {
        $sheet->setCellValue("A{$eduRow}", $edu->level ?? '');
        $sheet->setCellValue("D{$eduRow}", $edu->school_name ?? '');
        $sheet->setCellValue("G{$eduRow}", $edu->degree ?? '');
        $sheet->setCellValue("J{$eduRow}", $edu->from ?? '');
        $sheet->setCellValue("K{$eduRow}", $edu->to ?? '');
        $sheet->setCellValue("L{$eduRow}", $edu->highest_level ?? '');
        $sheet->setCellValue("M{$eduRow}", $edu->year_graduated ?? '');
        $sheet->setCellValue("N{$eduRow}", $edu->honors ?? '');
        $eduRow++;
    }

    /* =====================================================
     | C2 – ELIGIBILITY & WORK EXPERIENCE
     ===================================================== */
    $sheet = $spreadsheet->getSheetByName('C2');

    $row = 5;
    foreach ($pds->eligibility ?? [] as $e) {
        $sheet->setCellValue("A{$row}", $e->eligibility ?? '');
        $sheet->setCellValue("F{$row}", $e->rating ?? '');

        // Format exam_date as mm-dd-yyyy if not empty
        $sheet->setCellValue(
            "G{$row}",
            !empty($e->exam_date) ? (new DateTime($e->exam_date))->format('m-d-Y') : ''
        );

        $sheet->setCellValue("I{$row}", $e->place_taken ?? '');
        $sheet->setCellValue("J{$row}", $e->license_number ?? '');

        // Format license_validity as mm-dd-yyyy if not empty
        $sheet->setCellValue(
            "N{$row}",
            !empty($e->license_validity) ? (new DateTime($e->license_validity))->format('m-d-Y') : ''
        );

        $row++;
    }


    $row = 18;
    foreach ($pds->workExperience ?? [] as $w) {
    // Format from_date as mm-dd-yyyy
        $sheet->setCellValue(
            "A{$row}",
            !empty($w->from_date) ? (new DateTime($w->from_date))->format('m-d-Y') : ''
        );

        // Format to_date as mm-dd-yyyy
        $sheet->setCellValue(
            "C{$row}",
            !empty($w->to_date) ? (new DateTime($w->to_date))->format('m-d-Y') : ''
        );

        $sheet->setCellValue("D{$row}", $w->position ?? '');
        $sheet->setCellValue("G{$row}", $w->agency ?? '');
        $sheet->setCellValue("J{$row}", $w->appointment_status ?? '');
        $sheet->setCellValue("K{$row}", $w->government_service ?? '');

        $row++;
    }


    /* =====================================================
     | C3 – SKILLS / NON-ACADEMIC / MEMBERSHIP
     ===================================================== */
    $sheet = $spreadsheet->getSheetByName('C3');

    /* ---------- Voluntary Work ---------- */
    $row = 6;
    foreach ($pds->voluntaryWork ?? [] as $v) {
        $sheet->setCellValue("A{$row}", $v->organization ?? '');

        // Format from_date as mm-dd-yyyy
        $sheet->setCellValue(
            "D{$row}",
            !empty($v->from_date) ? (new DateTime($v->from_date))->format('m-d-Y') : ''
        );

        // Format to_date as mm-dd-yyyy
        $sheet->setCellValue(
            "G{$row}",
            !empty($v->to_date) ? (new DateTime($v->to_date))->format('m-d-Y') : ''
        );

        $sheet->setCellValue("J{$row}", $v->hours ?? '');
        $sheet->setCellValue("H{$row}", $v->nature_of_work ?? '');

        $row++;
    }


    /* ---------- Trainings / Seminars ---------- */
    $row = 18;
    foreach ($pds->trainings ?? [] as $t) {
        $sheet->setCellValue("A{$row}", $t->training_title ?? '');

        // Format date_from as mm-dd-yyyy
        $sheet->setCellValue(
            "E{$row}",
            !empty($t->date_from) ? (new DateTime($t->date_from))->format('m-d-Y') : ''
        );

        // Format date_to as mm-dd-yyyy
        $sheet->setCellValue(
            "F{$row}",
            !empty($t->date_to) ? (new DateTime($t->date_to))->format('m-d-Y') : ''
        );

        $sheet->setCellValue("G{$row}", $t->hours ?? '');
        $sheet->setCellValue("H{$row}", $t->training_type ?? '');
        $sheet->setCellValue("I{$row}", $t->conducted_by ?? '');

        $row++;
    }


    /* ---------- Skills / Hobbies ---------- */
    $row = 42;
    foreach ($pds->skillsHobbies ?? [] as $s) {
        $sheet->setCellValue("A{$row}", $s->skills_hobbies ?? '');
        $row++;
    }

    /* ---------- Non-Academic Recognition ---------- */
    $row = 42;
    foreach ($pds->nonAcademicRecognition ?? [] as $n) {
        $sheet->setCellValue("C{$row}", $n->recognition ?? '');
        $row++;
    }

    /* ---------- Membership in Organizations ---------- */
    $row = 42;
    foreach ($pds->membershipOrganizations ?? [] as $m) {
        $sheet->setCellValue("I{$row}", $m->organization_name ?? '');
        $row++;
    }


    /* =====================================================
     | C4 – YES / NO CHECKMARKS
     ===================================================== */
    /* =====================================================
 | C4 – YES / NO CHECKMARKS (1 = YES)
 ===================================================== */
$sheet = $spreadsheet->getSheetByName('C4');

function markYesNo(Worksheet $sheet, string $yesCell, string $noCell, $value): void
{
    $isYes = in_array($value, [1, '1', 'yes', 'Yes', true], true);

    $sheet->setCellValue($yesCell, $isYes ? '☑ Yes' : '☐ Yes');
    $sheet->setCellValue($noCell,  $isYes ? '☐ No'  : '☑ No');
}


/*
|--------------------------------------------------------------------------
| C4 Cell Mapping (CSC PDS – CORRECTED)
|--------------------------------------------------------------------------
*/

$questions = [
    // 34
    [
        'field'        => 'q34a_third_degree',
        'yesCell'      => 'H6',   // ✅ FIXED
        'noCell'       => 'K6',   // ✅ FIXED
        'detailsCell'  => 'N7',
        'detailsField' => 'q34a_details',
    ],
    [
        'field'        => 'q34b_fourth_degree',
        'yesCell'      => 'H8',   // ✅ FIXED
        'noCell'       => 'K8',   // ✅ FIXED
        'detailsCell'  => 'H11',
        'detailsField' => 'q34b_details',
    ],

    // 35
    [
        'field'        => 'q35a_admin_offense',
        'yesCell'      => 'H13',
        'noCell'       => 'K13',
        'detailsCell'  => 'H15',
        'detailsField' => 'q35a_details',
    ],
    [
        'field'        => 'q35b_criminal_charge',
        'yesCell'      => 'H18',
        'noCell'       => 'K18',
        
        'dateCell'     => 'K20',
        'dateField'    => 'q35b_date_filed',
        'statusCell'   => 'K21',
        'statusField'  => 'q35b_status',
    ],

    // 36
    [
        'field'        => 'q36_convicted',
        'yesCell'      => 'H23',
        'noCell'       => 'K23',
        'detailsCell'  => 'H25',
        'detailsField' => 'q36_details',
    ],

    // 37
    [
        'field'        => 'q37_separated_service',
        'yesCell'      => 'H27',
        'noCell'       => 'K27',
        'detailsCell'  => 'H29',
        'detailsField' => 'q37_details',
    ],

    // 38
    [
        'field'        => 'q38a_candidate',
        'yesCell'      => 'H31',
        'noCell'       => 'K31',
        'detailsCell'  => 'K32',
        'detailsField' => 'q38a_details',
    ],
    [
        'field'        => 'q38b_resigned_for_campaign',
        'yesCell'      => 'H34',
        'noCell'       => 'K34',
        'detailsCell'  => 'K35',
        'detailsField' => 'q38b_details',
    ],

    // 39
    [
        'field'        => 'q39_immigrant',
        'yesCell'      => 'H37',
        'noCell'       => 'K37',
        'detailsCell'  => 'H39',
        'detailsField' => 'q39_country',
    ],

    // 40
    [
        'field'        => 'q40a_indigenous',
        'yesCell'      => 'H43',
        'noCell'       => 'K43',
        'detailsCell'  => 'L44',
        'detailsField' => 'q40a_group',
    ],
    [
        'field'        => 'q40b_pwd',
        'yesCell'      => 'H45',
        'noCell'       => 'K45',
        'detailsCell'  => 'L46',
        'detailsField' => 'q40b_pwd_id',
    ],
    [
        'field'        => 'q40c_solo_parent',
        'yesCell'      => 'H47',
        'noCell'       => 'K47',
        'detailsCell'  => 'L48',
        'detailsField' => 'q40c_solo_parent_id',
    ],
];

/*
|--------------------------------------------------------------------------
| Populate C4
|--------------------------------------------------------------------------
*/

$c4 = $pds->questions;

foreach ($questions as $q) {
    $value = (int) ($c4?->{$q['field']} ?? 0);

    markYesNo($sheet, $q['yesCell'], $q['noCell'], $value);

    if ($value === 1 && isset($q['detailsCell'], $q['detailsField'])) {
        $sheet->setCellValue(
            $q['detailsCell'],
            $c4?->{$q['detailsField']} ?? ''
        );
    }

    if ($value === 1 && isset($q['dateCell'], $q['dateField']) && !empty($c4?->{$q['dateField']})) {
        $sheet->setCellValue(
            $q['dateCell'],
            (new DateTime($c4->{$q['dateField']}))->format('m-d-Y')
        );
    }

    if ($value === 1 && isset($q['statusCell'], $q['statusField'])) {
        $sheet->setCellValue(
            $q['statusCell'],
            $c4?->{$q['statusField']} ?? ''
        );
    }
}

    $row = 52;
    foreach ($pds->references ?? [] as $r) {
        $sheet->setCellValue("A{$row}", $r->name ?? '');
        $sheet->setCellValue("F{$row}", $r->office_address ?? '');
        $sheet->setCellValue("G{$row}", $r->contact_no_email?? '');
        $row++;
    }

    $sheet->setCellValue('D61', $pds->otherInfo->government_id ?? '');
    $sheet->setCellValue('D62', $pds->otherInfo->id_no ?? '');
    $sheet->setCellValue('D64', $pds->otherInfo->date_place_issuance ?? '');
    // Export as PDF
    $pdfWriter = new PdfWriter($spreadsheet);

    $tempFile = tempnam(sys_get_temp_dir(), 'pds_pdf_') . '.pdf';
    $pdfWriter->save($tempFile);

    return response()->download($tempFile, "PDS_CSCForm212_{$pds->id}.pdf")
                     ->deleteFileAfterSend(true);
}
}
