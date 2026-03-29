/**
 * Returns Tailwind badge classes for any status string used across BUGSAY-MIS.
 * Usage:  :class="statusBadgeClass(row.status)"
 */
export function statusBadgeClass(status) {
  const s = (status ?? '').toString().toLowerCase().trim()

  // ── Green / Success ──────────────────────────────────────────────────────
  if ([
    'approved', 'active', 'completed', 'request completed', 'ocd approved',
    'division approved', 'approved by pmt', 'awarded', 'screened', 'qualified',
    'outstanding', 'very satisfactory', 'pass', 'passed', 'enrolled',
    'confirmed', 'received', 'available', 'submitted to hr',
  ].includes(s)) {
    return 'bg-emerald-50 text-emerald-700'
  }

  // ── Blue / In-progress ───────────────────────────────────────────────────
  if ([
    'acted by mis', 'mis assessed the request',
    'submitted', 'submitted for rating', 'submitted to pmt',
    'evaluated', 'nominated', 'for review', 'under review',
    'scheduled', 'satisfactory', 'walk-in',
  ].includes(s)) {
    return 'bg-blue-50 text-blue-700'
  }

  // ── Amber / Pending ──────────────────────────────────────────────────────
  if ([
    'in progress','pending', 'pending approval', 'pending division chief approval',
    'pending ocd approval', 'pending fad approval',
    'draft', 'filed', 'needs improvement', 'unsatisfactory', 'under repair',
  ].includes(s)) {
    return 'bg-amber-50 text-amber-700'
  }

  // ── Red / Rejected ───────────────────────────────────────────────────────
  if ([
    'rejected', 'declined', 'cancelled', 'returned', 'returned for revision',
    'fail', 'failed', 'disqualified', 'lost', 'damaged', 'overdue', 'dropped', 'disposed',
    'division declined', 'ocd declined',
  ].includes(s)) {
    return 'bg-red-50 text-red-600'
  }

  // ── Purple / Special ─────────────────────────────────────────────────────
  if (['for rating', 'rated'].includes(s)) {
    return 'bg-purple-50 text-purple-700'
  }

  // ── Fallback ─────────────────────────────────────────────────────────────
  return 'bg-slate-100 text-slate-600'
}

/** Base classes every badge needs — combine with statusBadgeClass() */
export const badgeBase = 'inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium'
