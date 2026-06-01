<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Consultation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class HealthStatisticsController extends Controller
{
    public function report(Request $request)
    {
        $user = $request->user();

        if (! $user->hasPermission('health.manage')) {
            abort(403);
        }

        $start = $request->query('start');
        $end = $request->query('end');

        $query = Consultation::query();

        // Prefer filtering by date_attended when present
        if ($start) {
            try {
                $s = Carbon::parse($start)->toDateString();
                $query->whereDate('date_attended', '>=', $s);
            } catch (\Throwable $e) { }
        }
        if ($end) {
            try {
                $eD = Carbon::parse($end)->toDateString();
                $query->whereDate('date_attended', '<=', $eD);
            } catch (\Throwable $e) { }
        }

        $consultations = $query->get();

        // Build distinct diseases list and counts by age for students and employees
        $diseases = [];
        foreach ($consultations as $c) {
            $rawDisease = trim((string)($c->concern ?? $c->reason ?? ''));
            if ($rawDisease === '') $rawDisease = 'Unspecified';
            $dname = ucwords(strtolower($rawDisease));

            if (!isset($diseases[$dname])) {
                $diseases[$dname] = ['students' => [], 'employees' => []];
            }

            // determine if requestor is a student
            $isStudent = false;
            if (!empty($c->requestor_type) && $c->requestor_type === 'student') {
                $isStudent = true;
            } elseif (!empty($c->requestor_id)) {
                // quick existence check in students table
                $isStudent = (bool) DB::table('students')->where('id', $c->requestor_id)->exists();
            }

            $person = null;
            if ($isStudent) {
                $person = !empty($c->requestor_id) ? DB::table('students')->where('id', $c->requestor_id)->first() : null;
            } else {
                $person = !empty($c->requestor_id) ? DB::table('users')->where('id', $c->requestor_id)->first() : null;
            }

            // try to detect birthdate column and compute age
            $birth = null;
            if ($person) {
                foreach (['birthdate','birth_date','dob','date_of_birth'] as $col) {
                    if (isset($person->$col) && !empty($person->$col)) { $birth = $person->$col; break; }
                }
            }
            if (empty($birth) && Schema::hasColumn('consultations', 'birthdate') && isset($c->birthdate)) {
                $birth = $c->birthdate;
            }

            $age = null;
            try { if ($birth) $age = Carbon::parse($birth)->age; } catch (\Throwable $e) { }

            // determine sex
            $sex = null;
            if ($person && isset($person->sex)) $sex = $person->sex;
            if (empty($sex) && Schema::hasColumn('consultations', 'sex') && isset($c->sex)) $sex = $c->sex;
            $normSex = null;
            if (!empty($sex)) {
                $s = strtolower(trim((string)$sex));
                if (in_array($s, ['m','male'])) $normSex = 'Male';
                elseif (in_array($s, ['f','female'])) $normSex = 'Female';
            }

            $bucket = $age !== null ? (string) $age : 'Unknown';
            $groupKey = $isStudent ? 'students' : 'employees';

            if (!isset($diseases[$dname][$groupKey][$bucket])) {
                $diseases[$dname][$groupKey][$bucket] = ['Male' => 0, 'Female' => 0, 'Total' => 0];
            }
            if ($normSex) {
                $diseases[$dname][$groupKey][$bucket][$normSex]++;
            } else {
                // increment total for unknown sex
                $diseases[$dname][$groupKey][$bucket]['Total']++;
            }
            // recompute total
            $diseases[$dname][$groupKey][$bucket]['Total'] =
                ($diseases[$dname][$groupKey][$bucket]['Male'] ?? 0) +
                ($diseases[$dname][$groupKey][$bucket]['Female'] ?? 0) +
                ($diseases[$dname][$groupKey][$bucket]['Total'] ?? 0);
        }

        // normalize into array for the view, sort ages numerically when possible
        $diseasesData = [];
        foreach ($diseases as $name => $data) {
            // sort numeric keys, keep 'Unknown' at end
            $skeys = array_filter(array_keys($data['students']), fn($k) => $k !== 'Unknown');
            sort($skeys, SORT_NUMERIC);
            $students = [];
            foreach ($skeys as $k) $students[$k] = $data['students'][$k];
            if (isset($data['students']['Unknown'])) $students['Unknown'] = $data['students']['Unknown'];

            $ekeys = array_filter(array_keys($data['employees']), fn($k) => $k !== 'Unknown');
            sort($ekeys, SORT_NUMERIC);
            $employees = [];
            foreach ($ekeys as $k) $employees[$k] = $data['employees'][$k];
            if (isset($data['employees']['Unknown'])) $employees['Unknown'] = $data['employees']['Unknown'];

            $diseasesData[] = ['name' => $name, 'students' => $students, 'employees' => $employees];
        }

        // Build a summarized table per disease: student/employee male/female/total
        $diseaseSummary = [];
        foreach ($diseasesData as $d) {
            $sm = $sf = $st = 0;
            foreach ($d['students'] as $age => $row) {
                $sm += $row['Male'] ?? 0;
                $sf += $row['Female'] ?? 0;
                $st += $row['Total'] ?? (($row['Male'] ?? 0) + ($row['Female'] ?? 0));
            }

            $em = $ef = $et = 0;
            foreach ($d['employees'] as $age => $row) {
                $em += $row['Male'] ?? 0;
                $ef += $row['Female'] ?? 0;
                $et += $row['Total'] ?? (($row['Male'] ?? 0) + ($row['Female'] ?? 0));
            }

            $total = $st + $et;
            $diseaseSummary[] = [
                'name' => $d['name'],
                'students_male' => $sm,
                'students_female' => $sf,
                'students_total' => $st,
                'employees_male' => $em,
                'employees_female' => $ef,
                'employees_total' => $et,
                'total' => $total,
            ];
        }

        // sort by total desc
        usort($diseaseSummary, function ($a, $b) { return $b['total'] <=> $a['total']; });

        // Map free-text reasons into analyzed categories
        $mapReason = function ($text) {
            $t = strtolower(trim($text ?? ''));
            if ($t === '') return 'Unspecified';

            $categories = [
                'Respiratory' => ['cough','cold','sore throat','phlegm','runny','rhinitis','nasal','nasopharyngitis','throat','breath','wheez','asthma','coryza','flu','influenza'],
                'Fever' => ['fever','febrile','temperature','febrile'],
                'Gastrointestinal' => ['stomach','vomit','vomiting','diarr','abdominal','nausea','gastric','stomachache','colic'],
                'Injury' => ['injury','wound','cut','laceration','abrasion','trauma','fracture','sprain','bleed','bruise'],
                'Skin' => ['rash','itch','itching','dermat','eczema','skin','rash/','rash '],
                'Dental' => ['tooth','toothache','dental','gum','wisdom','teeth'],
                'Eye/Ear/Nose' => ['eye','ear','vision','conjunct','sight','hearing','nose','earache','conjunctivitis','red eye','sore eye'],
                'Headache' => ['headache','migraine','head pain','head ache'],
                'Musculoskeletal' => ['back','neck','shoulder','muscle','joint','arm','leg','knee','hip','waist','lumbar','sprain','pain'],
                'Allergy' => ['allerg','allergy','urticaria','hives'],
                'Other' => []
            ];

            foreach ($categories as $cat => $keywords) {
                foreach ($keywords as $kw) {
                    if ($kw === '') continue;
                    if (strpos($t, $kw) !== false) return $cat;
                }
            }

            return 'Other';
        };

        // We'll aggregate by mapped category and by requester category: Faculty, Staff, Student each split by Male/Female.
        $counts = []; // category => ["Faculty (Male)"=>int,...]
        foreach ($consultations as $c) {
            $raw = $c->concern ?? $c->reason ?? '';
            $reasonCategory = $mapReason($raw);

            // determine sex
            $sex = null;
            if (!empty($c->requestor_type) && $c->requestor_type === 'student' && !empty($c->requestor_id)) {
                $student = DB::table('students')->where('id', $c->requestor_id)->first();
                if ($student && isset($student->sex)) $sex = $student->sex;
            }

            if (empty($sex) && !empty($c->requestor_id)) {
                $u = DB::table('users')->where('id', $c->requestor_id)->first();
                if ($u && isset($u->sex)) $sex = $u->sex;
            }

            if (empty($sex) && Schema::hasColumn('consultations', 'sex') && isset($c->sex)) {
                $sex = $c->sex;
            }

            $normSex = null;
            if (!empty($sex)) {
                $s = strtolower(trim((string)$sex));
                if (in_array($s, ['m','male'])) $normSex = 'Male';
                elseif (in_array($s, ['f','female'])) $normSex = 'Female';
            }

            // determine requester category: Student or employee -> Faculty/Staff
            $requestorCategory = 'Staff';
            if (!empty($c->requestor_type) && $c->requestor_type === 'student') {
                $requestorCategory = 'Student';
            } else if (!empty($c->requestor_id)) {
                $u = DB::table('users')->where('id', $c->requestor_id)->first();
                if ($u) {
                    // try to resolve role name
                    $roleName = null;
                    if (isset($u->roleid) || isset($u->role_id)) {
                        $rid = $u->roleid ?? $u->role_id ?? null;
                        if ($rid) {
                            $rrec = DB::table('roles')->where('id', $rid)->first();
                            if ($rrec && isset($rrec->name)) $roleName = $rrec->name;
                        }
                    }
                    if (empty($roleName) && isset($u->role) && $u->role) $roleName = $u->role;
                    if (!empty($roleName)) {
                        $rl = strtolower($roleName);
                        if (str_contains($rl, 'faculty')) $requestorCategory = 'Faculty';
                        elseif (str_contains($rl, 'staff')) $requestorCategory = 'Staff';
                        else $requestorCategory = (str_contains($rl, 'teacher') ? 'Faculty' : 'Staff');
                    }
                }
            }

            // initialize counts for this mapped reason category
            if (!isset($counts[$reasonCategory])) {
                $counts[$reasonCategory] = [
                    'Faculty (Male)' => 0, 'Faculty (Female)' => 0,
                    'Staff (Male)' => 0, 'Staff (Female)' => 0,
                    'Student (Male)' => 0, 'Student (Female)' => 0,
                    'Other' => 0,
                ];
            }

            if ($normSex && in_array($requestorCategory, ['Faculty','Staff','Student'])) {
                $key = $requestorCategory . ' (' . $normSex . ')';
                if (!isset($counts[$reasonCategory][$key])) $counts[$reasonCategory][$key] = 0;
                $counts[$reasonCategory][$key]++;
            } else {
                $counts[$reasonCategory]['Other']++;
            }
        }

        // prepare ordered arrays for charting (sort by total desc)
        $rows = [];
        foreach ($counts as $r => $data) {
            $total = array_sum($data);
            $rows[] = ['reason' => $r, 'data' => $data, 'total' => $total];
        }

        usort($rows, function ($a, $b) { return $b['total'] <=> $a['total']; });

        $labels = array_map(fn($r) => $r['reason'], $rows);
        $facultyMale = array_map(fn($r) => $r['data']['Faculty (Male)'] ?? 0, $rows);
        $facultyFemale = array_map(fn($r) => $r['data']['Faculty (Female)'] ?? 0, $rows);
        $staffMale = array_map(fn($r) => $r['data']['Staff (Male)'] ?? 0, $rows);
        $staffFemale = array_map(fn($r) => $r['data']['Staff (Female)'] ?? 0, $rows);
        $studentMale = array_map(fn($r) => $r['data']['Student (Male)'] ?? 0, $rows);
        $studentFemale = array_map(fn($r) => $r['data']['Student (Female)'] ?? 0, $rows);

        return Inertia::render('Health/Statistics/Index', [
            'labels' => $labels,
            'facultyMale' => $facultyMale,
            'facultyFemale' => $facultyFemale,
            'staffMale' => $staffMale,
            'staffFemale' => $staffFemale,
            'studentMale' => $studentMale,
            'studentFemale' => $studentFemale,
            'start' => $start,
            'end' => $end,
            'diseasesData' => $diseasesData,
            'diseaseSummary' => $diseaseSummary,
        ]);
    }
}
