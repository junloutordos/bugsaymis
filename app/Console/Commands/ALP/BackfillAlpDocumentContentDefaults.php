<?php

namespace App\Console\Commands\ALP;

use App\Models\ALP\AlpDocument;
use Illuminate\Console\Command;

/**
 * The Constitution (25) and Risk Assessment Plan (32) content schemas were
 * widened to match the official PSHS-00-F-DSA form fields exactly. Any
 * AlpDocument row created under the old, narrower default schema is missing
 * the new keys entirely (json_decode gives null for an absent key, which the
 * new Blade views render as blank — safe, but this backfill makes existing
 * drafts show the new fields explicitly instead of silently omitting them).
 * Additive only: never removes or overwrites a key that already has a value.
 */
class BackfillAlpDocumentContentDefaults extends Command
{
    protected $signature = 'alp:backfill-document-defaults {--dry-run : Report what would change without saving}';

    protected $description = 'Merge the widened Constitution/Risk Assessment Plan default content keys into already-seeded ALP documents.';

    private const NEW_KEYS = [
        'constitution' => [
            'a1_name_and_logo' => '', 'a1_purpose_rationale' => '', 'a1_mission' => '', 'a1_objectives' => [],
            'a2_eligibility' => '', 'a2_active_members' => '', 'a2_termination' => '',
            'a3_equal_opportunity' => '', 'a3_right_to_petition' => '', 'a3_freedom_of_expression' => '', 'a3_right_to_public_information' => '',
            'a4_members_board' => '', 'a4_role_board' => '', 'a4_appointment_procedure' => '', 'a4_adviser_duties' => '',
            'a5_schedule' => '', 'a5_meeting_process' => '', 'a5_absenteeism' => '',
            'a6_amendments' => '',
        ],
        'risk_plan' => [
            'nature_and_purpose' => '', 'other_hazard' => '',
            'participants_with_medical_needs' => '',
            'school_clinic_nurse' => '', 'clinic_contact_information' => '',
            'incident_person_responsible' => '', 'incident_submit_to' => '',
        ],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $changed = 0;

        foreach (self::NEW_KEYS as $documentType => $newKeys) {
            AlpDocument::where('document_type', $documentType)->each(function (AlpDocument $document) use ($newKeys, $dryRun, &$changed) {
                $content = $document->content ?? [];
                $missing = array_diff_key($newKeys, $content);

                if (empty($missing)) {
                    return;
                }

                $changed++;
                $this->line("Cycle #{$document->alp_program_cycle_id} / {$document->document_type}: adding ".implode(', ', array_keys($missing)));

                if (! $dryRun) {
                    $document->update(['content' => $content + $missing]);
                }
            });
        }

        $this->info($dryRun ? "Dry run: {$changed} document(s) would be updated." : "Backfilled {$changed} document(s).");

        return self::SUCCESS;
    }
}
