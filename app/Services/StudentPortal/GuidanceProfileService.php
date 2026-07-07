<?php

namespace App\Services\StudentPortal;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Shared logic for the EGCU guidance profile forms — used by both the
 * student web portal and the mobile API.
 */
class GuidanceProfileService
{
    /**
     * Data needed to render / pre-fill the profile forms.
     */
    public function showData(string $pisayID, int $gradeLevel): array
    {
        $student = DB::table('students')->where('pisaysystemID', $pisayID)->first();
        $sy      = DB::table('school_years')->where('is_current', 1)->first();

        $existing = [
            'academic'      => DB::table('egcu_academic_preferences')
                ->where('pisaySystemID', $pisayID)->where('levelid', $gradeLevel)->first(),
            'clubs'         => DB::table('egcu_club_memberships')
                ->where('pisaySystemID', $pisayID)->where('levelid', $gradeLevel)->get()->toArray(),
            'activities'    => DB::table('egcu_activity_participations')
                ->where('pisaySystemID', $pisayID)->where('levelid', $gradeLevel)->get()->toArray(),
            'awards'        => DB::table('egcu_awards')
                ->where('pisaySystemID', $pisayID)->where('levelid', $gradeLevel)->get()->toArray(),
            'external'      => DB::table('egcu_external_activities')
                ->where('pisaySystemID', $pisayID)->where('levelid', $gradeLevel)->get()->toArray(),
            'personality'   => DB::table('egcu_personality_traits')
                ->where('pisaySystemID', $pisayID)->where('levelid', $gradeLevel)->first(),
            'selfassessment'=> DB::table('egcu_selfassessment')
                ->where('pisaySystemID', $pisayID)->where('levelid', $gradeLevel)->first(),
            'statements'    => DB::table('egcu_self_statements')
                ->where('pisaySystemID', $pisayID)->where('levelid', $gradeLevel)->first(),
            'friends'       => DB::table('egcu_bestfriends')
                ->where('pisaySystemID', $pisayID)->where('levelid', $gradeLevel)->get()->toArray(),
            'friend_age'    => DB::table('egcu_friend_age_patterns')
                ->where('pisaySystemID', $pisayID)->where('levelid', $gradeLevel)->first(),
            'friend_group'  => DB::table('egcu_friend_group_patterns')
                ->where('pisaySystemID', $pisayID)->where('levelid', $gradeLevel)->first(),
            'talents'       => DB::table('egcu_talents')
                ->where('pisaySystemID', $pisayID)->where('levelid', $gradeLevel)->get()->toArray(),
            'vocational'    => DB::table('egcu_vocational')
                ->where('pisaySystemID', $pisayID)->where('levelid', $gradeLevel)->first(),
            'residence'     => DB::table('egcu_residence')
                ->where('pisaySystemID', $pisayID)->where('levelid', $gradeLevel)->first(),
            'health'        => DB::table('egcu_health')
                ->where('pisaySystemID', $pisayID)->where('levelid', $gradeLevel)->first(),
        ];

        return [
            'student'        => $student,
            'grade_level'    => $gradeLevel,
            'school_year'    => $sy?->name,
            'school_year_id' => $sy?->id,
            'existing'       => $existing,
            'submitted'      => FormSubmissions::map($pisayID, $sy?->id),
        ];
    }

    /**
     * Validate and persist one profile section, then mark it submitted
     * for the current school year. Throws ValidationException on bad input
     * and aborts 404 on an unknown section.
     */
    public function saveSection(Request $request, string $section, string $pisayID, int $gradeLevel): void
    {
        match ($section) {
            'academic'    => $this->saveAcademic($request, $pisayID, $gradeLevel),
            'activities'  => $this->saveActivities($request, $pisayID, $gradeLevel),
            'social'      => $this->saveSocial($request, $pisayID, $gradeLevel),
            'career'      => $this->saveCareer($request, $pisayID, $gradeLevel),
            'residence'   => $this->saveResidence($request, $pisayID, $gradeLevel),
            'health'      => $this->saveHealth($request, $pisayID, $gradeLevel),
            default       => abort(404),
        };

        FormSubmissions::mark($pisayID, $gradeLevel, $section);
    }

    // ── Section savers ─────────────────────────────────────────────────────────

