<?php

namespace App\Console\Commands\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use App\Services\FacultyLoading\LoadComputationService;
use Illuminate\Console\Command;

class ImportTeachingLoads extends Command
{
    protected $signature = 'faculty-loading:import-teaching
                            {--term-id= : Academic term ID (defaults to current term)}
                            {--by= : User ID for created_by (defaults to first Administrator)}
                            {--execute : Commit inserts — omit for dry-run}';

    protected $description = 'Bulk-import teaching load assignments. Dry-run by default.';

    // ── Data ─────────────────────────────────────────────────────────────────
    // [faculty_name, load_units, subject_code]
    //
    // "New *" entries are omitted — vacant positions, no user to assign.
    // Cross-grade G11 & 12 electives with 10 total units are pre-split into
    // two rows of 5u each (G11 + G12 subject variants).
    // Cross-grade G11 & 12 electives with 5 total units are assigned to the
    // G11 subject variant (one mixed class).
    // ─────────────────────────────────────────────────────────────────────────
    private const DATA = [
        // ── AdTech ───────────────────────────────────────────────────────────
        ['Grado, JL-Joshua B.',          6,  'AT1-G7'],
        ['Nuñez, Nerry C.',              6,  'AT1-G7'],
        ['Sanchez, Jecelyn E.',         12,  'AT2-G8'],
        // ── Biology ──────────────────────────────────────────────────────────
        ['Galliguez, Ethel M.',         12,  'BIO1-G8'],
        ['Maique, Jay M.',              16,  'BIO2-G9'],
        ['Alcando, Darrell',            12,  'BIO2-G10'],
        ['Penados, Jhon Michael',        5,  'BIO3L2-G11'],   // Class 1
        ['Maique, Jay M.',               5,  'BIO3L2-G11'],   // Class 2
        ['Alcando, Darrell',             5,  'BIO3L2-G11'],   // Class 3
        ['Ganzon, Mary Ann M.',          5,  'BIO4L2-G12'],   // Class 1
        ['Ganzon, Mary Ann M.',          5,  'BIO4L2-G12'],   // Class 2
        // ── Chemistry ────────────────────────────────────────────────────────
        ['Baloria, Noah T.',            12,  'CHEM1-G8'],
        ['Perocho, Jayryn J.',          16,  'CHEM2-G9'],
        ['Lozano, Liezl Mae B.',        12,  'CHEM2-G10'],
        // Chemistry 3 (Level 2) G11 → New Chemistry — omitted
        ['Baloria, Noah T.',             5,  'CHEM4L2-G12'],  // Class 1
        ['Yamit, Marian Mae M.',         5,  'CHEM4L2-G12'],  // Class 2
        // ── Computer Science ─────────────────────────────────────────────────
        ['Payao, Loida Mae U.',         12,  'CS1-G7'],
        ['Galeon, Jessevim R.',         12,  'CS2-G8'],
        ['Fernando, Alvin C.',          12,  'CS3-G9'],
        ['Fernando, Chardy C.',         12,  'CS4-G10'],
        // ── Earth Science ────────────────────────────────────────────────────
        // Earth Science (G7) → New Physics — omitted
        ['Francisco, Rotchie Glen A.',  12,  'ES-G8'],
        // ── Electives — AYP (G10) ────────────────────────────────────────────
        ['Alerta, Gilbert',              3,  'EL-DS-G10'],
        ['Penados, Jhon Michael',        3,  'EL-MBMT-G10'],
        ['Ramayla, Sherry P.',           3,  'EL-FST-G10'],
        ['Bermoy, Lyndon R.',            3,  'EL-DMIOT-G10'],
        ['Baje, Benito A.',              3,  'EL-IPR-G10'],
        ['Alcando, Darrell',             3,  'EL-PB-G10'],
        // ── Electives — G11 & 12 (10u split into 5+5) ────────────────────────
        ['Nuñez, Nerry C.',              5,  'EL-AGR-G11'],
        ['Nuñez, Nerry C.',              5,  'EL-AGR-G12'],
        ['Grado, JL-Joshua B.',          5,  'EL-ENG-G11'],
        ['Grado, JL-Joshua B.',          5,  'EL-ENG-G12'],
        ['Bermoy, Lyndon R.',            5,  'EL-DMT-G11'],
        ['Bermoy, Lyndon R.',            5,  'EL-DMT-G12'],
        // ── Electives — G11 & 12 (5u, single mixed class → G11 subject) ──────
        ['Penados, Jhon Michael',        5,  'EL-BIO3-G11'],
        ['Yamit, Marian Mae M.',         5,  'EL-CHEM4-G11'],
        ['Fernando, Chardy C.',          5,  'EL-CS5-G11'],
        // ── Electives — single grade ─────────────────────────────────────────
        ['Ganzon, Mary Ann M.',          5,  'EL-BIO4-G12'],
        ['Lozano, Liezl Mae B.',         5,  'EL-CHEM3-G11'],
        // Elective: Chemistry 3 G12 → New Chemistry — omitted
        ['Baje, Benito A.',              5,  'EL-PHY3-G11'],
        // ── English ──────────────────────────────────────────────────────────
        ['Subla, Lily Dale M.',         12,  'EN1-G7'],
        // English 1 (4u) G7 → New English — omitted
        ['Ahon, Vonna Vejle V.',        16,  'EN2-G8'],
        // English 3 G9 → New English — omitted
        ['Garcia, Kenneth Jeason B.',   12,  'EN4-G10'],
        ['Bohol, Jan Grenechaux V.',     9,  'EN5-G11'],
        ['Ahon, Vonna Vejle V.',         3,  'EN6-G12'],
        ['Bohol, Jan Grenechaux V.',     6,  'EN6-G12'],
        // ── Filipino ─────────────────────────────────────────────────────────
        ['Asilom, Baby Jean P.',         6,  'FIL1-G7'],
        ['Dechusa, John Ridan D.',       3,  'FIL1-G7'],
        ['Mahinay, Nikki Lou L.',        3,  'FIL1-G7'],
        ['Mordeno, Yvonne M.',          12,  'FIL2-G8'],
        ['Mahinay, Nikki Lou L.',       12,  'FIL3-G9'],
        ['Cuadrazal, Rea Frechie C.',   12,  'FIL4-G10'],
        ['Asilom, Baby Jean P.',         9,  'FIL5-G11'],
        ['Dechusa, John Ridan D.',       6,  'FIL6-G12'],
        ['Mordeno, Yvonne M.',           3,  'FIL6-G12'],
        // ── Health ───────────────────────────────────────────────────────────
        ['Valencia, Ma.Riza F.',         4,  'HLT1-G7'],
        ['Fulay, Louren P.',             4,  'HLT2-G8'],
        ['Valencia, Ma.Riza F.',         4,  'HLT3-G9'],
        ['Gumapac, Jasmine S.',          4,  'HLT4-G10'],
        // ── Integrated Science ────────────────────────────────────────────────
        ['Francisco, Rotchie Glen A.',   5,  'ISCI1-G7'],
        ['Hijastro, Jhon Ryan P.',      15,  'ISCI1-G7'],
        // ── Mathematics ──────────────────────────────────────────────────────
        ['Almocera, Divine Faith G.',   15,  'MATH1-G7'],
        ['Pescueso, Brigette Ursula L.', 5,  'MATH1-G7'],
        ['Altar, Daisyre Mae G.',       12,  'MATH2A-G8'],
        ['Salvan, Vendy Von P.',         9,  'MATH2B-G8'],
        ['Mordeno, Patricia Therese M.', 3,  'MATH2B-G8'],
        ['Pescueso, Brigette Ursula L.',12,  'MATH3-G9'],
        ['Boniel, Charles Daniel',      16,  'MATH4-G10'],
        ['Mordeno, Patricia Therese M.', 9,  'MATH5L1-G11'],
        ['Mordeno, Patricia Therese M.', 3,  'MATH5L2-G11'],
        ['Empuesto, Gretchen Mae B.',    6,  'MATH6L1-G12'],
        ['Empuesto, Gretchen Mae B.',    3,  'MATH6L2-G12'],
        // ── Music ────────────────────────────────────────────────────────────
        ['Garrido, Hannah Elizabeth P.', 4,  'MUS1-G7'],
        ['Garrido, Hannah Elizabeth P.', 4,  'MUS2-G8'],
        ['Garrido, Hannah Elizabeth P.', 4,  'MUS3-G9'],
        ['Garrido, Hannah Elizabeth P.', 4,  'MUS4-G10'],
        // ── Physical Education ────────────────────────────────────────────────
        ['Fulay, Louren P.',             4,  'PE1-G7'],
        ['Fulay, Louren P.',             4,  'PE2-G8'],
        ['Valencia, Ma.Riza F.',         4,  'PE3-G9'],
        ['Fulay, Louren P.',             2,  'PE4-G10'],
        ['Valencia, Ma.Riza F.',         2,  'PE4-G10'],
        // ── Physics ──────────────────────────────────────────────────────────
        ['Segundino, Ken Wood L.',      12,  'PHY1-G8'],
        ['Llido, Deborah Gwen G.',      16,  'PHY2-G9'],
        ['Dumaicos, Aviel Sheen V.',    12,  'PHY2-G10'],
        ['Baje, Benito A.',              5,  'PHY3L2-G11'],
        ['Dumaicos, Aviel Sheen V.',     5,  'PHY4L2-G12'],
        // ── Social Science ────────────────────────────────────────────────────
        ['Orbegoso, Jenny B.',          12,  'SS1-G7'],
        ['Salang, Keith R.',            12,  'SS2-G8'],
        ['Villareiz, Jestoni M.',        6,  'SS3-G9'],
        ['Bernales, Sidney Marie S.',    6,  'SS3-G9'],
        ['Bernales, Sidney Marie S.',   12,  'SS4-G10'],
        ['Villareiz, Jestoni M.',        9,  'SS5-G11'],
        ['Abamonga, Maricris C.',        9,  'SS6-G12'],
        // ── Statistics ───────────────────────────────────────────────────────
        ['Alerta, Gilbert',             12,  'STAT1-G9'],
        // ── STEM Research ────────────────────────────────────────────────────
        // STEM Research 1 (8u) G10 → New Chemistry — omitted
        ['Ganzon, Mary Ann M.',          4,  'STEMR1-G10'],
        ['Ramayla, Sherry P.',           9,  'STEMR2-G11'],
        ['Fernando, Michelle B.',        3,  'STEMR3-G12'],
        // STEM Research 3 (6u) G12 → New Physics — omitted
        // ── Values Education ─────────────────────────────────────────────────
        ['Morales, Shriegley Mae R.',    6,  'VE1-G7'],
        ['Valencia, Ma.Riza F.',         2,  'VE1-G7'],
        ['Sionosa, Glenn M.',            8,  'VE2-G8'],
        ['Sionosa, Glenn M.',            8,  'VE3-G9'],
        ['Morales, Shriegley Mae R.',    8,  'VE4-G10'],
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
            AcademicTerm::with('schoolYear')->get()->each(
                fn ($t) => $this->line("  [{$t->id}] {$t->name} — SY {$t->schoolYear?->name}")
            );
            return self::FAILURE;
        }

