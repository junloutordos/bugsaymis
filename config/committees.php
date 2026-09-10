<?php

return [
    /*
     * Soft warning threshold (load units) for a member's total ACTIVE
     * committee load this term. Exceeding this never blocks a save — it
     * only surfaces an inline warning banner to the admin, since committee
     * load stacking is sometimes intentional (e.g., a senior faculty
     * chairing multiple committees).
     */
    'load_conflict_threshold' => env('COMMITTEE_LOAD_CONFLICT_THRESHOLD', 3.0),

    /*
     * Minimum active-member ratio (active / max_members) below which a
     * committee is flagged as having a vacancy, for the scheduled vacancy
     * alert command.
     */
    'vacancy_alert_enabled' => env('COMMITTEE_VACANCY_ALERTS_ENABLED', true),
];
