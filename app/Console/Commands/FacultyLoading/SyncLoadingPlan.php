<?php

namespace App\Console\Commands\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Designation;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\ResearchAdvisory;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use App\Services\FacultyLoading\HeadAdvisoryService;
use App\Services\FacultyLoading\LoadComputationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reconciles SY 2026-2027 faculty loading against the CID "Adjusted 18-unit Load"
 * plan (Temporary (On-going) PROPOSED FACULTY LOADING SY 2026-2027.xlsx, Jul 2026).
 *
 * Full mirror: creates, updates, reassigns AND deletes so the term's load
 * assignments match the plan exactly. Classes moved to vacant "New *" positions
 * are reassigned to the TBA (Vacant) user so timetables stay intact.
 *
 * Dry-run by default; pass --execute to commit (runs inside one transaction).
 */
class SyncLoadingPlan extends Command
{
    protected $signature = 'faculty-loading:sync-loading-plan
                            {--term-id= : Academic term ID (must be the current term; defaults to it)}
                            {--by= : User ID for created_by (defaults to first Administrator)}
                            {--execute : Commit changes — omit for dry-run}';

    protected $description = 'Reconcile SY 2026-2027 load assignments against the Adjusted 18-unit plan. Dry-run by default.';

    private const TBA_NAME = 'TBA (Vacant)';

    /** Ambiguous names pinned to an explicit user id (duplicate accounts in prod). */
    private const USER_ID_OVERRIDES = [
        'Fernando, Michelle B.' => 25, // id=4 is a duplicate; CID Chief account wins
    ];

    // ── Target: teaching — [faculty, subject_code, units] (per-teacher totals) ──
    private const TEACHING = [
        ['Abamonga, Maricris C.', 'SS6-G12', 9],
        ['Ahon, Vonna Vejle V.', 'EN2-G8', 16],
        ['Alcando, Darrell', 'BIO2-G10', 12],
        ['Alcando, Darrell', 'BIO3L2-G11', 5],
        ['Alerta, Gilbert', 'EL-DS-G10', 3],
        ['Alerta, Gilbert', 'STAT1-G9', 9],
        ['Almocera, Divine Faith G.', 'MATH1-G7', 10],
        ['Altar, Daisyre Mae G.', 'MATH2A-G8', 12],
        ['Asilom, Baby Jean P.', 'FIL1-G7', 12],
        ['Baje, Benito A.', 'EL-IPR-G10', 3],
        ['Baje, Benito A.', 'STEMR3-G12', 9],
        ['Baloria, Noah T.', 'CHEM1-G8', 12],
        ['Bermoy, Lyndon R.', 'EL-DMT-G11', 5],
        ['Bermoy, Lyndon R.', 'EL-DMT-G12', 5],
        ['Bohol, Jan Grenechaux V.', 'EN5-G11', 12],
        ['Boniel, Charles Daniel', 'MATH4-G10', 16],
        ['Cuadrazal, Rea Frechie C.', 'FIL4-G10', 12],
        ['Dechusa, John Ridan D.', 'FIL6-G12', 9],
        ['Dumaicos, Aviel Sheen V.', 'EL-PHY3-G11', 5],
        ['Dumaicos, Aviel Sheen V.', 'PHY4L2-G12', 5],
        ['Empuesto, Gretchen Mae B.', 'MATH6L1-G12', 6],
        ['Empuesto, Gretchen Mae B.', 'MATH6L2-G12', 3],
        ['Fernando, Alvin C.', 'CS3-G9', 12],
        ['Fernando, Chardy C.', 'CS4-G10', 12],
        ['Fernando, Chardy C.', 'EL-CS5-G11', 5],
        ['Francisco, Rotchie Glen A.', 'ISCI1-G7', 15],
        ['Fulay, Louren P.', 'HLT1-G7', 4],
        ['Fulay, Louren P.', 'PE1-G7', 4],
        ['Fulay, Louren P.', 'PE2-G8', 4],
        ['Galeon, Jessevim R.', 'CS2-G8', 12],
        ['Galliguez, Ethel M.', 'BIO1-G8', 12],
        ['Ganzon, Mary Ann M.', 'BIO4L2-G12', 5],
        ['Ganzon, Mary Ann M.', 'EL-BIO4-G12', 5],
        ['Garcia, Kenneth Jeason B.', 'EN3-G9', 12],
        ['Garrido, Hannah Elizabeth P.', 'MUS1-G7', 4],
        ['Garrido, Hannah Elizabeth P.', 'MUS2-G8', 4],
        ['Garrido, Hannah Elizabeth P.', 'MUS3-G9', 4],
        ['Garrido, Hannah Elizabeth P.', 'MUS4-G10', 4],
        ['Grado, JL-Joshua B.', 'AT1-G7', 3],
        ['Grado, JL-Joshua B.', 'EL-ENG-G11', 5],
        ['Grado, JL-Joshua B.', 'EL-ENG-G12', 5],
        ['Gumapac, Jasmine S.', 'HLT4-G10', 4],
        ['Hijastro, Jhon Ryan P.', 'ES-G7', 6],
        ['Hijastro, Jhon Ryan P.', 'ISCI1-G7', 5],
        ['Llido, Deborah Gwen G.', 'PHY2-G9', 16],
        ['Lozano, Liezl Mae B.', 'EL-CHEM3-G11', 5],
        ['Lozano, Liezl Mae B.', 'STEMR1-G10', 6],
        ['Mahinay, Nikki Lou L.', 'FIL3-G9', 12],
        ['Maique, Jay M.', 'BIO2-G9', 16],
        ['Morales, Shriegley Mae R.', 'VE1-G7', 8],
        ['Morales, Shriegley Mae R.', 'VE2-G8', 8],
        ['Mordeno, Patricia Therese M.', 'MATH5L1-G11', 9],
        ['Mordeno, Patricia Therese M.', 'MATH5L2-G11', 3],
        ['Mordeno, Yvonne M.', 'FIL2-G8', 12],
        ['Nuñez, Nerry C.', 'EL-AGR-G11', 5],
        ['Nuñez, Nerry C.', 'EL-AGR-G12', 5],
        ['Orbegoso, Jenny B.', 'SS1-G7', 12],
        ['Payao, Loida Mae U.', 'CS1-G7', 12],
        ['Penados, Jhon Michael', 'BIO3L2-G11', 5],
        ['Penados, Jhon Michael', 'EL-BIO3-G11', 5],
        ['Penados, Jhon Michael', 'EL-MBMT-G10', 3],
        ['Perocho, Jayryn J.', 'CHEM2-G9', 16],
        ['Pescueso, Brigette Ursula L.', 'MATH1-G7', 10],
        ['Pescueso, Brigette Ursula L.', 'MATH3-G9', 6],
        ['Ramayla, Sherry P.', 'EL-FST-G10', 3],
        ['Ramayla, Sherry P.', 'STEMR2-G11', 12],
        ['Salang, Keith R.', 'SS2-G8', 12],
        ['Salvan, Vendy Von P.', 'MATH2B-G8', 12],
        ['Sanchez, Jecelyn E.', 'AT2-G8', 12],
        ['Segundino, Ken Wood L.', 'PHY1-G8', 12],
        ['Sionosa, Glenn M.', 'VE3-G9', 8],
        ['Sionosa, Glenn M.', 'VE4-G10', 8],
        ['Subla, Lily Dale M.', 'EN1-G7', 12],
        ['TBA (Vacant)', 'AT1-G7', 9],
        ['TBA (Vacant)', 'BIO3L2-G11', 5],
        ['TBA (Vacant)', 'BIO4L2-G12', 5],
        ['TBA (Vacant)', 'CHEM2-G10', 12],
        ['TBA (Vacant)', 'CHEM3L2-G11', 5],
        ['TBA (Vacant)', 'EL-CHEM3-G12', 5],
        ['TBA (Vacant)', 'EL-DMIOT-G10', 3],
        ['TBA (Vacant)', 'EL-PB-G10', 3],
        ['TBA (Vacant)', 'EN1-G7', 4],
        ['TBA (Vacant)', 'EN4-G10', 12],
        ['TBA (Vacant)', 'EN6-G12', 9],
        ['TBA (Vacant)', 'ES-G7', 6],
        ['TBA (Vacant)', 'ES-G8', 12],
        ['TBA (Vacant)', 'FIL5-G11', 12],
        ['TBA (Vacant)', 'MATH3-G9', 6],
        ['TBA (Vacant)', 'PHY2-G10', 12],
        ['TBA (Vacant)', 'PHY3L2-G11', 5],
        ['TBA (Vacant)', 'SS3-G9', 6],
        ['TBA (Vacant)', 'SS4-G10', 12],
        ['TBA (Vacant)', 'STAT1-G9', 3],
        ['TBA (Vacant)', 'STEMR1-G10', 6],
        ['Valencia, Ma.Riza F.', 'HLT2-G8', 4],
        ['Valencia, Ma.Riza F.', 'HLT3-G9', 4],
        ['Valencia, Ma.Riza F.', 'PE3-G9', 4],
        ['Valencia, Ma.Riza F.', 'PE4-G10', 4],
        ['Villareiz, Jestoni M.', 'SS3-G9', 6],
        ['Villareiz, Jestoni M.', 'SS5-G11', 12],
        ['Yamit, Marian Mae M.', 'CHEM4L2-G12', 5],
        ['Yamit, Marian Mae M.', 'EL-CHEM4-G11', 5],
    ];

