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

    // ── Procurement (legacy basic flow) ──────────────────────────────────────
    const PROC_BUDGET_OFFICER = 'budget_officer';  // seq 1
    const PROC_PROCUREMENT    = 'procurement';     // seq 2
    const PROC_DIVISION_CHIEF = 'division_chief';  // seq 3
    const PROC_OCD            = 'ocd';             // seq 4

    // ── PR — Purchase Request ─────────────────────────────────────────────────
    const PR_DIVISION_CHIEF       = 'pr_division_chief';       // seq 1
    const PR_PROCUREMENT_OFFICER  = 'pr_procurement_officer';  // seq 2
    const PR_OCD                  = 'pr_ocd';                  // seq 3
    const PR_SUPP_DIVISION_CHIEF  = 'pr_supp_dc';              // supplemental seq 1
    const PR_SUPP_BUDGET_OFFICER  = 'pr_supp_bo';              // supplemental seq 2
    const PR_SUPP_OCD             = 'pr_supp_ocd';             // supplemental seq 3

    // ── ORS — Obligation Request Status ───────────────────────────────────────
    const ORS_DIVISION_CHIEF  = 'ors_division_chief';   // seq 1
    const ORS_BUDGET_OFFICER  = 'ors_budget_officer';   // seq 2
    const ORS_BOOKKEEPER      = 'ors_bookkeeper';       // seq 3
    const ORS_ACCOUNTANT      = 'ors_accountant';       // seq 4
    const ORS_OCD             = 'ors_ocd';              // seq 5
    const ORS_CANVASER        = 'ors_canvaser';         // seq 6

    // ── DV — Disbursement Voucher ─────────────────────────────────────────────
    const DV_DIVISION_CHIEF   = 'dv_division_chief';    // seq 1
    const DV_BOOKKEEPER       = 'dv_bookkeeper';        // seq 2
    const DV_ACCOUNTANT       = 'dv_accountant';        // seq 3
    const DV_OCD_SIGN         = 'dv_ocd_sign';          // seq 4
    const DV_CASHIER          = 'dv_cashier';           // seq 5
    const DV_OCD_PAYMENT      = 'dv_ocd_payment';       // seq 6

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
