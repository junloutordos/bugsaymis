/**
 * Canonical table class tokens for BugSayMis.
 *
 * Import these in any page that still uses inline table HTML so that
 * a single change here propagates everywhere. New pages should use
 * AppTable.vue (which embeds these automatically).
 *
 * Usage:
 *   import { TH, TD, TR } from '@/Composables/useTableClasses'
 *   <th :class="TH">Column</th>
 *   <tr :class="TR">...</tr>
 *   <td :class="TD">Value</td>
 */

// ── Header cells ──────────────────────────────────────────────────────────────
/** Standard left-aligned header */
export const TH = 'px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap'
/** Centre-aligned header (numeric / status columns) */
export const TH_C = 'px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap'
/** Right-aligned header (actions column) */
export const TH_END = 'px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide'

// ── Body cells ────────────────────────────────────────────────────────────────
/** Standard data cell */
export const TD = 'px-4 py-3 text-sm text-slate-700'
/** Muted / secondary data (dates, metadata) */
export const TD_MUTED = 'px-4 py-3 text-xs text-slate-500'
/** Monospaced — for IDs, codes, control numbers */
export const TD_MONO = 'px-4 py-3 font-mono text-xs text-indigo-700'
/** Right-aligned cell (actions) */
export const TD_END = 'px-4 py-3 text-right'

// ── Row ───────────────────────────────────────────────────────────────────────
/** Standard interactive row */
export const TR = 'hover:bg-slate-50/60 transition-colors'
/** Clickable row — add cursor-pointer */
export const TR_CLICK = 'hover:bg-slate-50/60 transition-colors cursor-pointer'

// ── Card wrapper (outer shell) ────────────────────────────────────────────────
/** The standard card that wraps every table */
export const TABLE_CARD = 'bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden'