    // ── Target: research groups — [title, grade, units, adviser|null] ──────────
    // null adviser = vacant "New *" position or on study leave → group is dropped
    // if it currently exists. Groups active in DB but absent here are dropped too.
    private const RESEARCH = [
        ['GRADE 10 GROUP 1-2',   10, 1,   'Baje, Benito A.'],
        ['GRADE 10 GROUP 3-4',   10, 1,   null],
        ['GRADE 10 GROUP 5-6',   10, 1,   'Mordeno, Patricia Therese M.'],
        ['GRADE 10 GROUP 9-10',  10, 1,   'Payao, Loida Mae U.'],
        ['GRADE 10 GROUP 11-12', 10, 1,   'Baloria, Noah T.'],
        ['GRADE 10 GROUP 13-14', 10, 1,   null],
        ['GRADE 10 GROUP 15-16', 10, 1,   null],
        ['GRADE 10 GROUP 17-18', 10, 1,   'Hijastro, Jhon Ryan P.'],
        ['GRADE 10 GROUP 19-20', 10, 1,   'Ramayla, Sherry P.'],
        ['GRADE 10 GROUP 21-22', 10, 1,   'Bermoy, Lyndon R.'],
        ['GRADE 10 GROUP 23-24', 10, 1,   'Fernando, Chardy C.'],
        ['GRADE 10 GROUP 25-26', 10, 1,   'Francisco, Rotchie Glen A.'],
        ['GRADE 10 GROUP 27-28', 10, 1,   'Segundino, Ken Wood L.'],
        ['GRADE 10 GROUP 29-30', 10, 1,   'Fernando, Michelle B.'],
        ['GRADE 10 GROUP 31-32', 10, 1,   null],
        ['GRADE 10 GROUP 33-34', 10, 1,   'Bermoy, Lyndon R.'],
        ['GRADE 10 GROUP 35',    10, 0.5, 'Salvan, Vendy Von P.'],
        ['GRADE 10 GROUP 36-37', 10, 1,   'Ganzon, Mary Ann M.'],
        ['Grade 11 GROUP 1-2',   11, 2,   'Gumapac, Jasmine S.'],
        ['Grade 11 GROUP 3-4',   11, 2,   'Galeon, Jessevim R.'],
        ['Grade 11 GROUP 5-6',   11, 2,   'Payao, Loida Mae U.'],
        ['Grade 11 GROUP 7-8',   11, 2,   'Baloria, Noah T.'],
        ['Grade 11 GROUP 9-10',  11, 2,   'Grado, JL-Joshua B.'],
        ['Grade 11 GROUP 11-12', 11, 2,   null],
        ['Grade 11 GROUP 13-14', 11, 2,   'Almocera, Divine Faith G.'],
        ['Grade 11 GROUP 15-16', 11, 2,   'Llido, Deborah Gwen G.'],
        ['Grade 11 GROUP 17-18', 11, 2,   'Dumaicos, Aviel Sheen V.'],
        ['Grade 11 GROUP 19-20', 11, 2,   'Almocera, Divine Faith G.'],
        ['Grade 11 GROUP 21-22', 11, 2,   null],
        ['Grade 11 GROUP 23-24', 11, 2,   'Empuesto, Gretchen Mae B.'],
        ['Grade 11 GROUP 25-26', 11, 2,   'Maique, Jay M.'],
        ['Grade 11 GROUP 27-28', 11, 2,   'Sanchez, Jecelyn E.'],
        ['Grade 11 GROUP 29-30', 11, 2,   null],
        ['Grade 11 GROUP 31-32', 11, 2,   'Lozano, Liezl Mae B.'],
        ['Grade 11 GROUP 33-34', 11, 2,   'Mordeno, Patricia Therese M.'],
        ['Grade 11 GROUP 35-36', 11, 2,   'Baje, Benito A.'],
        ['Grade 11 GROUP 37',    11, 1,   'Dumaicos, Aviel Sheen V.'],
        ['Grade 11 GROUP 39',    11, 1,   'Galeon, Jessevim R.'],
        ['Grade 12 GROUP 1-2',   12, 2,   'Dumaicos, Aviel Sheen V.'],
        ['Grade 12 GROUP 3-4',   12, 2,   'Galliguez, Ethel M.'],
        ['Grade 12 GROUP 5-6',   12, 2,   null],
        ['Grade 12 GROUP 7-8',   12, 2,   'Ramayla, Sherry P.'],
        ['Grade 12 GROUP 9-10',  12, 2,   null],
        ['Grade 12 GROUP 11-12', 12, 2,   'Llido, Deborah Gwen G.'],
        ['Grade 12 GROUP 13-14', 12, 2,   'Sanchez, Jecelyn E.'],
        ['Grade 12 GROUP 15-16', 12, 2,   'Grado, JL-Joshua B.'],
        ['Grade 12 GROUP 17-18', 12, 2,   'Nuñez, Nerry C.'],
        ['Grade 12 GROUP 19-20', 12, 2,   'Empuesto, Gretchen Mae B.'],
        ['Grade 12 GROUP 21',    12, 1,   'Galliguez, Ethel M.'],
        ['Grade 12 GROUP 22',    12, 1,   'Ganzon, Mary Ann M.'],
        ['Grade 12 GROUP 23-24', 12, 2,   'Fernando, Michelle B.'],
        ['Grade 12 GROUP 25-26', 12, 2,   'Yamit, Marian Mae M.'],
        ['Grade 12 GROUP 29',    12, 1,   null],
        ['Grade 12 GROUP 30',    12, 1,   'Boniel, Charles Daniel'],
    ];