        $this->info("Term   : [{$term->id}] {$term->name} (school_year_id={$term->school_year_id})");
        $this->info('Mode   : ' . ($execute ? 'EXECUTE — changes will be committed' : 'DRY RUN — no changes'));
        $this->line('');

        // Resolve created_by
        $createdBy = $this->option('by')
            ? (int) $this->option('by')
            : User::whereHas('roles', fn ($q) => $q->where('roles.name', 'Administrator'))->value('id')
              ?? User::first()?->id;

        if (! $createdBy) {
            $this->error('Cannot determine created_by. Pass --by=<user_id>.');
            return self::FAILURE;
        }

        // Pre-load subjects by code for the current SY
        $subjects = Subject::where('school_year_id', $term->school_year_id)
            ->get()
            ->keyBy('code');

        // Pre-load existing teaching assignments into a bag for idempotency
        // key: "{user_id}:{subject_id}", value: remaining count to skip
        $existingBag = [];
        if ($execute) {
            LoadAssignment::where('academic_term_id', $term->id)
                ->where('assignment_type', 'teaching')
                ->select('user_id', 'subject_id')
                ->get()
                ->each(function ($la) use (&$existingBag) {
                    $key = "{$la->user_id}:{$la->subject_id}";
                    $existingBag[$key] = ($existingBag[$key] ?? 0) + 1;
                });
        }

