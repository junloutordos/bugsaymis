<?php

namespace App\Http\Controllers\Guidance;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CumulativeRecordController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('guidance.cumulative.view');

        $search = $request->input('search', '');
        $status = $request->input('status', '');

        $query = DB::table('students')
            ->select('id', 'lastname', 'firstname', 'middlename', 'pisaysystemID', 'batch', 'status', 'sex', 'student_email');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('lastname', 'like', "%{$search}%")
                  ->orWhere('firstname', 'like', "%{$search}%")
                  ->orWhere('pisaysystemID', 'like', "%{$search}%")
                  ->orWhere('lrn', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        $students = $query->orderBy('batch', 'desc')->orderBy('lastname')->orderBy('firstname')->get();

        // Attach profile completion counts per student
        $ids = $students->pluck('pisaysystemID')->filter()->values()->toArray();

        $completionCounts = [];
        if ($ids) {
            $tables = [
                'egcu_academic_standing', 'egcu_academic_preferences',
                'egcu_club_memberships', 'egcu_activity_participations',
                'egcu_awards', 'egcu_external_activities',
                'egcu_personality_traits', 'egcu_selfassessment',
                'egcu_self_statements', 'egcu_talents',
                'egcu_vocational', 'egcu_residence',
                'egcu_friend_age_patterns', 'egcu_bestfriends',
                'egcu_health',
            ];
            foreach ($tables as $table) {
                $rows = DB::table($table)
                    ->select('pisaySystemID')
                    ->whereIn('pisaySystemID', $ids)
                    ->distinct()
                    ->pluck('pisaySystemID');
                foreach ($rows as $pid) {
                    $completionCounts[$pid] = ($completionCounts[$pid] ?? 0) + 1;
                }
            }
        }

        $students = $students->map(function ($s) use ($completionCounts) {
            $s->profile_sections = $completionCounts[$s->pisaysystemID] ?? 0;
            $s->profile_pct      = (int) round((($completionCounts[$s->pisaysystemID] ?? 0) / 15) * 100);
            return $s;
        });

        return Inertia::render('Guidance/CumulativeRecords/Index', [
            'students' => $students,
            'filters'  => ['search' => $search, 'status' => $status],
        ]);
    }

    public function show(string $pisaySystemID)
    {
        $this->authorize('guidance.cumulative.view');

        $student = DB::table('students')
            ->where('pisaysystemID', $pisaySystemID)
            ->first();

        abort_if(! $student, 404, 'Student not found.');

        // Load all egcu profile data keyed by grade level
        $profile = [
            'academic_standing'   => $this->byLevel('egcu_academic_standing', $pisaySystemID, 'gradelevel'),
            'academic_preferences'=> $this->byLevel('egcu_academic_preferences', $pisaySystemID, 'levelid'),
            'club_memberships'    => $this->byLevel('egcu_club_memberships', $pisaySystemID),
            'activity_participations' => $this->byLevel('egcu_activity_participations', $pisaySystemID),
            'awards'              => $this->byLevel('egcu_awards', $pisaySystemID),
            'external_activities' => $this->byLevel('egcu_external_activities', $pisaySystemID),
            'personality_traits'  => $this->byLevel('egcu_personality_traits', $pisaySystemID),
            'selfassessment'      => $this->byLevel('egcu_selfassessment', $pisaySystemID),
            'self_statements'     => $this->byLevel('egcu_self_statements', $pisaySystemID),
            'talents'             => $this->byLevel('egcu_talents', $pisaySystemID),
            'vocational'          => $this->byLevel('egcu_vocational', $pisaySystemID),
            'residence'           => $this->byLevel('egcu_residence', $pisaySystemID),
            'friend_age_patterns' => $this->byLevel('egcu_friend_age_patterns', $pisaySystemID),
            'friend_group_patterns'=> $this->byLevel('egcu_friend_group_patterns', $pisaySystemID),
            'bestfriends'         => $this->byLevel('egcu_bestfriends', $pisaySystemID),
            'health'              => $this->byLevelInt('egcu_health', $pisaySystemID),
        ];

        return Inertia::render('Guidance/CumulativeRecords/Show', [
            'student' => $student,
            'profile' => $profile,
        ]);
    }

    private function byLevel(string $table, string $pisaySystemID, string $levelCol = 'levelid'): array
    {
        return DB::table($table)
            ->where('pisaySystemID', $pisaySystemID)
            ->orderBy($levelCol)
            ->get()
            ->toArray();
    }

    private function byLevelInt(string $table, string $pisaySystemID): array
    {
        return DB::table($table)
            ->where('pisaySystemID', $pisaySystemID)
            ->orderBy('levelid')
            ->get()
            ->toArray();
    }
}