    // ── Target: designations — [code, holder|null, units] ──────────────────────
    // null holder = vacant → revoke any current holder. Units differing from the
    // designation default are stored with is_overridden = true.
    private const DESIGNATIONS = [
        // Academic Unit Heads (expected unchanged — verified, not modified blind)
        ['AUH-BISES',           'Ganzon, Mary Ann M.',          6],
        ['AUH-CHEM',            'Perocho, Jayryn J.',           3],
        ['AUH-CS',              'Galeon, Jessevim R.',          3],
        ['AUH-ENG',             'Ahon, Vonna Vejle V.',         3],
        ['AUH-ENGG',            'Bermoy, Lyndon R.',            3],
        ['AUH-FIL',             'Mordeno, Yvonne M.',           3],
        ['AUH-MATH',            'Salvan, Vendy Von P.',         6],
        ['AUH-PEHM',            'Morales, Shriegley Mae R.',    3],
        ['AUH-PHY',             'Segundino, Ken Wood L.',       3],
        ['AUH-RES',             'Alerta, Gilbert',              3],
        ['AUH-SOCSCI',          'Salang, Keith R.',             3],
        // ALP / clubs
        ['ALP-13thScholar',     null,                           3],
        ['ALP-BADMINTON',       'Nuñez, Nerry C.',              3],
        ['ALP-BASKETBALL',      null,                           3],
        ['ALP-CaragaScholar',   null,                           3],
        ['ALP-DEBATE',          'Sionosa, Glenn M.',            3],
        ['ALP-DIBUHO',          'Fernando, Alvin C.',           3],
        ['ALP-FRISBEE',         null,                           3],
        ['ALP-GLEE',            'Garrido, Hannah Elizabeth P.', 3],
        ['ALP-HOMEMAKERS',      null,                           3],
        ['ALP-MATH',            null,                           2],
        ['ALP-MULTIMEDIA',      'Fernando, Chardy C.',          3],
        ['ALP-MUN',             null,                           3],
        ['ALP-RED CROSS',       'Grado, JL-Joshua B.',          3],
        ['ALP-ROBOTICS',        'Bermoy, Lyndon R.',            3],
        ['ALP-SIGALAB',         'Fulay, Louren P.',             3],
        ['ALP-TAEKWONDO',       'Orbegoso, Jenny B.',           3], // auto-created if missing
        ['ALP-VOLLEYBALL',      'Llido, Deborah Gwen G.',       3],
        // Student organization
        ['SG',                  'Bohol, Jan Grenechaux V.',     3],
        // Coordinatorships
        ['COORD-ALP',           'Bohol, Jan Grenechaux V.',     3],
        ['COORD-ALUMNI',        'Alerta, Gilbert',              1],
        ['COORD-DRRM',          'Chavez, Hernan A.',            0],
        ['COORD-EXTERNAL',      'Yamit, Marian Mae M.',         3],
        ['COORD-G11&12',        'Orbegoso, Jenny B.',           3],
        ['COORD-GPTA',          'Cuadrazal, Rea Frechie C.',    3],
        ['COORD-HRG7&8',        'Almocera, Divine Faith G.',    4],
        ['COORD-HRG9&10',       'Sanchez, Jecelyn E.',          4],
        ['COORD-LCDP',          'Garcia, Kenneth Jeason B.',    3],
        ['COORD-LMS',           'Fernando, Chardy C.',          3],
        ['COORD-LeAP',          'Sionosa, Glenn M.',            3],
        ['COORD-SCALE',         'Altar, Daisyre Mae G.',        3],
        ['COORD-SIPAYP',        'Lozano, Liezl Mae B.',         3],
        ['COORD-SIPSYP',        'Nuñez, Nerry C.',              3],
        ['COORD-TLO',           'Baje, Benito A.',              3],
        // Special assignments
        ['SA-IQA',              'Galeon, Jessevim R.',          3],
        ['SA-POLLUTION',        'Baguio, Louege',               0],
        ['SA-QMS',              'Ramayla, Sherry P.',           3],
        ['SA-SCC',              'Galeon, Jessevim R.',          3],
        ['SA-STAT',             'Alerta, Gilbert',              3],
        // Supervisory
        ['ACIDAA',              'Empuesto, Gretchen Mae B.',    9],
        ['ACIDSA',              'Dechusa, John Ridan D.',       9],
        ['CID-Chief',           'Fernando, Michelle B.',        15],
        ['SSD-Chief',           'Gumapac, Jasmine S.',          15],
        // Student conduct officers
        ['SCO-789',             'Subla, Lily Dale M.',          6],
        ['SCO-101112',          'Salang, Keith R.',             5],
        // SCALE advisers
        ['SCALE-G12-DEL MUNDO', 'Mahinay, Nikki Lou L.',        3],
        ['SCALE-G12-OROSA',     'Penados, Jhon Michael',        3],
        ['SCALE-G12-ZARA',      'Abamonga, Maricris C.',        3],
        // LeAP advisers
        ['LeAP-G10-Electron',   'Payao, Loida Mae U.',          3],
        ['LeAP-G10-Graviton',   'Mordeno, Yvonne M.',           3],
        ['LeAP-G10-NEUTRON',    'Asilom, Baby Jean P.',         3],
        ['LeAP-G10-PROTON',     null,                           3],
        ['LeAP-G11-MARS',       'Segundino, Ken Wood L.',       3],
        ['LeAP-G11-MERCURY',    null,                           3],
        ['LeAP-G11-NEPTUNE',    'Hijastro, Jhon Ryan P.',       3],
        ['LeAP-G11-VENUS',      null,                           3],
    ];

