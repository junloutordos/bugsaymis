/**
 * Central source of truth for all badge/pill colour classes across CRCMIS.
 *
 * Usage:
 *   import { statusBadgeClass, badgeBase, priorityBadgeClass,
 *            originBadgeClass, issuanceTypeBadgeClass,
 *            routingStatusBadgeClass } from '@/Composables/useStatusBadge.js'
 *
 *   :class="[badgeBase, statusBadgeClass(row.status)]"
 */

// ── Generic status (used across HR, Payroll, Requests, etc.) ─────────────────
export function statusBadgeClass(status) {
  const s = (status ?? '').toString().toLowerCase().trim()

  if ([
    'approved', 'active', 'completed', 'request completed', 'ocd approved',
    'division approved', 'approved by pmt', 'awarded', 'screened', 'qualified',
    'outstanding', 'very satisfactory', 'pass', 'passed', 'enrolled',
    'confirmed', 'received', 'available', 'submitted to hr', 'released',
    'action taken', 'forwarded', 'published',
  ].includes(s)) return 'bg-emerald-100 text-emerald-700'

  if ([
    'acted by mis', 'mis assessed the request',
    'submitted', 'submitted for rating', 'submitted to pmt',
    'evaluated', 'nominated', 'for review', 'under review',
    'scheduled', 'satisfactory', 'walk-in', 'in-transit', 'in progress',
  ].includes(s)) return 'bg-blue-100 text-blue-700'

  if ([
    'pending', 'pending approval', 'pending division chief approval',
    'pending ocd approval', 'pending fad approval',
    'draft', 'filed', 'needs improvement', 'unsatisfactory', 'under repair',
    'queued', 'for ocd review',
  ].includes(s)) return 'bg-amber-100 text-amber-700'

  if ([
    'rejected', 'declined', 'cancelled', 'returned', 'returned for revision',
    'fail', 'failed', 'disqualified', 'lost', 'damaged', 'overdue', 'dropped',
    'disposed', 'division declined', 'ocd declined',
  ].includes(s)) return 'bg-red-100 text-red-600'

  if (['for rating', 'rated'].includes(s)) return 'bg-purple-100 text-purple-700'

  return 'bg-slate-100 text-slate-600'
}

/** Base classes every badge needs — combine with any *BadgeClass() helper */
export const badgeBase = 'inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium'

// ── Priority (Normal / Urgent / Rush / High / Low) ───────────────────────────
export function priorityBadgeClass(priority) {
  const map = {
    rush:   'bg-red-100 text-red-700',
    urgent: 'bg-amber-100 text-amber-700',
    high:   'bg-orange-100 text-orange-700',
    normal: 'bg-slate-100 text-slate-600',
    low:    'bg-blue-100 text-blue-700',
  }
  return map[(priority ?? '').toLowerCase()] ?? map.normal
}

// ── Urgency (Normal / Urgent / Very Urgent) — HR & Requests ──────────────────
export function urgencyBadgeClass(urgency) {
  const u = (urgency ?? '').toLowerCase()
  if (u === 'very urgent') return 'bg-red-100 text-red-700'
  if (u === 'urgent')      return 'bg-amber-100 text-amber-700'
  return 'bg-blue-100 text-blue-700'
}

// ── Document origin (external / internal) ────────────────────────────────────
export function originBadgeClass(originType) {
  return originType === 'external'
    ? 'bg-green-100 text-green-700'
    : 'bg-indigo-100 text-indigo-700'
}

// ── Issuance type codes (SO / TO / MEMO / OO / AO / CIRC / NOTICE) ───────────
export function issuanceTypeBadgeClass(type) {
  const map = {
    SO:     'bg-indigo-100 text-indigo-700',
    TO:     'bg-blue-100 text-blue-700',
    MEMO:   'bg-violet-100 text-violet-700',
    OO:     'bg-cyan-100 text-cyan-700',
    AO:     'bg-amber-100 text-amber-700',
    CIRC:   'bg-emerald-100 text-emerald-700',
    NOTICE: 'bg-rose-100 text-rose-700',
  }
  return map[type] ?? 'bg-slate-100 text-slate-600'
}

// ── Document routing step status ─────────────────────────────────────────────
export function routingStatusBadgeClass(routing) {
  const s = routing?.status ?? ''
  if (routing?.is_overdue && ['Pending', 'Received'].includes(s)) return 'bg-red-600 text-white'
  if (s === 'Action Taken') return 'bg-emerald-100 text-emerald-700'
  if (s === 'Forwarded')    return 'bg-blue-100 text-blue-700'
  if (s === 'Received')     return 'bg-indigo-100 text-indigo-700'
  if (s === 'Returned')     return 'bg-red-100 text-red-700'
  if (s === 'Queued')       return 'bg-slate-100 text-slate-400'
  return 'bg-amber-100 text-amber-700'
}

// ── HTTP method badges (Routes / API reference) ───────────────────────────────
export function httpMethodBadgeClass(method) {
  const map = {
    GET:    'bg-green-100 text-green-700',
    POST:   'bg-blue-100 text-blue-700',
    PUT:    'bg-amber-100 text-amber-700',
    PATCH:  'bg-orange-100 text-orange-700',
    DELETE: 'bg-red-100 text-red-700',
  }
  return map[method] ?? 'bg-slate-100 text-slate-600'
}

// ── Changelog release type ────────────────────────────────────────────────────
export function changelogTypeBadgeClass(type) {
  const map = {
    feat: 'bg-indigo-100 text-indigo-700',
    fix:  'bg-red-100 text-red-700',
    ops:  'bg-slate-100 text-slate-600',
  }
  return map[type] ?? map.feat
}
