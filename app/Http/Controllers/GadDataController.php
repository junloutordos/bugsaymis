<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GadDataController extends Controller
{
    private const CACHE_TTL = 3600;

    public function index(): Response
    {
        $data = Cache::remember('gad_data_v2', self::CACHE_TTL, fn () => $this->buildData());

        return Inertia::render('GadData/Index', $data);
    }

    private function buildData(): array
    {
        return [
            'employees'   => $this->employees(),
            'students'    => $this->students(),
            'leave'       => $this->leave(),
            'training'    => $this->training(),
            'rewards'     => $this->rewards(),
            'guidance'    => $this->guidance(),
            'ipcr'        => $this->ipcr(),
            'csm'         => $this->csm(),
            'generatedAt' => now()->toIso8601String(),
        ];
    }

    /**
     * Normalize a raw query result (rows with sex + total) into {male, female}.
     * Accepts 'male'/'m' and 'female'/'f' (case-insensitive).
     */
    private function normalizeSex(array $rows, string $sexCol = 'sex'): array
    {
        $male = 0;
        $female = 0;
        foreach ($rows as $row) {
            $sex = strtolower((string) ($row->$sexCol ?? ''));
            if (in_array($sex, ['male', 'm'], true))       $male   += (int) $row->total;
            elseif (in_array($sex, ['female', 'f'], true)) $female += (int) $row->total;
        }
        return ['male' => $male, 'female' => $female];
    }

    /** Collapse a raw grouped result into [{name, male, female}]. */
    private function buildBreakdown(\Illuminate\Support\Collection $raw, string $groupKey, string $sexCol = 'sex'): array
    {
        $map = [];
        foreach ($raw as $row) {
            $key = (string) $row->$groupKey;
            if (!isset($map[$key])) $map[$key] = ['name' => $key, 'male' => 0, 'female' => 0];
            $sex = strtolower((string) ($row->$sexCol ?? ''));
            if (in_array($sex, ['male', 'm'], true))       $map[$key]['male']   += (int) $row->total;
            elseif (in_array($sex, ['female', 'f'], true)) $map[$key]['female'] += (int) $row->total;
        }
        return array_values(array_map(
            fn ($d) => ['name' => $d['name'], 'male' => $d['male'], 'female' => $d['female']],
            $map
        ));
    }

    // ─── Sections ─────────────────────────────────────────────────────────────

    private function employees(): array
    {
        $activeFilter = fn ($q) => $q->where('users.status', '<>', 'inactive')->whereNotNull('users.sex');

        $totalRaw = DB::table('users')
            ->where('status', '<>', 'inactive')
            ->whereNotNull('sex')
            ->selectRaw('sex, count(*) as total')
            ->groupBy('sex')
            ->get();

        $divisionRaw = DB::table('users')
            ->join('divisions', 'users.division_id', '=', 'divisions.id')
            ->where('users.status', '<>', 'inactive')
            ->whereNotNull('users.sex')
            ->selectRaw('divisions.division_name as division_name, users.sex, count(*) as total')
            ->groupBy('divisions.division_name', 'users.sex')
            ->orderBy('divisions.division_name')
            ->get();

        $bandRaw = DB::table('users')
            ->where('status', '<>', 'inactive')
            ->whereNotNull('sex')
            ->whereNotNull('salary_grade')
            ->selectRaw("
                CASE
                    WHEN salary_grade BETWEEN 1 AND 9   THEN 'Rank-and-File (SG 1–9)'
                    WHEN salary_grade BETWEEN 10 AND 14 THEN 'Supervisory (SG 10–14)'
                    WHEN salary_grade >= 15             THEN 'Managerial (SG 15+)'
                    ELSE 'Unclassified'
                END as band,
                sex, count(*) as total
            ")
            ->groupBy('band', 'sex')
            ->get();

        return [
            'total'        => $this->normalizeSex($totalRaw->toArray()),
            'byDivision'   => $this->buildBreakdown($divisionRaw, 'division_name'),
            'bySalaryBand' => $this->buildBreakdown($bandRaw, 'band'),
        ];
    }

    private function students(): array
    {
        $raw = DB::table('student_enrollments')
            ->join('students', 'student_enrollments.student_id', '=', 'students.id')
            ->where('student_enrollments.status', 'enrolled')
            ->whereNotNull('students.sex')
            ->selectRaw('student_enrollments.grade_level, students.sex, count(*) as total')
            ->groupBy('student_enrollments.grade_level', 'students.sex')
            ->orderBy('student_enrollments.grade_level')
            ->get();

        $total  = ['male' => 0, 'female' => 0];
        $grades = [];
        foreach ($raw as $row) {
            $name = 'Grade ' . $row->grade_level;
            if (!isset($grades[$name])) $grades[$name] = ['name' => $name, 'male' => 0, 'female' => 0];
            $sex = strtolower((string) $row->sex);
            if (in_array($sex, ['male', 'm'], true)) {
                $grades[$name]['male'] += (int) $row->total;
                $total['male'] += (int) $row->total;
            } elseif (in_array($sex, ['female', 'f'], true)) {
                $grades[$name]['female'] += (int) $row->total;
                $total['female'] += (int) $row->total;
            }
        }

        return [
            'total'   => ['male' => $total['male'], 'female' => $total['female']],
            'byGrade' => array_values(array_map(
                fn ($g) => ['name' => $g['name'], 'male' => $g['male'], 'female' => $g['female']],
                $grades
            )),
        ];
    }

    private function leave(): array
    {
        $base = DB::table('leave_applications')
            ->join('users', 'leave_applications.user_id', '=', 'users.id')
            ->whereNotNull('users.sex')
            ->whereNull('leave_applications.deleted_at');

        $totalRaw    = (clone $base)->selectRaw('users.sex, count(*) as total')->groupBy('users.sex')->get();
        $approvedRaw = (clone $base)->where('leave_applications.status', 'approved')
            ->selectRaw('users.sex, count(*) as total')->groupBy('users.sex')->get();

        $byTypeRaw = (clone $base)
            ->join('leave_types', 'leave_applications.leave_type_id', '=', 'leave_types.id')
            ->selectRaw('leave_types.name as leave_type, users.sex, count(*) as total')
            ->groupBy('leave_types.name', 'users.sex')
            ->orderBy('leave_types.name')
            ->get();

        return [
            'total'    => $this->normalizeSex($totalRaw->toArray()),
            'approved' => $this->normalizeSex($approvedRaw->toArray()),
            'byType'   => $this->buildBreakdown($byTypeRaw, 'leave_type'),
        ];
    }

    private function training(): array
    {
        $totalRaw = DB::table('training_records')
            ->join('users', 'training_records.user_id', '=', 'users.id')
            ->whereNotNull('users.sex')
            ->selectRaw('users.sex, count(distinct training_records.user_id) as total')
            ->groupBy('users.sex')
            ->get();

        $byYearRaw = DB::table('training_records')
            ->join('users', 'training_records.user_id', '=', 'users.id')
            ->whereNotNull('users.sex')
            ->whereNotNull('training_records.date_from')
            ->selectRaw('YEAR(training_records.date_from) as band, users.sex, count(*) as total')
            ->groupBy('band', 'users.sex')
            ->orderBy('band')
            ->get();

        return [
            'total'  => $this->normalizeSex($totalRaw->toArray()),
            'byYear' => $this->buildBreakdown($byYearRaw, 'band'),
        ];
    }

    private function rewards(): array
    {
        $base = DB::table('reward_nominations')
            ->join('users', 'reward_nominations.nominee_id', '=', 'users.id')
            ->whereNotNull('users.sex')
            ->whereNull('reward_nominations.deleted_at');

        $totalRaw    = (clone $base)->selectRaw('users.sex, count(*) as total')->groupBy('users.sex')->get();
        $byStatusRaw = (clone $base)
            ->selectRaw('reward_nominations.status as band, users.sex, count(*) as total')
            ->groupBy('reward_nominations.status', 'users.sex')
            ->get();

        $byStatus = $this->buildBreakdown($byStatusRaw, 'band');
        foreach ($byStatus as &$s) {
            $s['name'] = ucfirst($s['name']);
        }

        return [
            'total'    => $this->normalizeSex($totalRaw->toArray()),
            'byStatus' => $byStatus,
        ];
    }

    private function guidance(): array
    {
        $totalRaw = DB::table('guidance_session_reports')
            ->whereNotNull('client_sex')
            ->selectRaw('client_sex as sex, count(*) as total')
            ->groupBy('client_sex')
            ->get();

        $byYearRaw = DB::table('guidance_session_reports')
            ->whereNotNull('client_sex')
            ->whereNotNull('school_year')
            ->selectRaw('school_year as band, client_sex as sex, count(*) as total')
            ->groupBy('school_year', 'client_sex')
            ->orderBy('school_year')
            ->get();

        return [
            'total'  => $this->normalizeSex($totalRaw->toArray()),
            'byYear' => $this->buildBreakdown($byYearRaw, 'band'),
        ];
    }

    private function ipcr(): array
    {
        $totalRaw = DB::table('ipcrs')
            ->join('users', 'ipcrs.user_id', '=', 'users.id')
            ->whereNotNull('users.sex')
            ->selectRaw('users.sex, count(distinct ipcrs.user_id) as total')
            ->groupBy('users.sex')
            ->get();

        $avgRaw = DB::table('ipcrs')
            ->join('users', 'ipcrs.user_id', '=', 'users.id')
            ->whereNotNull('users.sex')
            ->whereNotNull('ipcrs.supervisor_rating')
            ->selectRaw('users.sex, round(avg(ipcrs.supervisor_rating), 2) as avg_rating')
            ->groupBy('users.sex')
            ->get();

        $avgRatings = ['male' => null, 'female' => null];
        foreach ($avgRaw as $row) {
            $sex = strtolower((string) $row->sex);
            if ($sex === 'male')   $avgRatings['male']   = (float) $row->avg_rating;
            elseif ($sex === 'female') $avgRatings['female'] = (float) $row->avg_rating;
        }

        return [
            'total'      => $this->normalizeSex($totalRaw->toArray()),
            'avgRatings' => $avgRatings,
        ];
    }

    private function csm(): array
    {
        $totalRaw = DB::table('csm_responses')
            ->whereNotNull('sex')
            ->selectRaw('sex, count(*) as total')
            ->groupBy('sex')
            ->get();

        $byClientTypeRaw = DB::table('csm_responses')
            ->whereNotNull('sex')
            ->whereNotNull('client_type')
            ->selectRaw('client_type as band, sex, count(*) as total')
            ->groupBy('client_type', 'sex')
            ->orderBy('client_type')
            ->get();

        return [
            'total'        => $this->normalizeSex($totalRaw->toArray()),
            'byClientType' => $this->buildBreakdown($byClientTypeRaw, 'band'),
        ];
    }
}