    // ── Target: homeroom (HR / HR-Academic) advisers — [level, section, adviser|null]
    // Section names as they exist in prod (incl. the Torquoise/Barrium spellings).
    private const SECTION_ADVISERS = [
        [7,  'Aquamarine', 'Fulay, Louren P.'],
        [7,  'Opal',       'Hijastro, Jhon Ryan P.'],
        [7,  'Sapphire',   null],
        [7,  'Torquoise',  null],
        [8,  'Anthurium',  'Baloria, Noah T.'],
        [8,  'Carnation',  'Mordeno, Patricia Therese M.'],
        [8,  'Daffodil',   'Altar, Daisyre Mae G.'],
        [8,  'Sunflower',  'Galliguez, Ethel M.'],
        [9,  'Barrium',    'Fernando, Alvin C.'],
        [9,  'Calcium',    'Mahinay, Nikki Lou L.'],
        [9,  'Lithium',    null],
        [9,  'Sodium',     'Valencia, Ma.Riza F.'],
        [10, 'Electron',   null],
        [10, 'Graviton',   'Yamit, Marian Mae M.'],
        [10, 'Neutron',    'Garcia, Kenneth Jeason B.'],
        [10, 'Proton',     null],
        [11, 'Mars',       'Francisco, Rotchie Glen A.'],
        [11, 'Mercury',    null],
        [11, 'Neptune',    null],
        [11, 'Venus',      'Cuadrazal, Rea Frechie C.'],
        [12, 'Del Mundo',  'Lozano, Liezl Mae B.'],
        [12, 'Orosa',      null],
        [12, 'Zara',       'Dumaicos, Aviel Sheen V.'],
    ];

    private array $errors = [];
    private array $userCache = [];
    /** @var array<int, true> user ids whose FacultyLoad must be re-synced */
    private array $affected = [];
    private bool $execute = false;
    private ?int $createdBy = null;
    private AcademicTerm $term;

