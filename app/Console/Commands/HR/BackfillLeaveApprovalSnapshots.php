<?php

namespace App\Console\Commands\HR;

use App\Enums\ApprovalStep;
use App\Models\HR\LeaveApplication;
use App\Models\ApprovalSnapshot;
use App\Models\User;
use App\Services\SnapshotService;
use Illuminate\Console\Command;

/**
 * BackfillLeaveApprovalSnapshots
 *
 * Backfills approval_snapshots rows for LeaveApplications that were approved
 * before the snapshot system was introduced.
 *
 * Safe to run multiple times — SnapshotService::recordApproval() is idempotent
 * (it skips silently if a snapshot already exists for a given step).
 *
 * Backfills three stages:
 *   Stage 1 — hr_officer       (hr_officer_id  / hr_officer_action  / hr_officer_at)
 *   Stage 2 — division_chief   (division_chief_id / ...)
 *   Stage 3 — campus_director  (approved_by / approval_action / approved_at)
 *
 * Note: snapshots are written with current user profile data (name, position,
 * division, office) because historical profile data is not stored. This is the
 * best available approximation for records predating the snapshot system.
 */
class BackfillLeaveApprovalSnapshots extends Command
{
    protected $signature   = 'snapshots:backfill-leave {--dry-run : Preview without writing}';
    protected $description = 'Backfill approval_snapshots for existing leave applications';

    public function __construct(private SnapshotService $snapshots)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN — no rows will be written.');
        }

        $written  = 0;
        $skipped  = 0;
        $missing  = 0;  // FK set but user no longer exists

        // ── Stage definitions ─────────────────────────────────────────────────
        $stages = [
            [
                'label'      => 'Stage 1 (HR Officer)',
                'step'       => ApprovalStep::LEAVE_HR_OFFICER,
                'sequence'   => 1,
                'fk'         => 'hr_officer_id',
                'action_col' => 'hr_officer_action',
                'at_col'     => 'hr_officer_at',
                'remarks_col'=> 'hr_officer_remarks',
                'default_action' => 'certified',
            ],
            [
                'label'      => 'Stage 2 (Division Chief)',
                'step'       => ApprovalStep::LEAVE_DIVISION_CHIEF,
                'sequence'   => 2,
                'fk'         => 'division_chief_id',
                'action_col' => 'division_chief_action',
                'at_col'     => 'division_chief_at',
                'remarks_col'=> 'division_chief_remarks',
                'default_action' => 'forwarded',
            ],
            [
                'label'      => 'Stage 3 (Campus Director)',
                'step'       => ApprovalStep::LEAVE_CAMPUS_DIRECTOR,
                'sequence'   => 3,
                'fk'         => 'approved_by',
                'action_col' => 'approval_action',
                'at_col'     => 'approved_at',
                'remarks_col'=> 'approval_remarks',
                'default_action' => 'approved',
            ],
        ];

        // ── Process each stage ────────────────────────────────────────────────
        foreach ($stages as $stage) {
            $this->info("Processing {$stage['label']}…");

            // Only rows where the FK is filled but no snapshot exists yet
            $query = LeaveApplication::withTrashed()
                ->whereNotNull($stage['fk'])
                ->whereDoesntHave('approvalSnapshots', fn ($q) =>
                    $q->where('step', $stage['step'])
                );

            $total = $query->count();
            $this->line("  → {$total} application(s) need a snapshot");

            $bar = $this->output->createProgressBar($total);
            $bar->start();

            $query->chunkById(200, function ($applications) use (
                $stage, $dryRun, &$written, &$skipped, &$missing, $bar
            ) {
                foreach ($applications as $app) {
                    $userId = $app->{$stage['fk']};
                    $user   = User::find($userId);

                    if (! $user) {
                        $missing++;
                        $bar->advance();
                        continue;
                    }

                    // Determine action — fall back to stage default when NULL
                    $action  = $app->{$stage['action_col']} ?? $stage['default_action'];
                    $remarks = $app->{$stage['remarks_col']} ?? '';
                    $signedAt = $app->{$stage['at_col']} ?? $app->updated_at ?? $app->created_at;

                    if (! $dryRun) {
                        $this->snapshots->recordApproval(
                            approvable: $app,
                            step:       $stage['step'],
                            sequence:   $stage['sequence'],
                            action:     $action,
                            approver:   $user,
                            remarks:    $remarks,
                            signedAt:   $signedAt,
                        );
                    }

                    $written++;
                    $bar->advance();
                }
            });

            $bar->finish();
            $this->newLine();
        }

        // ── Summary ───────────────────────────────────────────────────────────
        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Snapshots written',        $dryRun ? "(dry run) {$written}" : $written],
                ['Skipped (already existed)', $skipped],
                ['Skipped (user not found)',  $missing],
            ]
        );

        if ($missing > 0) {
            $this->warn("{$missing} application(s) had a user FK that no longer exists — those snapshots were skipped.");
        }

        $this->info($dryRun ? 'Dry run complete. Run without --dry-run to apply.' : 'Backfill complete.');

        return self::SUCCESS;
    }
}