    private function saveAcademic(Request $request, string $pid, int $lvl): void
    {
        $data = $request->validate([
            'subject_like'        => ['nullable', 'string', 'max:100'],
            'subject_least'       => ['nullable', 'string', 'max:100'],
            'subject_difficult'   => ['nullable', 'string', 'max:100'],
            'subjectlearnedmost'  => ['nullable', 'string', 'max:100'],
            'subjectlearnedleast' => ['nullable', 'string', 'max:100'],
            'subjecttaugtbest'    => ['nullable', 'string', 'max:100'],
            'subjecttaugtworst'   => ['nullable', 'string', 'max:100'],
        ]);

        DB::table('egcu_academic_preferences')->updateOrInsert(
            ['pisaySystemID' => $pid, 'levelid' => $lvl],
            array_merge($data, ['syid' => 0])
        );
    }

    private function saveActivities(Request $request, string $pid, int $lvl): void
    {
        $data = $request->validate([
            'clubs'              => ['nullable', 'array'],
            'clubs.*.clubname'   => ['required', 'string', 'max:200'],
            'clubs.*.position'   => ['nullable', 'string', 'max:100'],
            'activities'                => ['nullable', 'array'],
            'activities.*.activityname' => ['required', 'string', 'max:100'],
            'activities.*.category'     => ['nullable', 'string', 'max:50'],
            'activities.*.involvement'  => ['nullable', 'string', 'max:500'],
            'awards'                  => ['nullable', 'array'],
            'awards.*.competition'    => ['required', 'string', 'max:200'],
            'awards.*.award'          => ['nullable', 'string', 'max:100'],
            'awards.*.category'       => ['nullable', 'string', 'max:50'],
        ]);

        // Replace all current-year entries
        DB::table('egcu_club_memberships')->where('pisaySystemID', $pid)->where('levelid', $lvl)->delete();
        foreach ($data['clubs'] ?? [] as $row) {
            DB::table('egcu_club_memberships')->insert(['pisaySystemID' => $pid, 'levelid' => $lvl, 'clubname' => $row['clubname'], 'position' => $row['position'] ?? null]);
        }

        DB::table('egcu_activity_participations')->where('pisaySystemID', $pid)->where('levelid', $lvl)->delete();
        foreach ($data['activities'] ?? [] as $row) {
            DB::table('egcu_activity_participations')->insert(['pisaySystemID' => $pid, 'levelid' => $lvl, 'activityname' => $row['activityname'], 'category' => $row['category'] ?? null, 'involvement' => $row['involvement'] ?? null]);
        }

        DB::table('egcu_awards')->where('pisaySystemID', $pid)->where('levelid', $lvl)->delete();
        foreach ($data['awards'] ?? [] as $row) {
            DB::table('egcu_awards')->insert(['pisaySystemID' => $pid, 'levelid' => $lvl, 'competition' => $row['competition'], 'award' => $row['award'] ?? null, 'category' => $row['category'] ?? null]);
        }
    }