    public function __construct(
        private readonly LoadComputationService $loads,
        private readonly HeadAdvisoryService $headAdvisory,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->execute = (bool) $this->option('execute');

        $current = AcademicTerm::where('is_current', true)->first();
        $termId  = $this->option('term-id') ? (int) $this->option('term-id') : $current?->id;

        if (! $termId || ! ($term = AcademicTerm::find($termId))) {
            $this->error('No academic term resolved. Pass --term-id=<id>.');
            return self::FAILURE;
        }
        if (! $current || $term->id !== $current->id) {
            $this->error("Term [{$term->id}] is not the current term — this one-shot sync only targets the current term.");
            return self::FAILURE;
        }
        $this->term = $term;

        $this->createdBy = $this->option('by')
            ? (int) $this->option('by')
            : User::whereHas('roles', fn ($q) => $q->where('roles.name', 'Administrator'))->value('id')
              ?? User::first()?->id;

        $this->info("Term   : [{$term->id}] {$term->name} (school_year_id={$term->school_year_id})");
        $this->info('Mode   : ' . ($this->execute ? 'EXECUTE — changes will be committed' : 'DRY RUN — no changes'));
        $this->line('');

        // Pre-resolve every referenced name so all resolution errors surface up front.
        $names = array_unique(array_merge(
            array_column(self::TEACHING, 0),
            array_filter(array_column(self::RESEARCH, 3)),
            array_filter(array_column(self::DESIGNATIONS, 1)),
            array_filter(array_column(self::SECTION_ADVISERS, 2)),
        ));
        foreach ($names as $name) {
            if (! $this->user($name)) {
                $this->errors[] = "NO USER MATCH: {$name}";
            }
        }

        $run = function (): void {
            $before = $this->snapshotTotals();

            $this->reconcileTeaching();
            $this->reconcileResearch();
            $this->reconcileDesignations();
            $this->reconcileSectionAdvisers();

            if ($this->execute) {
                $this->line('');
                $this->info('Syncing faculty load totals (' . count($this->affected) . ' faculty)...');
                foreach (array_keys($this->affected) as $userId) {
                    $load = $this->loads->findOrCreateFacultyLoad($userId, $this->term->school_year_id, $this->term->id);
                    $this->loads->syncLoad($load->fresh());
                }
                $this->printTotals($before);
            }
        };

        if ($this->execute) {
            if (count($this->errors) > 0) {
                $this->line('');
                foreach ($this->errors as $e) {
                    $this->error($e);
                }
                $this->error('Aborting — resolve the errors above before running with --execute.');
                return self::FAILURE;
            }
            DB::transaction($run);
        } else {
            $run();
        }

        $this->line('');
        $this->info('Done. Errors: ' . count($this->errors));
        foreach ($this->errors as $e) {
            $this->error($e);
        }

        return count($this->errors) > 0 ? self::FAILURE : self::SUCCESS;
    }

    // ── Domain 1: teaching ───────────────────────────────────────────────────
    //
    // Targets are per-(faculty, subject) unit totals; live rows are per-section.
    // Section rows are MOVED between faculty (schedules follow) to satisfy the
    // deltas; residual needs create section-less rows; residual surplus shrinks
    // or deletes rows (schedules of deleted rows are deleted too — reported).

    private function reconcileTeaching(): void
    {
        $this->info('── Teaching ──────────────────────────────────────────');

        $subjects = Subject::where('school_year_id', $this->term->school_year_id)->get()->keyBy('code');

        // target[subject_id][user_id] = units
        $target = [];
        foreach (self::TEACHING as [$name, $code, $units]) {
            $subject = $subjects->get($code);
            if (! $subject) {
                $this->errors[] = "SUBJECT NOT FOUND: {$code} (for {$name})";
                continue;
            }
            $user = $this->user($name);
            if (! $user) {
                continue; // already reported
            }
            $target[$subject->id][$user->id] = ($target[$subject->id][$user->id] ?? 0) + (float) $units;
        }

        $rows = LoadAssignment::where('academic_term_id', $this->term->id)
            ->where('assignment_type', 'teaching')
            ->withCount('classSchedules')
            ->orderBy('id')
            ->get()
            ->groupBy('subject_id');

        $subjectIds = collect(array_keys($target))->merge($rows->keys())->unique()->sort();

        foreach ($subjectIds as $subjectId) {
            $subjRows = collect($rows->get($subjectId, collect()))->values();
            $code     = $subjects->firstWhere('id', $subjectId)?->code ?? "subject#{$subjectId}";

            $cur = [];
            foreach ($subjRows as $r) {
                $cur[$r->user_id] = ($cur[$r->user_id] ?? 0) + (float) $r->load_units;
            }

            // delta > 0 → needs units; delta < 0 → gives units
            $delta = [];
            foreach ($target[$subjectId] ?? [] as $uid => $units) {
                $delta[$uid] = round($units - ($cur[$uid] ?? 0), 2);
            }
            foreach ($cur as $uid => $units) {
                if (! isset($delta[$uid])) {
                    $delta[$uid] = round(-$units, 2);
                }
            }
            $delta = array_filter($delta, fn ($d) => abs($d) > 0.01);
            if (! $delta) {
                continue;
            }

            $need = array_filter($delta, fn ($d) => $d > 0);
            $give = array_map(fn ($d) => -$d, array_filter($delta, fn ($d) => $d < 0));

            // Pass 1 — whole-row moves from donors to receivers
            foreach ($give as $donorId => $surplus) {
                foreach ($subjRows->where('user_id', $donorId)->sortByDesc('load_units') as $row) {
                    if ($surplus < 0.01) {
                        break;
                    }
                    $u = (float) $row->load_units;
                    if ($u > $surplus + 0.01) {
                        continue;
                    }
                    foreach ($need as $recvId => $needU) {
                        if ($needU + 0.01 >= $u) {
                            $this->moveTeachingRow($row, $recvId, $code);
                            $surplus        -= $u;
                            $need[$recvId]   = round($needU - $u, 2);
                            if ($need[$recvId] < 0.01) {
                                unset($need[$recvId]);
                            }
                            break;
                        }
                    }
                }
                $give[$donorId] = $surplus;
            }

            // Pass 2 — receivers still short: shave a donor row / create new rows
            foreach ($need as $recvId => $needU) {
                foreach ($give as $donorId => $surplus) {
                    if ($needU < 0.01 || $surplus < 0.01) {
                        continue;
                    }
                    $shave = min($needU, $surplus);
                    $row   = $subjRows->where('user_id', $donorId)
                        ->first(fn ($r) => $r->exists && (float) $r->load_units > $shave + 0.01);
                    if (! $row) {
                        continue;
                    }
                    $this->op("SHRINK   {$code}: {$this->nameOf($donorId)} row #{$row->id} " . (float) $row->load_units . 'u → ' . round((float) $row->load_units - $shave, 2) . 'u');
                    if ($this->execute) {
                        $row->update(['load_units' => round((float) $row->load_units - $shave, 2)]);
                    }
                    $this->touch($donorId);
                    $give[$donorId] = round($surplus - $shave, 2);
                    $needU          = round($needU - $shave, 2);
                    if ($needU > 0.01) {
                        $this->createTeachingRow($recvId, $subjectId, $shave, $code); // partial handoff
                        $needU = 0; // shave already granted below via create of full remainder
                    }
                }
                if ($needU > 0.01) {
                    $this->createTeachingRow($recvId, $subjectId, $needU, $code);
                }
            }

            // Pass 3 — donors still over target: shrink/delete their rows
            foreach ($give as $donorId => $surplus) {
                if ($surplus < 0.01) {
                    continue;
                }
                // prefer deleting rows without schedules first
                foreach ($subjRows->where('user_id', $donorId)->sortBy([['class_schedules_count', 'asc'], ['load_units', 'asc']]) as $row) {
                    if ($surplus < 0.01) {
                        break;
                    }
                    if (! $row->exists) {
                        continue;
                    }
                    $u = (float) $row->load_units;
                    if ($u <= $surplus + 0.01) {
                        $sched = (int) $row->class_schedules_count;
                        $this->op("DELETE   {$code}: {$this->nameOf($donorId)} row #{$row->id} {$u}u" . ($sched ? " + {$sched} schedule slot(s)" : ''));
                        if ($this->execute) {
                            DB::table('class_schedules')->where('load_assignment_id', $row->id)->delete();
                            $row->delete();
                        }
                        $this->touch($donorId);
                        $surplus = round($surplus - $u, 2);
                    } else {
                        $this->op("SHRINK   {$code}: {$this->nameOf($donorId)} row #{$row->id} {$u}u → " . round($u - $surplus, 2) . 'u');
                        if ($this->execute) {
                            $row->update(['load_units' => round($u - $surplus, 2)]);
                        }
                        $this->touch($donorId);
                        $surplus = 0;
                    }
                }
                if ($surplus > 0.01) {
                    $this->errors[] = "TEACHING RESIDUAL: {$code} donor {$this->nameOf($donorId)} still {$surplus}u over target";
                }
            }
        }
    }

