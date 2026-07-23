<?php

namespace App\Console\Commands\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\User;
use App\Services\FacultyLoading\ResearchAdvisoryReconciliationService;
use Illuminate\Console\Command;
use RuntimeException;

class ReconcileCidResearchAdvisories extends Command
{
    protected $signature = 'faculty-loading:reconcile-cid-research
                            {--term-id= : Academic term ID (defaults to the current term)}
                            {--by= : User ID for created_by (defaults to the first Administrator)}
                            {--execute : Commit the reconciliation; omit for dry-run}';

    protected $description = 'Reconcile research advisories only against the CID SY 2026-2027 adjusted loading. Dry-run by default.';

    private const USER_ID_OVERRIDES = [
        'Fernando, Michelle B.' => 25,
        'Penados, Jhon Michael' => 114, // production account is spelled "John Michael"
    ];

    /**
     * CID Adjusted 18-unit Load, July 21, 2026.
     * New Biology 3 is explicitly assigned to Bayron per the CID Chief's confirmation.
     */
    private const TARGET = [
        ['title' => 'Grade 12 GROUP 29', 'grade' => 12, 'units' => 1, 'faculty' => 'Alcando, Darrell'],
        ['title' => 'GRADE 10 GROUP 25-26', 'grade' => 10, 'units' => 1, 'faculty' => 'Francisco, Rotchie Glen A.'],
        ['title' => 'Grade 12 GROUP 21', 'grade' => 12, 'units' => 1, 'faculty' => 'Galliguez, Ethel M.'],
        ['title' => 'Grade 12 GROUP 3-4', 'grade' => 12, 'units' => 2, 'faculty' => 'Galliguez, Ethel M.'],
        ['title' => 'Grade 10 GROUP 36-37', 'grade' => 10, 'units' => 1, 'faculty' => 'Ganzon, Mary Ann M.'],
        ['title' => 'Grade 12 GROUP 2, 22', 'grade' => 12, 'units' => 2, 'faculty' => 'Ganzon, Mary Ann M.'],
        ['title' => 'GRADE 10 GROUP 17-18', 'grade' => 10, 'units' => 1, 'faculty' => 'Hijastro, Jhon Ryan P.'],
        ['title' => 'Grade 11 GROUP 25-26', 'grade' => 11, 'units' => 2, 'faculty' => 'Maique, Jay M.'],
        ['title' => 'Grade 12 GROUP 5-6', 'grade' => 12, 'units' => 2, 'faculty' => 'Penados, Jhon Michael'],
        ['title' => 'Grade 11 GROUP 11-12', 'grade' => 11, 'units' => 2, 'faculty' => 'Bayron, Janina Erika S.'],
        ['title' => 'GRADE 10 GROUP 11-12', 'grade' => 10, 'units' => 1, 'faculty' => 'Baloria, Noah T.'],
        ['title' => 'Grade 11 GROUP 7-8', 'grade' => 11, 'units' => 2, 'faculty' => 'Baloria, Noah T.'],
        ['title' => 'GRADE 10 GROUP 13-14', 'grade' => 10, 'units' => 1, 'faculty' => 'Demco, Aphryl Danril B.'],
        ['title' => 'Grade 12 GROUP 9-10', 'grade' => 12, 'units' => 2, 'faculty' => 'Demco, Aphryl Danril B.'],
        ['title' => 'Grade 12 GROUP 25-26', 'grade' => 12, 'units' => 2, 'faculty' => 'Yamit, Marian Mae M.'],
        ['title' => 'GRADE 10 GROUP 3-4', 'grade' => 10, 'units' => 1, 'faculty' => 'New Chemistry 2'],
        ['title' => 'GRADE 10 GROUP 15-16', 'grade' => 10, 'units' => 1, 'faculty' => 'New Chemistry 2'],
        ['title' => 'GRADE 10 GROUP 23-24', 'grade' => 10, 'units' => 1, 'faculty' => 'Fernando, Alvin C.'],
        ['title' => 'Grade 11 GROUP 3-4', 'grade' => 11, 'units' => 2, 'faculty' => 'Galeon, Jessevim R.'],
        ['title' => 'Grade 11 GROUP 39', 'grade' => 11, 'units' => 1, 'faculty' => 'Galeon, Jessevim R.'],
        ['title' => 'GRADE 10 GROUP 9-10', 'grade' => 10, 'units' => 1, 'faculty' => 'Payao, Loida Mae U.'],
        ['title' => 'Grade 11 GROUP 5-6', 'grade' => 11, 'units' => 2, 'faculty' => 'Payao, Loida Mae U.'],
        ['title' => 'GRADE 10 GROUP 33-34', 'grade' => 10, 'units' => 1, 'faculty' => 'Bermoy, Lyndon R.'],
        ['title' => 'GRADE 10 GROUP 21-22', 'grade' => 10, 'units' => 1, 'faculty' => 'Bermoy, Lyndon R.'],
        ['title' => 'Grade 11 GROUP 16', 'grade' => 11, 'units' => 1, 'faculty' => 'Grado, JL-Joshua B.'],
        ['title' => 'Grade 11 GROUP 9-10', 'grade' => 11, 'units' => 2, 'faculty' => 'Grado, JL-Joshua B.'],
        ['title' => 'Grade 12 GROUP 15-16', 'grade' => 12, 'units' => 2, 'faculty' => 'Grado, JL-Joshua B.'],
        ['title' => 'Grade 12 GROUP 17-18', 'grade' => 12, 'units' => 2, 'faculty' => 'Nuñez, Nerry C.'],
        ['title' => 'Grade 11 GROUP 27-28', 'grade' => 11, 'units' => 2, 'faculty' => 'Sanchez, Jecelyn E.'],
        ['title' => 'Grade 12 GROUP 13-14', 'grade' => 12, 'units' => 2, 'faculty' => 'Sanchez, Jecelyn E.'],
        ['title' => 'GRADE 10 GROUP 5-6', 'grade' => 10, 'units' => 1, 'faculty' => 'Almocera, Divine Faith G.'],
        ['title' => 'Grade 11 Group 23', 'grade' => 11, 'units' => 1, 'faculty' => 'Altar, Daisyre Mae G.'],
        ['title' => 'Grade 11 GROUP 21-22', 'grade' => 11, 'units' => 2, 'faculty' => 'Boniel, Charles Daniel'],
        ['title' => 'Grade 12 GROUP 19', 'grade' => 12, 'units' => 1, 'faculty' => 'Empuesto, Gretchen Mae B.'],
        ['title' => 'Grade 11 Group 13-14,20', 'grade' => 11, 'units' => 3, 'faculty' => 'Mordeno, Patricia Therese M.'],
        ['title' => 'Grade 11 Group 24', 'grade' => 11, 'units' => 1, 'faculty' => 'Pescueso, Brigette Ursula L.'],
        ['title' => 'Grade 12 GROUP 30', 'grade' => 12, 'units' => 1, 'faculty' => 'Salvan, Vendy Von P.'],
        ['title' => 'Grade 11 GROUP 1-2', 'grade' => 11, 'units' => 2, 'faculty' => 'Gumapac, Jasmine S.'],
        ['title' => 'GRADE 10 GROUP 31-32', 'grade' => 10, 'units' => 1, 'faculty' => 'Dumaicos, Aviel Sheen V.'],
        ['title' => 'Grade 11 GROUP 17', 'grade' => 11, 'units' => 1, 'faculty' => 'Sala, John Niño C.'],
        ['title' => 'Grade 11 GROUP 15', 'grade' => 11, 'units' => 1, 'faculty' => 'Llido, Deborah Gwen G.'],
        ['title' => 'GRADE 10 GROUP 27-28', 'grade' => 10, 'units' => 1, 'faculty' => 'Segundino, Ken Wood L.'],
        ['title' => 'Grade 11 Groups 19, 33-34', 'grade' => 11, 'units' => 3, 'faculty' => 'Alerta, Gilbert'],
        ['title' => 'GRADE 10 GROUP 29-30', 'grade' => 10, 'units' => 1, 'faculty' => 'Fernando, Michelle B.'],
        ['title' => 'GRADE 10 GROUP 1-2', 'grade' => 10, 'units' => 1, 'faculty' => 'Baje, Benito A.'],
        ['title' => 'GRADE 10 GROUP 19-20', 'grade' => 10, 'units' => 1, 'faculty' => 'Ramayla, Sherry P.'],
    ];

