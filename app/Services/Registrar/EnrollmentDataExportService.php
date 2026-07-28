<?php

namespace App\Services\Registrar;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\Registrar\StudentEnrollment;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;

class EnrollmentDataExportService
{
    private const TEMPLATE_PATH = 'registrar-templates/enrollment_data_template.xlsx';

    private const ENROLLMENT_TYPE_LABELS = [
        'new'        => 'New',
        'returning'  => 'Old',
        'transferee' => 'Transferee',
        'returnee'   => 'Balik-Aral',
    ];

    /**
     * @param  array{scope: string, school_year_id: int, grade_level?: int, section_id?: int, student_id?: int}  $filters
     * @return array{path: string, filename: string}
     */
    public function export(array $filters): array
    {
        $schoolYear = SchoolYear::findOrFail($filters['school_year_id']);

        $query = StudentEnrollment::with('student')
            ->where('school_year_id', $schoolYear->id)
            ->where('status', 'enrolled');

        match ($filters['scope']) {
            'grade'   => $query->where('grade_level', $filters['grade_level']),
            'section' => $query->where('section_id', $filters['section_id']),
            'student' => $query->where('student_id', $filters['student_id']),
            default   => null,
        };

        $enrollments = $query->orderBy('grade_level')->get();

        $templatePath = base_path('storage/' . self::TEMPLATE_PATH);
        $spreadsheet  = IOFactory::load($templatePath);
        $sheet        = $spreadsheet->getActiveSheet();

        $row = 2;
        foreach ($enrollments as $enrollment) {
            $student = $enrollment->student;
            if (! $student) {
                continue;
            }

            $sheet->setCellValue("B{$row}", $enrollment->grade_level);
            $sheet->setCellValue("C{$row}", $student->lastname);
            $sheet->setCellValue("D{$row}", $student->firstname);
            $sheet->setCellValue("E{$row}", $student->middlename);
            $sheet->setCellValue("G{$row}", $student->sex);
            $sheet->setCellValue("H{$row}", $this->formatBirthday($student->birthday));
            $sheet->setCellValue("I{$row}", $student->houseno);
            $sheet->setCellValue("L{$row}", $student->barangay);
            $sheet->setCellValue("M{$row}", $this->formatMuniCityProvReg($student));
            $sheet->setCellValue("N{$row}", self::ENROLLMENT_TYPE_LABELS[$enrollment->enrollment_type] ?? $enrollment->enrollment_type);
            $sheet->setCellValue("P{$row}", $student->schooltype);
            $sheet->setCellValue("Y{$row}", $student->feduc);
            $sheet->setCellValue("Z{$row}", $student->meduc);
            $sheet->setCellValue("AB{$row}", $student->foccupation);
            $sheet->setCellValue("AC{$row}", $student->moccupation);
            $sheet->setCellValue("AR{$row}", $student->remarks);

            $row++;
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'enrollment_export');
        $writer   = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($tempFile);

        return [
            'path'     => $tempFile,
            'filename' => "Enrollment_Data_{$schoolYear->name}.xlsx",
        ];
    }

    private function formatBirthday(?string $birthday): ?string
    {
        if (! $birthday) {
            return null;
        }

        try {
            return Carbon::parse($birthday)->format('m-d-Y');
        } catch (\Exception) {
            return $birthday;
        }
    }

    private function formatMuniCityProvReg($student): ?string
    {
        $parts = array_filter([$student->municipal, $student->province, $student->region]);

        return $parts ? implode(', ', $parts) : null;
    }
}