    private function moveTeachingRow(LoadAssignment $row, int $newUserId, string $code): void
    {
        $sched = (int) $row->class_schedules_count;
        $this->op("MOVE     {$code}: row #{$row->id} " . (float) $row->load_units . "u {$this->nameOf($row->user_id)} → {$this->nameOf($newUserId)}"
            . ($row->section_id ? " [section {$row->section_id}]" : '')
            . ($sched ? " (+{$sched} schedule slot(s))" : ''));

        $this->touch($row->user_id);
        $this->touch($newUserId);

        if ($this->execute) {
            $load = $this->loads->findOrCreateFacultyLoad($newUserId, $this->term->school_year_id, $this->term->id);
            $row->update(['user_id' => $newUserId, 'faculty_load_id' => $load->id]);
            DB::table('class_schedules')->where('load_assignment_id', $row->id)->update(['user_id' => $newUserId]);
        }
        // mark as consumed for in-memory passes
        $row->user_id = $newUserId;
    }

    private function createTeachingRow(int $userId, int $subjectId, float $units, string $code): void
    {
        $this->op("CREATE   {$code}: {$this->nameOf($userId)} {$units}u (no section — assign via UI later)");
        $this->touch($userId);
        if ($this->execute) {
            $load = $this->loads->findOrCreateFacultyLoad($userId, $this->term->school_year_id, $this->term->id);
            LoadAssignment::create([
                'faculty_load_id'  => $load->id,
                'user_id'          => $userId,
                'school_year_id'   => $this->term->school_year_id,
                'academic_term_id' => $this->term->id,
                'assignment_type'  => 'teaching',
                'subject_id'       => $subjectId,
                'load_units'       => $units,
                'created_by'       => $this->createdBy,
            ]);
        }
    }

    // ── Domain 2: research advisories ────────────────────────────────────────

