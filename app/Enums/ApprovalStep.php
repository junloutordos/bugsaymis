<?php

namespace App\Enums;

/**
 * Canonical step name constants used as the `step` column value
 * in approval_snapshots.
 *
 * Using constants prevents typo drift when the same step name
 * is referenced across controllers, services, and tests.
 */
class ApprovalStep
{
    // ── HR Leave ───────────────────────────────────────────────────────────────
    const LEAVE_HR_OFFICER      = 'hr_officer';       // seq 1 — certifies credits
    const LEAVE_DIVISION_CHIEF  = 'division_chief';   // seq 2 — recommends
    const LEAVE_CAMPUS_DIRECTOR = 'campus_director';  // seq 3 — final approval

    // ── Payroll ────────────────────────────────────────────────────────────────
    const PAYROLL_PREPARED = 'prepared_by';  // seq 1
    const PAYROLL_APPROVED = 'approved_by';  // seq 2

    // ── Procurement ───────────────────────────────────────────────────────────
    const PROC_BUDGET_OFFICER = 'budget_officer';  // seq 1
    const PROC_PROCUREMENT    = 'procurement';     // seq 2
    const PROC_DIVISION_CHIEF = 'division_chief';  // seq 3
    const PROC_OCD            = 'ocd';             // seq 4

    // ── Service Requests (Vehicle, Work, Service, Messengerial) ───────────────
    const REQ_DIVISION_CHIEF = 'division_chief';   // seq 1
    const REQ_GSU            = 'gsu_head';         // seq 2
    const REQ_FAD            = 'fad';              // seq 3
    const REQ_OCD            = 'ocd';              // seq 4

    // ── SALN ───────────────────────────────────────────────────────────────────
    const SALN_REVIEWED = 'reviewer';  // seq 1
    const SALN_APPROVED = 'approver';  // seq 2
    const SALN_FILED    = 'filer';     // seq 3

    // ── Recruitment ───────────────────────────────────────────────────────────
    const RECRUIT_PANEL      = 'panel_member';  // seq 1..N (one per panel member)
    const RECRUIT_HR_APPROVE = 'hr_approver';   // final

    // ── Faculty Loading ───────────────────────────────────────────────────────
    const FACULTY_OVERLOAD_APPROVED = 'overload_approver';

    // ── IDP / Training ────────────────────────────────────────────────────────
    const IDP_SUPERVISOR = 'supervisor';
    const IDP_APPROVER   = 'approver';

    // ── Signatory role labels (for signatory_snapshots.role_label) ────────────
    const SIG_CERTIFYING_OFFICER  = 'Certifying Officer';
    const SIG_AUTHORIZED_OFFICER  = 'Authorized Officer';
    const SIG_AUTHORIZED_OFFICIAL = 'Authorized Official';
    const SIG_PREPARED_BY         = 'Prepared By';
    const SIG_APPROVED_BY         = 'Approved By';
    const SIG_NOTED_BY            = 'Noted By';
}
