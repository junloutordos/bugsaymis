<?php

namespace App\Console\Commands\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Designation;
use App\Models\FacultyLoading\DesignationCategory;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\ResearchAdvisory;
use App\Models\User;
use App\Services\FacultyLoading\LoadComputationService;
use Illuminate\Console\Command;

class ImportResearchAdvisories extends Command
{
    protected $signature = 'faculty-loading:import-research
                            {--term-id= : Academic term ID (defaults to current term)}
                            {--by= : User ID for created_by (defaults to first Administrator)}
                            {--execute : Commit inserts — omit for dry-run}';

    protected $description = 'Bulk-import research advisory groups. Dry-run by default; pass --execute to commit.';

    // [research_title, load_units, faculty_name]
    // Grade level is inferred from the title prefix.
    // "New *" faculty names are skipped (vacant groups).
    private const DATA = [
        // ── Grade 10 ──────────────────────────────────────────────────────────
        ['GRADE 10 GROUP 1-2',   1,   'Baje, Benito A.'],
        ['GRADE 10 GROUP 3-4',   1,   'Nuñez, Nerry C.'],
        ['GRADE 10 GROUP 5-6',   1,   'Lozano, Liezl Mae B.'],
        ['GRADE 10 GROUP 7-8',   1,   'Alerta, Gilbert'],
        ['GRADE 10 GROUP 9-10',  1,   'Payao, Loida Mae U.'],
        ['GRADE 10 GROUP 11-12', 1,   'Sanchez, Jecelyn E.'],
        ['GRADE 10 GROUP 13-14', 1,   'New Chemistry'],       // vacant — skipped
        ['GRADE 10 GROUP 15-16', 1,   'Yamit, Marian Mae M.'],
        ['GRADE 10 GROUP 17-18', 1,   'Penados, Jhon Michael'],
        ['GRADE 10 GROUP 19-20', 1,   'Ramayla, Sherry P.'],
        ['GRADE 10 GROUP 21-22', 1,   'Bermoy, Lyndon R.'],
        ['GRADE 10 GROUP 23-24', 1,   'Fernando, Chardy C.'],
        ['GRADE 10 GROUP 25-26', 1,   'Francisco, Rotchie Glen A.'],
        ['GRADE 10 GROUP 27-28', 1,   'Segundino, Ken Wood L.'],
        ['GRADE 10 GROUP 29-30', 1,   'Fernando, Michelle B.'],
        ['GRADE 10 GROUP 31-32', 1,   'Tarre, Merriam Danielle G.'],
        ['GRADE 10 GROUP 33-34', 1,   'Fernando, Alvin C.'],
        ['GRADE 10 GROUP 35',    0.5, 'Salvan, Vendy Von P.'],
        ['GRADE 10 GROUP 36-37', 1,   'Ganzon, Mary Ann M.'],
        // ── Grade 11 ──────────────────────────────────────────────────────────
        ['Grade 11 GROUP 1-2',   2,   'Gumapac, Jasmine S.'],
        ['Grade 11 GROUP 3-4',   2,   'Galeon, Jessevim R.'],
        ['Grade 11 GROUP 5-6',   2,   'Payao, Loida Mae U.'],
        ['Grade 11 GROUP 7-8',   2,   'Baloria, Noah T.'],
        ['Grade 11 GROUP 9-10',  2,   'Grado, JL-Joshua B.'],
        ['Grade 11 GROUP 11-12', 2,   'Galliguez, Ethel M.'],
        ['Grade 11 GROUP 13-14', 2,   'Almocera, Divine Faith G.'],
        ['Grade 11 GROUP 15-16', 2,   'Llido, Deborah Gwen G.'],
        ['Grade 11 GROUP 17-18', 2,   'Dumaicos, Aviel Sheen V.'],
        ['Grade 11 GROUP 19-20', 2,   'Salvan, Vendy Von P.'],
        ['Grade 11 GROUP 21-22', 2,   'Boniel, Charles Daniel'],
        ['Grade 11 GROUP 23-24', 2,   'Empuesto, Gretchen Mae B.'],
        ['Grade 11 GROUP 25-26', 2,   'Maique, Jay M.'],
        ['Grade 11 GROUP 27-28', 2,   'Sanchez, Jecelyn E.'],
        ['Grade 11 GROUP 29-30', 2,   'Pescueso, Brigette Ursula L.'],
        ['Grade 11 GROUP 31-32', 2,   'Lozano, Liezl Mae B.'],
        ['Grade 11 GROUP 33-34', 2,   'Mordeno, Patricia Therese M.'],
        ['Grade 11 GROUP 35-36', 2,   'Baje, Benito A.'],
        ['Grade 11 GROUP 37',    1,   'Dumaicos, Aviel Sheen V.'],
        ['Grade 11 GROUP 38',    1,   'Alerta, Gilbert'],
        ['Grade 11 GROUP 39',    1,   'Galeon, Jessevim R.'],
        // ── Grade 12 ──────────────────────────────────────────────────────────
        ['Grade 12 GROUP 1-2',   2,   'Dumaicos, Aviel Sheen V.'],
        ['Grade 12 GROUP 3-4',   2,   'Galliguez, Ethel M.'],
        ['Grade 12 GROUP 5-6',   2,   'Penados, Jhon Michael'],
        ['Grade 12 GROUP 7-8',   2,   'Ramayla, Sherry P.'],
        ['Grade 12 GROUP 9-10',  2,   'Perocho, Jayryn J.'],
        ['Grade 12 GROUP 11-12', 2,   'Llido, Deborah Gwen G.'],
        ['Grade 12 GROUP 13-14', 2,   'Sanchez, Jecelyn E.'],
        ['Grade 12 GROUP 15-16', 2,   'Grado, JL-Joshua B.'],
        ['Grade 12 GROUP 17-18', 2,   'Nuñez, Nerry C.'],
        ['Grade 12 GROUP 19-20', 2,   'Empuesto, Gretchen Mae B.'],
        ['Grade 12 GROUP 21',    1,   'Galliguez, Ethel M.'],
        ['Grade 12 GROUP 22',    1,   'Ganzon, Mary Ann M.'],
        ['Grade 12 GROUP 23-24', 2,   'Fernando, Michelle B.'],
        ['Grade 12 GROUP 25-26', 2,   'Yamit, Marian Mae M.'],
        ['Grade 12 GROUP 27-28', 2,   'Tarre, Merriam Danielle G.'],
        ['Grade 12 GROUP 29',    1,   'Alcando, Darrell'],
        ['Grade 12 GROUP 30',    1,   'Boniel, Charles Daniel'],
    ];