    private function reconcileResearch(): void
    {
        $this->line('');
        $this->info('── Research advisories ───────────────────────────────');

        $existing = ResearchAdvisory::where('academic_term_id', $this->term->id)->get();
        $byTitle  = $existing->keyBy(fn ($r) => $this->normTitle($r->research_title));

        $recompute = []; // "userId:grade" => [userId, grade]
        $seen      = [];

        foreach (self::RESEARCH as [$title, $grade, $units, $name]) {
            $key        = $this->normTitle($title);
            $seen[$key] = true;
            $user       = $name ? $this->user($name) : null;
            $row        = $byTitle->get($key);

            if (! $row) {
                if (! $user) {
                    continue; // vacant and not in DB — nothing to do
                }
                $this->op("CREATE   research: {$title} ({$units}u) → {$user->name}");
                $recompute["{$user->id}:{$grade}"] = [$user->id, $grade];
                $this->touch($user->id);
                if ($this->execute) {
                    ResearchAdvisory::create([
                        'user_id'            => $user->id,
                        'school_year_id'     => $this->term->school_year_id,
                        'academic_term_id'   => $this->term->id,
                        'research_title'     => $title,
                        'grade_level'        => $grade,
                        'advisory_role'      => 'lead',
                        'research_type'      => 'science_research',
                        'load_units'         => (float) $units,
                        'status'             => 'active',
                        'load_assignment_id' => null,
                    ]);
                }
                continue;
            }

            if (! $user) {
                if ($row->status === 'active') {
                    $this->op("DROP     research: {$title} (was {$this->nameOf($row->user_id)}) → vacant position");
                    $recompute["{$row->user_id}:{$row->grade_level}"] = [$row->user_id, $row->grade_level];
                    $this->touch($row->user_id);
                    if ($this->execute) {
                        $row->update(['status' => 'dropped', 'load_assignment_id' => null]);
                    }
                }
                continue;
            }

            $changes = [];
            if ($row->user_id !== $user->id) {
                $changes['user_id'] = $user->id;
                $this->op("REASSIGN research: {$title} {$this->nameOf($row->user_id)} → {$user->name}");
                $recompute["{$row->user_id}:{$row->grade_level}"] = [$row->user_id, $row->grade_level];
                $this->touch($row->user_id);
            }
            if (abs((float) $row->load_units - (float) $units) > 0.01) {
                $changes['load_units'] = (float) $units;
                $this->op("UNITS    research: {$title} " . (float) $row->load_units . " → {$units}u");
            }
            if ($row->status !== 'active') {
                $changes['status'] = 'active';
                $this->op("REVIVE   research: {$title} ({$row->status} → active)");
            }
            if ($changes) {
                $changes['load_assignment_id'] = null;
                $recompute["{$user->id}:{$grade}"] = [$user->id, $grade];
                $this->touch($user->id);
                if ($this->execute) {
                    $row->update($changes);
                }
            }
        }

        // Active groups in DB that the plan no longer lists at all
        foreach ($existing as $row) {
            if (isset($seen[$this->normTitle($row->research_title)]) || $row->status !== 'active') {
                continue;
            }
            $this->op("DROP     research: {$row->research_title} (was {$this->nameOf($row->user_id)}) → absent from plan");
            $recompute["{$row->user_id}:{$row->grade_level}"] = [$row->user_id, $row->grade_level];
            $this->touch($row->user_id);
            if ($this->execute) {
                $row->update(['status' => 'dropped', 'load_assignment_id' => null]);
            }
        }

        if ($this->execute) {
            foreach ($recompute as [$userId, $grade]) {
                $load = $this->loads->findOrCreateFacultyLoad($userId, $this->term->school_year_id, $this->term->id);
                $la   = $this->loads->recomputeResearchGrade($userId, $this->term->id, (int) $grade, $load, $this->createdBy);
                if ($la) {
                    ResearchAdvisory::where('user_id', $userId)
                        ->where('academic_term_id', $this->term->id)
                        ->where('grade_level', $grade)
                        ->where('status', 'active')
                        ->update(['load_assignment_id' => $la->id]);
                }
            }
        }
    }

    // ── Domain 3: designation assignments ────────────────────────────────────

    private function reconcileDesignations(): void
    {
        $this->line('');
        $this->info('── Designations ──────────────────────────────────────');

        foreach (self::DESIGNATIONS as [$code, $name, $units]) {
            $designation = Designation::where('code', $code)->first();

            if (! $designation && $code === 'ALP-TAEKWONDO') {
                $sibling = Designation::where('code', 'ALP-BADMINTON')->first();
                if (! $sibling) {
                    $this->errors[] = 'CANNOT CREATE ALP-TAEKWONDO: sibling ALP-BADMINTON missing';
                    continue;
                }
                $this->op('CREATE   designation ALP-TAEKWONDO (Taekwondo Club Adviser, 3u)');
                if ($this->execute) {
                    $designation = Designation::create([
                        'designation_category_id' => $sibling->designation_category_id,
                        'code'            => 'ALP-TAEKWONDO',
                        'name'            => 'Taekwondo Club Adviser',
                        'assignment_type' => $sibling->assignment_type,
                        'load_units'      => 3,
                        'requires_unit'   => false,
                        'is_active'       => true,
                        'sort_order'      => 99,
                    ]);
                } else {
                    $designation = $sibling; // stand-in so the dry-run can report the ASSIGN op
                }
            }

            if (! $designation) {
                $this->errors[] = "DESIGNATION NOT FOUND: {$code}";
                continue;
            }

            $user    = $name ? $this->user($name) : null;
            $holders = LoadAssignment::where('academic_term_id', $this->term->id)
                ->where('designation_id', $designation->id)
                ->get();

            foreach ($holders as $la) {
                if ($user && $la->user_id === $user->id) {
                    continue;
                }
                $this->op("REVOKE   {$code} from {$this->nameOf($la->user_id)} (" . (float) $la->load_units . 'u)');
                $this->touch($la->user_id);
                if ($this->execute) {
                    $la->delete();
                }
            }

            if (! $user) {
                continue;
            }

            $mine = $holders->firstWhere('user_id', $user->id);
            if (! $mine) {
                $this->op("ASSIGN   {$code} → {$user->name} ({$units}u)");
                $this->touch($user->id);
                if ($this->execute) {
                    $load = $this->loads->findOrCreateFacultyLoad($user->id, $this->term->school_year_id, $this->term->id);
                    LoadAssignment::create([
                        'faculty_load_id'  => $load->id,
                        'user_id'          => $user->id,
                        'school_year_id'   => $this->term->school_year_id,
                        'academic_term_id' => $this->term->id,
                        'assignment_type'  => $designation->assignment_type ?: 'admin',
                        'load_units'       => (float) $units,
                        'is_overridden'    => abs((float) $designation->load_units - (float) $units) > 0.01,
                        'description'      => $designation->name,
                        'designation_id'   => $designation->id,
                        'created_by'       => $this->createdBy,
                    ]);
                }
            } elseif (abs((float) $mine->load_units - (float) $units) > 0.01) {
                $this->op("UNITS    {$code}: {$user->name} " . (float) $mine->load_units . " → {$units}u");
                $this->touch($user->id);
                if ($this->execute) {
                    $mine->update([
                        'load_units'    => (float) $units,
                        'is_overridden' => abs((float) $designation->load_units - (float) $units) > 0.01,
                    ]);
                }
            }
        }
    }

