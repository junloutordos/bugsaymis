<?php

namespace App\Console\Commands\Payroll;

use App\Enums\ApprovalStep;
use App\Models\Payroll\PayrollRun;
use App\Services\SnapshotService;
use Illuminate\Console\Command;

/**
 * BackfillPayrollApprovalSnapshots
 *
 * Backfills approval_snapshots rows for PayrollRuns that were created/approved
 * before the snapshot system was introduced.
 *
 * Safe to run multiple times — idempotent via SnapshotService.
 */
class BackfillPayrollApprovalSnapshots extends Command
{
    protected $signature   = 'snapshots:backfill-payroll {--dry-run : Preview without writing}';
    protected $description = 'Backfill approval_snapshots for existing payroll runs';

    public function __construct(private SnapshotService $snapshots)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun  = $this->option('dry-run');
        $written = 0;
        $missing = 0;

        if ($dryRun) {
            $this->warn('DRY RUN — no rows will be written.');
        }

        $stages = [
            [
                'label'    => 'prepared_by',
                'step'     => ApprovalStep::PAYROLL_PREPARED,
                'sequence' => 1,
                'fk'       => 'prepared_by',
                'at_col'   => 'created_at',
                'action'   => 'prepared',
            ],
            [
                'label'    => 'approved_by',
                'step'     => ApprovalStep::PAYROLL_APPROVED,
                'sequence' => 2,
                'fk'       => 'approved_by',
                'at_col'   => 'approved_at',
                'action'   => 'approved',
            ],
        ];

        foreach ($stages as $stage) {
            $this->info("Processing {$stage['label']}…");

            $query = PayrollRun::withTrashed()
                ->whereNotNull($stage['fk'])
                ->whereDoesntHave('approvalSnapshots', fn ($q) =>
                    $q->where('step', $stage['step'])
                );

            $total = $query->count();
            $this->line("  → {$total} run(s) need a snapshot");

            $bar = $this->output->createProgressBar($total);
            $bar->start();

            $query->chunkById(200, function ($runs) use (
                $stage, $dryRun, &$written, &$missing, $bar
            ) {
                foreach ($runs as $run) {
                    $user = \App\Models\User::find($run->{$stage['fk']});

                    if (! $user) {
                        $missing++;
                        $bar->advance();
                        continue;
                    }

                    if (! $dryRun) {
                        $this->snapshots->recordApproval(
                            approvable: $run,
                            step:       $stage['step'],
                            sequence:   $stage['sequence'],
                            action:     $stage['action'],
                            approver:   $user,
                            signedAt:   $run->{$stage['at_col']},
                        );
                    }

                    $written++;
                    $bar->advance();
                }
            });

            $bar->finish();
            $this->newLine();
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Snapshots written',       $dryRun ? "(dry run) {$written}" : $written],
                ['Skipped (user not found)', $missing],
            ]
        );

        $this->info($dryRun ? 'Dry run complete. Run without --dry-run to apply.' : 'Backfill complete.');

        return self::SUCCESS;
    }
}