    private function saveSocial(Request $request, string $pid, int $lvl): void
    {
        $data = $request->validate([
            'traits'       => ['nullable', 'string', 'max:500'],
            'physical'     => ['nullable', 'integer', 'min:1', 'max:5'],
            'mental'       => ['nullable', 'integer', 'min:1', 'max:5'],
            'speech_en'    => ['nullable', 'integer', 'min:1', 'max:5'],
            'speech_fil'   => ['nullable', 'integer', 'min:1', 'max:5'],
            'writing'      => ['nullable', 'integer', 'min:1', 'max:5'],
            'personality'  => ['nullable', 'integer', 'min:1', 'max:5'],
            'character1'   => ['nullable', 'integer', 'min:1', 'max:5'],
            'state1'  => ['nullable', 'string', 'max:500'],
            'state2'  => ['nullable', 'string', 'max:500'],
            'state3'  => ['nullable', 'string', 'max:500'],
            'state4'  => ['nullable', 'string', 'max:500'],
            'state5'  => ['nullable', 'string', 'max:500'],
            'state6'  => ['nullable', 'string', 'max:500'],
            'state7'  => ['nullable', 'string', 'max:500'],
            'state8'  => ['nullable', 'string', 'max:500'],
            'state9'  => ['nullable', 'string', 'max:500'],
            'state10' => ['nullable', 'string', 'max:500'],
            'fr_age'          => ['nullable', 'string', 'max:5'],
            'fr_older'        => ['nullable', 'string', 'max:5'],
            'fr_younger'      => ['nullable', 'string', 'max:5'],
            'fr_boys'         => ['nullable', 'string', 'max:5'],
            'fr_girls'        => ['nullable', 'string', 'max:5'],
            'fr_otherschool'  => ['nullable', 'string', 'max:5'],
            'fr_fromthischool'=> ['nullable', 'string', 'max:5'],
            'friends'         => ['nullable', 'array'],
            'friends.*'       => ['nullable', 'string', 'max:500'],
            'talents'         => ['nullable', 'array'],
            'talents.*'       => ['nullable', 'string', 'max:300'],
        ]);

        DB::table('egcu_personality_traits')->updateOrInsert(
            ['pisaySystemID' => $pid, 'levelid' => $lvl],
            ['traits' => $data['traits'] ?? null]
        );

        DB::table('egcu_selfassessment')->updateOrInsert(
            ['pisaySystemID' => $pid, 'levelid' => $lvl],
            collect($data)->only(['physical','mental','speech_en','speech_fil','writing','personality','character1'])->all()
        );

        DB::table('egcu_self_statements')->updateOrInsert(
            ['pisaySystemID' => $pid, 'levelid' => $lvl],
            collect($data)->only(['state1','state2','state3','state4','state5','state6','state7','state8','state9','state10'])->all()
        );

        DB::table('egcu_friend_age_patterns')->updateOrInsert(
            ['pisaySystemID' => $pid, 'levelid' => $lvl],
            ['fr_age' => $data['fr_age'] ?? null, 'fr_older' => $data['fr_older'] ?? null, 'fr_younger' => $data['fr_younger'] ?? null]
        );

        DB::table('egcu_friend_group_patterns')->updateOrInsert(
            ['pisaySystemID' => $pid, 'levelid' => $lvl],
            ['fr_boys' => $data['fr_boys'] ?? null, 'fr_girls' => $data['fr_girls'] ?? null, 'fr_otherschool' => $data['fr_otherschool'] ?? null, 'fr_fromthischool' => $data['fr_fromthischool'] ?? null]
        );

        DB::table('egcu_bestfriends')->where('pisaySystemID', $pid)->where('levelid', $lvl)->delete();
        foreach (array_filter($data['friends'] ?? []) as $name) {
            DB::table('egcu_bestfriends')->insert(['pisaySystemID' => $pid, 'levelid' => $lvl, 'fr_name' => $name]);
        }

        DB::table('egcu_talents')->where('pisaySystemID', $pid)->where('levelid', $lvl)->delete();
        foreach (array_filter($data['talents'] ?? []) as $talent) {
            DB::table('egcu_talents')->insert(['pisaySystemID' => $pid, 'levelid' => $lvl, 'talent' => $talent]);
        }
    }

    private function saveCareer(Request $request, string $pid, int $lvl): void
    {
        $data = $request->validate([
            '1stchoice' => ['nullable', 'string', 'max:300'],
            '2ndchoice' => ['nullable', 'string', 'max:300'],
        ]);

        DB::table('egcu_vocational')->updateOrInsert(
            ['pisaySystemID' => $pid, 'levelid' => $lvl],
            $data
        );
    }

    private function saveResidence(Request $request, string $pid, int $lvl): void
    {
        $data = $request->validate([
            'residencetype'  => ['nullable', 'string', 'max:50'],
            'transportation' => ['nullable', 'string', 'max:50'],
        ]);

        DB::table('egcu_residence')->updateOrInsert(
            ['pisaySystemID' => $pid, 'levelid' => $lvl],
            array_merge($data, ['date_filed' => now()->format('Y-m-d H:i:sa'), 'syid' => 0])
        );
    }

    private function saveHealth(Request $request, string $pid, int $lvl): void
    {
        $data = $request->validate([
            'weight'          => ['nullable', 'string', 'max:20'],
            'height'          => ['nullable', 'string', 'max:20'],
            'sight'           => ['nullable', 'string', 'max:3'],
            'hearing'         => ['nullable', 'string', 'max:3'],
            'speech'          => ['nullable', 'string', 'max:3'],
            'general_health'  => ['nullable', 'string', 'max:100'],
            'ailment'         => ['nullable', 'string', 'max:3'],
            'ailment_remarks' => ['nullable', 'string', 'max:100'],
        ]);

        DB::table('egcu_health')->updateOrInsert(
            ['pisaySystemID' => $pid, 'levelid' => $lvl],
            array_merge($data, ['date_filed' => now()->format('Y-m-d H:i:sa'), 'syid' => 0])
        );
    }
}
