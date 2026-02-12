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
        $role = $user->role->name ?? '';

        if (! in_array($role, ['Administrator','Nurse','Clinic'])) {
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
        ]);
    }
}
