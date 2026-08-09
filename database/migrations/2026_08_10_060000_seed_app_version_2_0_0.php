<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $features = [
            'Learn — a full Learning Management System: course pages resolved live from each teacher\'s Faculty Loading assignments, modules with a publish/draft workflow, pages, private S3-backed file attachments, and course announcements',
            'Learn Assignments — text, file, and link submissions with resubmission until graded; optional rubric-based grading with a reusable, personal rubric bank',
            'Learn Quizzes — multiple choice, true/false, multiple select, short answer, and essay questions; instructor-set attempt limits with highest-score-wins; optional per-quiz time limits; question randomization (draw N of M) with shuffled questions and choices; LaTeX/math rendering in question prompts; a reusable question bank; full item-analysis and course-trend analytics',
            'Learn Discussions — fully threaded, unlimited-depth discussion boards per course with optional participation grading and instructor moderation',
            'Learn Class Record integration — assignment, quiz, and discussion scores can be linked to and pushed directly into existing Class Record (Weekly Assessment Tracker) assessments without ever creating or rescheduling one',
            'Learn Student Portal — dedicated views for enrolled students to browse course content, submit assignments, take quizzes, and participate in discussions from their own portal',
        ];

        $fixes = [
            'Learn file uploads now reject non-allowlisted file types instead of accepting anything',
            'Learn assignment link submissions now block javascript: and other unsafe URL schemes',
            'Fixed a Learn quiz validation gap that let an answer be checked against another question\'s options',
            'Fixed Learn course pages sometimes misclassifying an assignment as the wrong item type',
        ];

        DB::table('app_versions')->update(['is_current' => false]);
        DB::table('app_versions')->updateOrInsert(
            ['version' => '2.0.0'],
            [
                'date' => '2026-08-10',
                'remarks' => 'Version 2.0.0 — Learn: a comprehensive, Canvas/Blackboard-style Learning Management System wired directly into Faculty Loading and Class Record.',
                'changes' => json_encode(['features' => $features, 'fixes' => $fixes, 'improvements' => []]),
                'is_current' => true,
                'is_visible' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('app_versions')->where('version', '2.0.0')->delete();
        DB::table('app_versions')->where('version', '1.0.0')->update(['is_current' => true]);
    }
};