    public function handle(ResearchAdvisoryReconciliationService $service): int
    {
        try {
            $term = $this->resolveTerm();
            $createdBy = $this->resolveCreatedBy();
            $execute = (bool) $this->option('execute');

            $this->info(($execute ? 'EXECUTE' : 'DRY RUN').": {$term->schoolYear->name} / {$term->name} (term {$term->id})");

            $result = $service->reconcile($term, self::TARGET, $execute, $createdBy, self::USER_ID_OVERRIDES);

            foreach ($result['operations'] as $operation) {
                if ($operation['action'] === 'keep' && ! $this->output->isVerbose()) {
                    continue;
                }

                $this->line($this->formatOperation($operation));
            }

            $counts = $result['counts'];
            $this->newLine();
            $this->info("Keep {$counts['keep']} | Update {$counts['update']} | Create {$counts['create']} | Drop {$counts['drop']}");
            $this->info($execute ? 'Research-only reconciliation committed and verified.' : 'No changes written. Re-run with --execute after reviewing this plan.');

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function resolveTerm(): AcademicTerm
    {
        $term = $this->option('term-id')
            ? AcademicTerm::with('schoolYear')->find((int) $this->option('term-id'))
            : AcademicTerm::with('schoolYear')->where('is_current', true)->first();

        if (! $term) {
            throw new RuntimeException('Academic term not found.');
        }

        if ($term->schoolYear?->name !== '2026-2027') {
            throw new RuntimeException("Refusing to run against {$term->schoolYear?->name}; this target is only for SY 2026-2027.");
        }

        return $term;
    }

    private function resolveCreatedBy(): ?int
    {
        if ($this->option('by')) {
            return User::findOrFail((int) $this->option('by'))->id;
        }

        return User::whereHas('roles', fn ($query) => $query->where('name', 'Administrator'))->value('users.id');
    }

    private function formatOperation(array $operation): string
    {
        if ($operation['action'] === 'drop') {
            $current = $operation['current'];

            return "DROP #{$operation['id']} | {$current['faculty']} | {$current['title']} | {$current['units']}u";
        }

        $target = $operation['target'];
        $prefix = strtoupper($operation['action']);
        $id = isset($operation['id']) ? " #{$operation['id']}" : '';

        return "{$prefix}{$id} | {$target['faculty']} | {$target['title']} | {$target['units']}u";
    }
}