    // ── Domain 4: homeroom section advisers ──────────────────────────────────

    private function reconcileSectionAdvisers(): void
    {
        $this->line('');
        $this->info('── Section (homeroom) advisers ───────────────────────');

        foreach (self::SECTION_ADVISERS as [$level, $sectionName, $name]) {
            $section = Section::where('school_year_id', $this->term->school_year_id)
                ->where('levelid', $level)
                ->where('sectionname', $sectionName)
                ->first();

            if (! $section) {
                $this->errors[] = "SECTION NOT FOUND: G{$level} {$sectionName}";
                continue;
            }

            $user   = $name ? $this->user($name) : null;
            $oldId  = $section->adviser ? (int) $section->adviser : null;
            $newId  = $user?->id;

            if ($oldId === $newId) {
                continue;
            }

            $this->op("ADVISER  G{$level} {$sectionName}: " . ($oldId ? $this->nameOf($oldId) : '(vacant)') . ' → ' . ($newId ? $user->name : '(vacant)'));

            if ($oldId) {
                $this->touch($oldId);
            }
            if ($newId) {
                $this->touch($newId);
            }

            if (! $this->execute) {
                continue;
            }

            $section->update(['adviser' => $newId]);

            // An explicit Homeroom Coordinator override takes precedence —
            // leave the HR_ADV/HR_ACAD LoadAssignment alone; it belongs to
            // the coordinator, not the adviser. See HeadAdvisoryService::
            // syncHomeroomCoordinator().
            if ($section->homeroom_coordinator_id) {
                continue;
            }

            // Maintain the HRA-/HAC- homeroom LoadAssignment (term-scoped)
            if ($oldId) {
                LoadAssignment::where('academic_term_id', $this->term->id)
                    ->where('section_id', $section->id)
                    ->where('user_id', $oldId)
                    ->whereHas('designation.category', fn ($q) => $q->whereIn('code', ['HR_ADV', 'HR_ACAD']))
                    ->delete();
            }

            if ($newId) {
                $designation = $this->headAdvisory->ensureSectionDesignation($section);
                if ($designation) {
                    $load   = $this->loads->findOrCreateFacultyLoad($newId, $this->term->school_year_id, $this->term->id);
                    $exists = LoadAssignment::where('faculty_load_id', $load->id)
                        ->where('section_id', $section->id)
                        ->where('designation_id', $designation->id)
                        ->exists();
                    if (! $exists) {
                        LoadAssignment::create([
                            'faculty_load_id'  => $load->id,
                            'user_id'          => $newId,
                            'school_year_id'   => $this->term->school_year_id,
                            'academic_term_id' => $this->term->id,
                            'assignment_type'  => 'admin',
                            'section_id'       => $section->id,
                            'load_units'       => (float) $designation->load_units,
                            'description'      => $designation->name,
                            'designation_id'   => $designation->id,
                            'created_by'       => $this->createdBy,
                        ]);
                    }
                }
            }
        }
    }

    // ── Reporting helpers ─────────────────────────────────────────────────────

    private function snapshotTotals(): array
    {
        return FacultyLoad::where('academic_term_id', $this->term->id)
            ->pluck('total_units', 'user_id')
            ->map(fn ($u) => (float) $u)
            ->all();
    }

    private function printTotals(array $before): void
    {
        $this->line('');
        $this->info('Faculty totals (before → after):');
        $after = $this->snapshotTotals();
        foreach (collect($after)->sortBy(fn ($u, $id) => $this->nameOf($id)) as $userId => $units) {
            $prev = $before[$userId] ?? 0.0;
            if (abs($prev - $units) > 0.01) {
                $this->line(sprintf('  %-40s %6.1f → %6.1f', $this->nameOf($userId), $prev, $units));
            }
        }
    }

    private function op(string $line): void
    {
        $this->line(($this->execute ? '  ' : '  [DRY] ') . $line);
    }

    private function touch(int $userId): void
    {
        $this->affected[$userId] = true;
    }

    private function normTitle(string $title): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim($title)));
    }

    private function nameOf(int $userId): string
    {
        return User::find($userId)?->name ?? "user#{$userId}";
    }

    private function user(string $rawName): ?User
    {
        if (array_key_exists($rawName, $this->userCache)) {
            return $this->userCache[$rawName];
        }

        if (isset(self::USER_ID_OVERRIDES[$rawName])) {
            return $this->userCache[$rawName] = User::find(self::USER_ID_OVERRIDES[$rawName]);
        }

        if ($rawName === self::TBA_NAME) {
            return $this->userCache[$rawName] = User::where('name', self::TBA_NAME)->first();
        }

        $parts     = explode(', ', $rawName, 2);
        $lastName  = trim($parts[0]);
        $rest      = trim($parts[1] ?? '');
        $firstName = $rest ? explode(' ', $rest)[0] : '';

        $query = User::where('status', '<>', 'inactive')->where('name', 'like', "%{$lastName}%");
        if ($firstName) {
            $query->where('name', 'like', "%{$firstName}%");
        }

        $user = $query->get()->first()
            ?? User::where('status', '<>', 'inactive')->where('name', 'like', "%{$lastName}%")->get()->first();

        return $this->userCache[$rawName] = $user;
    }
}
