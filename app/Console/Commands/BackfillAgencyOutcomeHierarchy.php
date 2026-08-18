<?php

namespace App\Console\Commands;

use App\Models\AgencyOutcome;
use Illuminate\Console\Command;

class BackfillAgencyOutcomeHierarchy extends Command
{
    protected $signature = 'agency-outcomes:backfill-hierarchy';

    protected $description = 'Groups agency_org_outcomes rows sharing identical outcome text into a real parent/child hierarchy via parent_id.';

    public function handle(): int
    {
        $groups = AgencyOutcome::whereNull('parent_id')->get()->groupBy('outcome');

        $groupsCreated = 0;
        $rowsLinked = 0;
        $flaggedForReview = [];

        foreach ($groups as $outcomeText => $rows) {
            if ($rows->count() < 2) {
                $row = $rows->first();
                if ($row->outcome === $row->function_type) {
                    $flaggedForReview[] = "id={$row->id} outcome=\"{$row->outcome}\"";
                }
                continue;
            }

            $first = $rows->first();
            $parent = AgencyOutcome::create([
                'outcome' => $outcomeText,
                'sub_outcome' => null,
                'function_type' => $first->function_type,
                'fiscal_year' => $first->fiscal_year,
            ]);
            $groupsCreated++;

            foreach ($rows as $row) {
                $row->parent_id = $parent->id;
                $row->save();
                $rowsLinked++;
            }
        }

        $this->info("Created {$groupsCreated} parent outcome(s), linked {$rowsLinked} existing row(s) as children.");

        if ($flaggedForReview) {
            $this->warn('Single-row outcomes that look like placeholders (outcome text equals function_type) — review manually:');
            foreach ($flaggedForReview as $line) {
                $this->line("  {$line}");
            }
        }

        return self::SUCCESS;
    }
}