        $inserted    = 0;
        $skipped     = 0;
        $errors      = [];
        $affectedLoads = []; // userId => FacultyLoad

        foreach (self::DATA as [$facultyName, $units, $subjectCode]) {
            // Resolve subject
            $subject = $subjects->get($subjectCode);
            if (! $subject) {
                $errors[] = "SUBJECT NOT FOUND: {$subjectCode} (for {$facultyName})";
                $this->error("  MISSING SUBJECT : [{$subjectCode}]");
                continue;
            }

            // Resolve user
            $user = $this->resolveUser($facultyName);
            if (! $user) {
                $errors[] = "NO USER MATCH: {$facultyName}";
                $this->warn("  UNMATCHED USER  : {$facultyName}");
                continue;
            }

            // Idempotency: check existing bag
            $bagKey = "{$user->id}:{$subject->id}";
            if (($existingBag[$bagKey] ?? 0) > 0) {
                $existingBag[$bagKey]--;
                $this->line("  SKIP (exists)   : {$user->name} | [{$subjectCode}] | {$units}u");
                $skipped++;
                continue;
            }

            if (! $execute) {
                $this->line(sprintf(
                    '  [DRY] %-35s | %-14s | %4su | %s',
                    $user->name, $subjectCode, $units, $subject->name
                ));
                $inserted++;
                continue;
            }

            $load = $this->loads->findOrCreateFacultyLoad($user->id, $term->school_year_id, $term->id);

            LoadAssignment::create([
                'faculty_load_id'  => $load->id,
                'user_id'          => $user->id,
                'school_year_id'   => $term->school_year_id,
                'academic_term_id' => $term->id,
                'assignment_type'  => 'teaching',
                'subject_id'       => $subject->id,
                'load_units'       => (float) $units,
                'created_by'       => $createdBy,
            ]);

            $this->line("  CREATED         : {$user->name} | [{$subjectCode}] | {$units}u");
            $affectedLoads[$user->id] = $load;
            $inserted++;
        }