    public function __construct(private readonly LoadComputationService $loads)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');

        // Resolve term
        $termId = $this->option('term-id');
        $term   = $termId
            ? AcademicTerm::find((int) $termId)
            : AcademicTerm::where('is_current', true)->first();

        if (! $term) {
            $this->error('No academic term found. Pass --term-id=<id>.');
            $this->listTerms();
            return self::FAILURE;
        }

        $this->info("Term   : [{$term->id}] {$term->name} (school_year_id={$term->school_year_id})");
        $this->info('Mode   : ' . ($execute ? 'EXECUTE — changes will be committed' : 'DRY RUN — no changes'));
        $this->line('');

        // Resolve created_by
        $byOption  = $this->option('by');
        $createdBy = $byOption
            ? (int) $byOption
            : User::whereHas('roles', fn ($q) => $q->where('roles.name', 'Administrator'))->value('id')
              ?? User::first()?->id;

        if (! $createdBy) {
            $this->error('Cannot determine created_by user. Pass --by=<user_id>.');
            return self::FAILURE;
        }

        $inserted  = 0;
        $skipped   = 0;
        $errors    = [];

        // Group rows by (user key, grade_level) so we can call recomputeGradeAssignment
        // once per pair after all inserts, rather than on every single row.
        $gradeRecompute = []; // keyed by "userId:gradeLevel"

        foreach (self::DATA as [$title, $units, $facultyName]) {
            // Skip vacant placeholders
            if (str_starts_with($facultyName, 'New ')) {
                $this->line("  SKIP (vacant)   : {$title}");
                $skipped++;
                continue;
            }

            $gradeLevel = $this->inferGradeLevel($title);
            $user       = $this->resolveUser($facultyName);

            if (! $user) {
                $errors[] = "NO USER MATCH: {$facultyName} → {$title}";
                $this->warn("  UNMATCHED USER  : {$facultyName}");
                continue;
            }

            // Idempotency check
            $exists = ResearchAdvisory::where('user_id', $user->id)
                ->where('academic_term_id', $term->id)
                ->where('research_title', $title)
                ->exists();

            if ($exists) {
                $this->line("  SKIP (exists)   : {$user->name} | {$title}");
                $skipped++;
                continue;
            }

            if (! $execute) {
                $this->line(sprintf(
                    '  [DRY] %-35s | G%-2d | %4s units | %s',
                    $title, $gradeLevel, $units, $user->name
                ));
                $inserted++;
                continue;
            }

            ResearchAdvisory::create([
                'user_id'          => $user->id,
                'school_year_id'   => $term->school_year_id,
                'academic_term_id' => $term->id,
                'research_title'   => $title,
                'grade_level'      => $gradeLevel,
                'advisory_role'    => 'lead',
                'research_type'    => 'science_research',
                'load_units'       => (float) $units,
                'status'           => 'active',
                'load_assignment_id' => null,
            ]);

            $this->line("  CREATED         : {$user->name} | {$title} | {$units} units");
            $inserted++;

            // Queue for post-insert recompute
            $key = "{$user->id}:{$gradeLevel}";
            $gradeRecompute[$key] = ['user_id' => $user->id, 'grade_level' => $gradeLevel];
        }

