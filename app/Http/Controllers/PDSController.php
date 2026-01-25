<?php

namespace App\Http\Controllers;

use App\Models\Pds;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\IOFactory;

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
        $sheet = $spreadsheet->getActiveSheet();

        /* ---------- Personal Info ---------- */
        $sheet->setCellValue('D10', $pds->personalInfo->surname ?? '');
        $sheet->setCellValue('D11', $pds->personalInfo->first_name ?? '');
        $sheet->setCellValue('D12', $pds->personalInfo->middle_name ?? '');

        /* ---------- Family ---------- */
        $sheet->setCellValue('B15', $pds->familyBackground->father_surname ?? '');
        $sheet->setCellValue('C15', $pds->familyBackground->father_first_name ?? '');
        $sheet->setCellValue('B16', $pds->familyBackground->mother_surname ?? '');
        $sheet->setCellValue('C16', $pds->familyBackground->mother_first_name ?? '');

        /* ---------- Education ---------- */
        $row = 25;
        foreach ($pds->education ?? [] as $edu) {
            $sheet->setCellValue("B{$row}", $edu->level);
            $sheet->setCellValue("C{$row}", $edu->school_name);
            $sheet->setCellValue("D{$row}", $edu->degree_course);
            $sheet->setCellValue("E{$row}", $edu->year_graduated);
            $sheet->setCellValue("F{$row}", $edu->highest_level);
            $sheet->setCellValue("G{$row}", $edu->scholarship);
            $row++;
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'pds_');
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($tempFile);

        return response()
            ->download($tempFile, "PDS_{$pds->id}.xlsx")
            ->deleteFileAfterSend(true);
    }
}