        // Sync all affected faculty load totals
        if ($execute && count($affectedLoads) > 0) {
            $this->line('');
            $this->info('Syncing faculty load totals (' . count($affectedLoads) . ' faculty)...');
            foreach ($affectedLoads as $load) {
                $this->loads->syncLoad($load->fresh());
            }
            $this->line('');
            $this->info('Running recalculate-loads to finalize all totals...');
            $this->call('faculty-loading:recalculate-loads');
        }

        $this->line('');
        $this->info("Inserted: {$inserted} | Skipped: {$skipped} | Errors: " . count($errors));
        foreach ($errors as $e) {
            $this->error($e);
        }

        return count($errors) > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveUser(string $rawName): ?User
    {
        $parts     = explode(', ', $rawName, 2);
        $lastName  = $this->normalize(trim($parts[0]));
        $rest      = trim($parts[1] ?? '');
        $firstName = $rest ? $this->normalize(explode(' ', $rest)[0]) : '';

        $query = User::where('name', 'like', "%{$lastName}%");
        if ($firstName) {
            $query->where('name', 'like', "%{$firstName}%");
        }

        $candidates = $query->get();
        if ($candidates->count() === 1) return $candidates->first();
        if ($candidates->count() > 1)  return $candidates->first();

        return User::where('name', 'like', "%{$lastName}%")->first();
    }

    private function normalize(string $value): string
    {
        return str_replace(
            ['√±', '√©', '√¡', '√≥', '√∫'],
            ['ñ',  'é',  'á',  'ó',  'ú'],
            $value
        );
    }
}
