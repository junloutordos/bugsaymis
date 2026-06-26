<?php

namespace Database\Seeders;

use App\Models\Discipline\DisciplineOffense;
use Illuminate\Database\Seeder;

/**
 * Starter offense catalog derived from the PSHS-CRC Student Handbook
 * (Student Behavior & Code of Discipline). The official Level 1/2/3
 * sanction matrix should be finalized by the Discipline Office; sanctions
 * here are placeholders ("Refer to PSHS Code of Conduct") where unconfirmed.
 *
 * Idempotent: keyed by `code` so re-running won't duplicate rows.
 */
class DisciplineOffenseSeeder extends Seeder
{
    public function run(): void
    {
        $offenses = [
            // ── Level 1 (minor) ───────────────────────────────────────────────
            ['code' => 'CC-L1-01', 'level' => 1, 'category' => 'Uniform & Grooming', 'title' => 'Incomplete or improper uniform'],
            ['code' => 'CC-L1-02', 'level' => 1, 'category' => 'Uniform & Grooming', 'title' => 'No ID / improper wearing of ID'],
            ['code' => 'CC-L1-03', 'level' => 1, 'category' => 'Uniform & Grooming', 'title' => 'Haircut / grooming non-compliance', 'description' => 'Six (6) instances of non-compliance is considered a Level 1 offense.'],
            ['code' => 'CC-L1-04', 'level' => 1, 'category' => 'Attendance',          'title' => 'Tardiness'],
            ['code' => 'CC-L1-05', 'level' => 1, 'category' => 'Attendance',          'title' => 'Cutting class'],
            ['code' => 'CC-L1-06', 'level' => 1, 'category' => 'Conduct',             'title' => 'Loitering during class hours / unnecessary noise'],
            ['code' => 'CC-L1-07', 'level' => 1, 'category' => 'Conduct',             'title' => 'Littering / failure to clean as you go'],

            // ── Level 2 (serious) ─────────────────────────────────────────────
            ['code' => 'CC-L2-01', 'level' => 2, 'category' => 'Academic Integrity',  'title' => 'Cheating during an examination'],
            ['code' => 'CC-L2-02', 'level' => 2, 'category' => 'Academic Integrity',  'title' => 'Possession of unauthorized exam materials ("cheat sheet")'],
            ['code' => 'CC-L2-03', 'level' => 2, 'category' => 'Academic Integrity',  'title' => 'Use of communication device during an exam'],
            ['code' => 'CC-L2-04', 'level' => 2, 'category' => 'Academic Integrity',  'title' => 'Collaborating when instructed to work independently'],
            ['code' => 'CC-L2-05', 'level' => 2, 'category' => 'Property',             'title' => 'Damage to school property'],
            ['code' => 'CC-L2-06', 'level' => 2, 'category' => 'Conduct',             'title' => 'Use of prohibited gadget / device in class'],
            ['code' => 'CC-L2-07', 'level' => 2, 'category' => 'Conduct',             'title' => 'Disrespect toward school authorities, teachers, or personnel'],

            // ── Level 3 (major) ───────────────────────────────────────────────
            ['code' => 'CC-L3-01', 'level' => 3, 'category' => 'Bullying',            'title' => 'Bullying (physical, verbal, or social)'],
            ['code' => 'CC-L3-02', 'level' => 3, 'category' => 'Bullying',            'title' => 'Cyberbullying'],
            ['code' => 'CC-L3-03', 'level' => 3, 'category' => 'Bullying',            'title' => 'Gender-based bullying'],
            ['code' => 'CC-L3-04', 'level' => 3, 'category' => 'Academic Integrity',  'title' => 'Plagiarism'],
            ['code' => 'CC-L3-05', 'level' => 3, 'category' => 'Academic Integrity',  'title' => 'Leakage / unauthorized sharing of exam questions or answers'],
            ['code' => 'CC-L3-06', 'level' => 3, 'category' => 'Conduct',             'title' => 'Lewd, obscene, or indecent conduct'],
            ['code' => 'CC-L3-07', 'level' => 3, 'category' => 'Conduct',             'title' => 'Possession of prohibited substances (alcohol, drugs, pornography, weapons)'],
        ];

        foreach ($offenses as $i => $o) {
            DisciplineOffense::updateOrCreate(
                ['code' => $o['code']],
                array_merge($o, [
                    'sort_order'       => $i + 1,
                    'is_active'        => true,
                    'default_sanction' => $o['default_sanction'] ?? 'Refer to PSHS Code of Conduct.',
                ]),
            );
        }

        $this->command?->info('Discipline offense catalog seeded: ' . count($offenses) . ' offenses.');
    }
}
