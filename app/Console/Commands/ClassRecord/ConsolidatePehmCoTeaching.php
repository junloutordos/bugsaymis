<?php

namespace App\Console\Commands\ClassRecord;

use App\Models\ClassRecord\GradingCategory;
use App\Models\FacultyLoading\Subject;
use Illuminate\Console\Command;

class ConsolidatePehmCoTeaching extends Command
{
    protected $signature = 'class-record:consolidate-pehm-coteaching {--commit : Actually write changes; without this flag the command only reports what it would do}';

    protected $description = 'One-off data fix: split shared PEHM grading-option templates per grade level and consolidate each section\'s separate PEHM class records into one shared co-teacher record.';

    public function handle(): int
    {
        $this->info($this->option('commit') ? 'RUNNING IN COMMIT MODE — writes will be made.' : 'DRY RUN — no writes will be made. Pass --commit to apply.');

        // Populated by later tasks.
        return self::SUCCESS;
    }

    /**
     * Detects which PEHM subject (PE/Health/Music) a leaf grading category
     * represents, by matching the literal "(PE)"/"(Health)"/"(Music)"
     * substring every real production leaf name carries. Returns null
     * (never a guess) for parent/summary categories or anything unmatched.
     */
    public function roleForCategory(GradingCategory $category): ?string
    {
        return match (true) {
            str_contains($category->name, '(PE)') => 'PE',
            str_contains($category->name, '(Health)') => 'Health',
            str_contains($category->name, '(Music)') => 'Music',
            default => null,
        };
    }

    /** "SA 1" -> "SA", "FA 2" -> "FA", "SA" -> "SA". */
    public function stripCode(string $code): string
    {
        return trim(preg_replace('/\s*\d+$/', '', $code));
    }

    /**
     * @return array<int,array<string,int>> grade_level => ['PE' => subject_id, 'Health' => ..., 'Music' => ...]
     */
    public function subjectsByGradeAndRole(): array
    {
        $subjects = Subject::where('subject_group', 'PEHM')->get(['id', 'name', 'grade_level']);

        $map = [];
        foreach ($subjects as $subject) {
            $role = match (true) {
                str_starts_with($subject->name, 'Health') => 'Health',
                str_starts_with($subject->name, 'Music') => 'Music',
                str_starts_with($subject->name, 'Physical Education') => 'PE',
                default => null,
            };

            if ($role === null) {
                continue;
            }

            $map[$subject->grade_level][$role] = $subject->id;
        }

        return $map;
    }
}