        // After all inserts: recompute RES-GX load assignments and sync totals
        if ($execute && count($gradeRecompute) > 0) {
            $this->line('');
            $this->info('Recomputing grade-level load assignments...');

            // Collect unique faculty IDs for final syncLoad pass
            $facultyIds = [];

            foreach ($gradeRecompute as ['user_id' => $userId, 'grade_level' => $grade]) {
                $load = $this->loads->findOrCreateFacultyLoad($userId, $term->school_year_id, $term->id);
                $la   = $this->recomputeGradeAssignment($userId, $term->id, $grade, $load, $createdBy);
                $this->line("  RES-G{$grade} → user_id={$userId} | la_id=" . ($la?->id ?? 'deleted'));

                // Link each advisory to its load_assignment
                if ($la) {
                    ResearchAdvisory::where('user_id', $userId)
                        ->where('academic_term_id', $term->id)
                        ->where('grade_level', $grade)
                        ->where('status', 'active')
                        ->update(['load_assignment_id' => $la->id]);
                }

                $facultyIds[$userId] = $load;
            }

            $this->line('');
            $this->info('Syncing faculty load totals...');
            foreach ($facultyIds as $load) {
                $this->loads->syncLoad($load->fresh());
            }
        }

        $this->line('');
        $this->info("Inserted: {$inserted} | Skipped: {$skipped} | Errors: " . count($errors));

        foreach ($errors as $e) {
            $this->error($e);
        }

        return count($errors) > 0 ? self::FAILURE : self::SUCCESS;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function inferGradeLevel(string $title): int
    {
        if (stripos($title, 'GRADE 10') !== false) return 10;
        if (stripos($title, 'Grade 11') !== false) return 11;
        if (stripos($title, 'Grade 12') !== false) return 12;
        return 0;
    }

    private function resolveUser(string $rawName): ?User
    {
        $parts     = explode(', ', $rawName, 2);
        $lastName  = $this->normalizeSpecialChars(trim($parts[0]));
        $rest      = trim($parts[1] ?? '');
        $firstName = $rest ? $this->normalizeSpecialChars(explode(' ', $rest)[0]) : '';

        $query = User::where('name', 'like', "%{$lastName}%");
        if ($firstName) {
            $query->where('name', 'like', "%{$firstName}%");
        }

        $candidates = $query->get();

        if ($candidates->count() === 1) return $candidates->first();
        if ($candidates->count() > 1)  return $candidates->first();

        // Fallback: last name only
        return User::where('name', 'like', "%{$lastName}%")->first();
    }

    private function normalizeSpecialChars(string $value): string
    {
        return str_replace(
            ['√±', '√©', '√¡', '√≥', '√∫'],
            ['ñ',  'é',  'á',  'ó',  'ú'],
            $value
        );
    }

    /**
     * Mirrors ResearchAdvisoryController::recomputeGradeAssignment().
     * Upserts the RES-G{grade} LoadAssignment with the sum of all active
     * advisory units for that faculty + grade + term.
     */
    private function recomputeGradeAssignment(
        int $userId,
        int $termId,
        int $gradeLevel,
        FacultyLoad $load,
        int $createdBy
    ): ?LoadAssignment {
        $designation = Designation::firstOrCreate(
            ['code' => "RES-G{$gradeLevel}"],
            [
                'designation_category_id' => DesignationCategory::firstOrCreate(
                    ['code' => 'RESEARCH'],
                    ['name' => 'Research Advisory', 'description' => 'Research group and thesis advisory roles', 'sort_order' => 8, 'is_active' => true]
                )->id,
                'name'            => "Research Advisory — Grade {$gradeLevel}",
                'assignment_type' => 'research',
                'load_units'      => 0,
                'requires_unit'   => false,
                'is_active'       => true,
                'sort_order'      => $gradeLevel,
            ]
        );

        $total = (float) ResearchAdvisory::where('user_id', $userId)
            ->where('academic_term_id', $termId)
            ->where('grade_level', $gradeLevel)
            ->where('status', 'active')
            ->sum('load_units');

        $la = LoadAssignment::where('faculty_load_id', $load->id)
            ->where('designation_id', $designation->id)
            ->first();

        if ($total <= 0) {
            $la?->delete();
            return null;
        }

        if ($la) {
            $la->update(['load_units' => $total]);
        } else {
            $la = LoadAssignment::create([
                'faculty_load_id'  => $load->id,
                'user_id'          => $userId,
                'school_year_id'   => $load->school_year_id,
                'academic_term_id' => $termId,
                'designation_id'   => $designation->id,
                'assignment_type'  => 'research',
                'load_units'       => $total,
                'description'      => "Grade {$gradeLevel} Research Advisory",
                'is_overridden'    => true,
                'created_by'       => $createdBy,
            ]);
        }

        return $la;
    }

    private function listTerms(): void
    {
        $this->line('Available academic terms:');
        AcademicTerm::with('schoolYear')->get()->each(function (AcademicTerm $t) {
            $sy = $t->schoolYear?->name ?? '?';
            $this->line("  [{$t->id}] {$t->name} — SY {$sy} | is_current={$t->is_current}");
        });
    }
}
